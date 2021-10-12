$(document).ready(function () {
    checkIfDeviceSupportApplePay();
});

if (typeof prestashop !== 'undefined') {
    prestashop.on(
        'changedCheckoutStep',
        function () {
            checkIfDeviceSupportApplePay();
        }
    );
}

function checkIfDeviceSupportApplePay()
{
    try {
        if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
            removeApplePay();
        }
    } catch (error) {
        removeApplePay();
    }
}

function removeApplePay()
{
    $("#multisafepay-form-applepay").parent().closest("div").prev("div").remove();
    $("#multisafepay-form-applepay").parent().closest("div").remove();
}