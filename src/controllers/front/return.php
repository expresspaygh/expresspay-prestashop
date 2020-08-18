<?php

require_once(dirname(__FILE__) . '/BaseController.php');

class expresspayReturnModuleFrontController extends BaseController {
    public function run() {
        if (isset($_REQUEST['order-id']) && isset($_REQUEST['token'])) {
            if ($query = $this->queryExpressPayByToken($_REQUEST['token'])) {
                if ($query['result'] == 1) {
                    $order = Order::getByCartId($_REQUEST['order-id']);
                    if ($order && isset($order->id)) {
                        $this->processPayment($order, $query);
                        $this->redirectSuccess($order->id_cart, 'Payment successful. Your order will be updated shortly');
                    }
                    else {
                        $this->redirectError('Order not found');
                    }
                }
                else {
                    $this->redirectError($query['result-text']);
                }
            }
        }
        $this->redirectError();
    }
}
