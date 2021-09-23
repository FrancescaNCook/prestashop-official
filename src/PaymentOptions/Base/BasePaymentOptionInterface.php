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

namespace MultiSafepay\PrestaShop\PaymentOptions\Base;

use Order;
use MultiSafepay\Api\Transactions\OrderRequest\Arguments\GatewayInfoInterface;

interface BasePaymentOptionInterface
{
    /**
     *
     * @return string
     */
    public function getPaymentOptionName(): string;

    /**
     *
     * @return string
     */
    public function getPaymentOptionDescription(): string;

    /**
     *
     * @return string
     */
    public function getPaymentOptionGatewayCode(): string;

    /**
     *
     * @return string
     */
    public function getTransactionType(): string;

    /**
     *
     * @return string
     */
    public function getPaymentOptionLogo(): string;

    /**
     * @return string
     */
    public function getUniqueName(): string;

    /**
     * @return array
     */
    public function getGatewaySettings(): array;

    /**
     * @param Order $order
     * @param array $data
     * @return GatewayInfoInterface
     */
    public function getGatewayInfo(Order $order, array $data = []): GatewayInfoInterface;

    /**
     * @return bool
     */
    public function canProcessRefunds(): bool;
}
