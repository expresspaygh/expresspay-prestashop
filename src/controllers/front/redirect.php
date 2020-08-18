<?php

require_once(dirname(__FILE__) . '/BaseController.php');

class expresspayRedirectModuleFrontController extends BaseController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'expresspay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = $this->context->customer;
        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        $form_fields = array(
            'redirect-url' => $this->getReturnUrl(),
            'post-url' => $this->getWebhookUrl(),

            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'email' => $customer->email,

            'order-id' => $cart->id,
            'amount' => $total,
            'currency' => $currency->iso_code,
        );

        if ($token = $this->getExpressPayCheckoutToken($form_fields)) {
            $this->module->validateOrder($cart->id, Configuration::get('PS_OS_EXPRESSPAY_PENDING'), $total, $this->module->displayName, 'Customer redirected', NULL, (int)$currency->id, false, $customer->secure_key);
            $url = $this->buildExpressPayApiUrl('checkout.php', ['token' => $token['token']]);
            header('Location: ' . $url);
            exit;
        }
        else {
            $this->redirectError('Payment method setup is invalid');
        }
    }
}
