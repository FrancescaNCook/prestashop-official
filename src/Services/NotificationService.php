<?php declare(strict_types=1);
/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <integration@multisafepay.com>
 * @copyright   Copyright (c) MultiSafepay, Inc. (https://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */

namespace MultiSafepay\PrestaShop\Services;

use Configuration;
use MultiSafepay\Api\Transactions\TransactionResponse;
use MultiSafepay\Exception\ApiException;
use MultiSafepay\PrestaShop\Helper\LoggerHelper;
use MultiSafepay\PrestaShop\Helper\OrderMessageHelper;
use MultiSafepay\Util\Notification;
use Multisafepay;
use Order;
use OrderHistory;
use OrderPayment;
use PaymentModule;
use PrestaShopException;
use Tools;

/**
 * Class OrderService
 *
 * @package MultiSafepay\PrestaShop\Services
 */
class NotificationService
{
    /**
     * @var Multisafepay
     */
    private $module;

    /**
     * @var SdkService
     */
    private $sdkService;

    /**
     * @var PaymentOptionService
     */
    private $paymentOptionService;

    /**
     * NotificationService constructor.
     *
     * @param Multisafepay $module
     * @param SdkService $sdkService
     * @param PaymentOptionService $paymentOptionService
     */
    public function __construct(Multisafepay $module, SdkService $sdkService, PaymentOptionService $paymentOptionService)
    {
        $this->module = $module;
        $this->sdkService = $sdkService;
        $this->paymentOptionService = $paymentOptionService;
    }

    /**
     * @param string $body
     * @return void
     * @throws PrestaShopException
     */
    public function processNotification(string $body): void
    {

        if (!Notification::verifyNotification($body, $_SERVER['HTTP_AUTH'], $this->sdkService->getApiKey())) {
            $message = "Notification for transaction ID " . Tools::getValue('transactionid') . " has been received but is not valid";
            LoggerHelper::logWarning($message);
            throw new PrestaShopException($message);
        }

        $transaction = $this->getTransactionFromPostNotification($body);
        $orderCollection = Order::getByReference(Tools::getValue('transactionid'));

        /** @var Order $order */
        foreach ($orderCollection->getResults() as $order) {
            if (!$order->id) {
                $message = "It seems a notification is trying to process an order which does not exist. Transaction ID received is " . Tools::getValue('transactionid');
                LoggerHelper::logWarning($message);
                throw new PrestaShopException($message);
            }

            if ($order->module && $order->module !== 'multisafepay') {
                $message = "It seems a notification is trying to process an order processed by another payment method. Transaction ID received is " . Tools::getValue('transactionid');
                LoggerHelper::logWarning($message);
                throw new PrestaShopException($message);
            }

            // If the PrestaShop order status is the same as the one received in the notification
            if ((int)$order->current_state === (int)$this->getOrderStatusId($transaction->getStatus())) {
                continue;
            }

            // If the PrestaShop order status is considered a final status
            if ($this->isFinalStatus((int)$order->current_state)) {
                $message = "It seems a notification is trying to process an order which already have a final order status defined. For this reason notification is being ignored. Transaction ID received is " . Tools::getValue('transactionid') . " with status " . $transaction->getStatus();
                LoggerHelper::logWarning($message);
                OrderMessageHelper::addMessage($order, $message);
                continue;
            }

            // If the payment method of the PrestaShop order is not the same than the one received in the notification
            $paymentMethodName = $this->getPaymentMethodNameFromTransaction($transaction);
            if ($order->payment !== $paymentMethodName) {
                $this->updateOrderPaymentMethod($order, $paymentMethodName);
            }

            // Set new order status and set transaction id within the order information
            $this->updateOrderData($order, $transaction);

            if (Configuration::get('MULTISAFEPAY_DEBUG_MODE')) {
                LoggerHelper::logInfo('A notification has been processed for order ID: ' . $order->id . ' with status: ' . $transaction->getStatus() . ' and PSP ID: ' . $transaction->getTransactionId());
            }
        }
    }

    /**
     * Change the order status
     *
     * @param Order $order
     * @param TransactionResponse $transaction
     * @return void
     */
    private function updateOrderData(Order $order, TransactionResponse $transaction): void
    {
        // Set order status ID.
        $orderStatusId     = (int)$this->getOrderStatusId($transaction->getStatus());
        $history           = new OrderHistory();
        $history->id_order = $order->id;
        $history->changeIdOrderState($orderStatusId, $order->id, true);
        $history->addWithemail();

        // Set PSP ID within the order information
        if ('completed' === $transaction->getStatus()) {
            $payments = $order->getOrderPaymentCollection();
            /** @var OrderPayment $payment */
            foreach ($payments->getResults() as $payment) {
                $payment->transaction_id = $transaction->getTransactionId();
                $payment->amount         = $transaction->getAmount() / 100;
                $payment->update();
            }
        }
    }

    /**
     * Update the order payment method if this one change after leave checkout page.
     *
     * @param Order $order
     * @param string $paymentMethodName
     */
    private function updateOrderPaymentMethod(Order $order, string $paymentMethodName): void
    {

        // Update payment method
        $order->payment = $paymentMethodName;
        $order->save();

        // Add Order Note
        $message = 'Notification received with a different payment method for Order ID: ' . $order->id . ' and Order Reference: ' . $order->reference . ' on ' . date('d/m/Y H:i:s') . '. Payment method changed from ' . $order->payment . ' to ' . $paymentMethodName . '.';
        OrderMessageHelper::addMessage($order, $message);

        if (Configuration::get('MULTISAFEPAY_DEBUG_MODE')) {
            LoggerHelper::logInfo($message);
        }
    }

    /**
     * Return the payment method name using the transaction information
     *
     * @param TransactionResponse $transaction
     * @return string
     */
    public function getPaymentMethodNameFromTransaction(TransactionResponse $transaction)
    {
        $gatewayCode = $transaction->getPaymentDetails()->getType();
        if (strpos($gatewayCode, 'Coupon::') !== false) {
            $data = $transaction->getPaymentDetails()->getData();
            $paymentOption = $this->paymentOptionService->getMultiSafepayPaymentOption($data['coupon_brand']);
        } else {
            $paymentOption = $this->paymentOptionService->getMultiSafepayPaymentOption($gatewayCode);
        }
        return $paymentOption->name;
    }

    /**
     * @param string $body
     * @return TransactionResponse
     */
    public function getTransactionFromPostNotification(string $body): TransactionResponse
    {
        try {
            $transaction = new TransactionResponse(json_decode($body, true), $body);
            return $transaction;
        } catch (ApiException $apiException) {
            LoggerHelper::logError($apiException->getMessage());
            throw new PrestaShopException($apiException->getMessage());
        }
    }

    /**
     * Return the order status id for the given transaction status
     *
     * @param string $transactionStatus
     * @return string
     */
    public function getOrderStatusId(string $transactionStatus): string
    {
        switch ($transactionStatus) {
            case 'cancelled':
            case 'expired':
            case 'void':
            case 'declined':
                return Configuration::get('PS_OS_CANCELED');
            case 'completed':
                return Configuration::get('PS_OS_PAYMENT');
            case 'uncleared':
                return Configuration::get('MULTISAFEPAY_OS_UNCLEARED');
            case 'refunded':
                return Configuration::get('PS_OS_REFUND');
            case 'partial_refunded':
                return Configuration::get('MULTISAFEPAY_OS_PARTIAL_REFUNDED');
            case 'chargedback':
                return Configuration::get('MULTISAFEPAY_OS_CHARGEBACK');
            case 'shipped':
                return Configuration::get('PS_OS_SHIPPING');
            case 'initialized':
            default:
                return Configuration::get('MULTISAFEPAY_OS_INITIALIZED');
        }
    }

    /**
     * Return if the Order Status is final, therefore should not be changed anymore.
     *
     * @param int $orderStatus
     * @return bool
     */
    private function isFinalStatus(int $orderStatus): bool
    {
        $finalStatuses = [(int)Configuration::get('PS_OS_REFUND')];
        return in_array($orderStatus, $finalStatuses, true);
    }
}