<?php

class BaseController extends ModuleFrontController {
    protected function getConfig($field) {
        $output = null;
        $config = $this->module->getConfigFormValues();
        $key = 'expresspay_' . $field;
        if (isset($config[$key])) {
            $output = $config[$key];
        }
        return $output;
    }

    protected function buildExpressPayApiUrl($path = null, $params = []) {
        $mode = $this->getConfig('environment') == 'live' ? 'live' : 'sandbox';
        $url = 'https://' . ($mode == 'sandbox' ? 'sandbox.' : null) . 'expresspaygh.com';
        $url .= "/api/$path";
        if ($params)
            $url .= '?' . http_build_query($params, '', '&');

        return $url;
    }

    protected function makeExpressPayApiRequest($path, $params, $method = 'POST') {
        $method = strtoupper($method);
        $url = $this->buildExpressPayApiUrl($path, $method == 'GET' ? $params : []);
        $params['merchant-id'] = $this->getConfig('merchant_id');
        $params['api-key'] = $this->getConfig('api_key');

        $ch = curl_init();
    
        curl_setopt( $ch, CURLOPT_USERAGENT, 'ExpressPay Magento');
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        // curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        // curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

        curl_setopt( $ch, CURLOPT_URL, $url );
        if ($method == 'POST') {
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
        }

        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    
        $response = curl_exec( $ch );
        curl_close( $ch );
        
        return json_decode($response, true);
    }

    protected function getExpressPayCheckoutToken($fields) {
        $request = $this->makeExpressPayApiRequest('submit.php', $fields);
        if (isset($request['token']) && $request['token']) {
            return $request;
        }
    }

    protected function queryExpressPayByToken($token) {
        if ($query = $this->makeExpressPayApiRequest('query.php', ['token' => $token])) {
            return $query;
        }
    }

    protected function getReturnUrl() {
        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/expresspay/return';
    }

    protected function getWebhookUrl() {
        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/expresspay/webhook';
    }

    protected function redirectError($message = null) {
        if ($message) {
            $this->errors[] = $message;
        }

        $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, ['step' => '3']));
    }

    protected function redirectSuccess($cartId, $message = null) {
        if ($message) {
            $this->success[] = $message;
        }

        $this->redirectWithNotifications($this->context->link->getPageLink('order-confirmation', true, null, ['id_cart' => $cartId, 'id_module' => (int)$this->module->id, 'key' => $this->context->customer->secure_key]));
    }

    protected function processPayment($order, $transaction) {
        if ($transaction['result'] == 1) {
            if ($order->getCurrentState() == Configuration::get('PS_OS_EXPRESSPAY_PENDING')) {
                $currency = new Currency($order->id_currency);
                $module = $this->module;

                if (strtolower($transaction['currency']) == strtolower($currency->iso_code) && floatval($transaction['amount']) >= floatval($order->total_paid)) {
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;

                    $useExistingsPayment = false;
                    if (!$order->hasInvoice()) {
                        $useExistingsPayment = true;
                    }
                    $history->changeIdOrderState((int)Configuration::get('PS_OS_EXPRESSPAY_PAID'), $order, $useExistingsPayment);
                    // $history->addWithemail(true);
                }
            }
        }
        else {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $order);
        }
    }
}