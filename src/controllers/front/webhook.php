<?php

require_once(dirname(__FILE__) . '/BaseController.php');

class expresspayWebhookModuleFrontController extends BaseController {
    public function run() {
        if (isset($_REQUEST['order-id']) && isset($_REQUEST['token'])) {
            if ($query = $this->queryExpressPayByToken($_REQUEST['token'])) {
                if ($query['result'] == 1) {
                    $order = Order::getByCartId($_REQUEST['order-id']);
                    if ($order && isset($order->id)) {
                        $this->processPayment($order, $query);
                        exit;
                    }
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->changeIdOrderState(6, $order);        
                }
            }
        }
    }
}
