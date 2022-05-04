<?php

class gateway_webhook {

    public object $woocomObj;
    public object $gocardlessObj;
    private WP_REST_Request $request;
    private WP_REST_Response $response;
    private array $responseData;


    public function __construct($woocom, $gocardless) {

        $this->woocomObj = $woocom;
        $this->gocardlessObj = $gocardless;
        $this->response = new WP_REST_Response();
        $this->responseData = [];

        // register endpoint
        add_action( 'rest_api_init', [$this, 'registerEndpoints'] );

    }


    /**
     *  REGISTER ENDPOINTS
     *  // https://www.onecallsim.com/wp-json/gateway_gc_wc/v1/payments
     *  
     */

    public function registerEndpoints() {

        register_rest_route(
            WEBHOOK_NAMESPACE,
            WEBHOOK_ROUTE_PAYMENTS,
            [
                'methods' => ['GET', 'POST'],
                'callback' => [$this, 'webhookController'],
                'permission_callback' => [$this, 'verifySignature']
            ]
        );

    }


    /**
     *  WEBHOOK ROUTE HANDLER
     */

    public function webhookController(WP_REST_Request $request) {

        $this->request = $request;
        $requestBody = json_decode($this->request->get_body());
        $events = $requestBody->events;

        // implement handler by type
        foreach ($events as $event) {
            try {
                if ($event->resource_type == 'payments') {
                    $this->instantBankPaymentStatus($event);
                }
            }
            catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        
        // return succesful response
        die(var_dump($this->responseData));
        $this->response->set_data($this->responseData);
        $this->response->set_status( 204 );
        http_response_code(204); 
        return $this->response;

    }


    /**
     *  AUTHORIZE
     */

    public function verifySignature(WP_REST_Request $request) {

        $secret = $this->woocomObj->get_option('webhook_secret');
        $calculatedSignature = hash_hmac('sha256', $request->get_body(), $secret);
        $requestSignature = $request->get_header('Webhook-Signature');

        if ($calculatedSignature !== $requestSignature) {
            http_response_code(498);    
            return new WP_Error('invalid token', 'invalid token', ['status' => 498]);
        }
        else {
            return true;
        }

    }


    /**
     *  INSTANT BANK PAYMENT STATUS CHANGE
     */

    public function instantBankPaymentStatus($event) {

        $logger = wc_get_logger();
        $orderStatus = '';
        $orderNote = '';
        $responseKey = 'event_' . $event->id;

        // determine status
        if (empty($event->action)) {
            return;
        }

        switch ($event->action) {
            case 'confirmed' :
                $orderStatus = 'processing';
                $orderNote   = 'GC payment successful.';
                $this->responseData[$responseKey] = 'updated order to processing';
                break;
            case 'failed' :
                $orderStatus = 'failed';
                $orderNote   = 'GC payment failed.';
                $this->responseData[$responseKey] = 'updated order as failed';
                break;
            case 'cancelled' :
                $orderStatus = 'cancelled';
                $orderNote   = 'GC payment was cancelled by the customer or their bank.';
                $this->responseData[$responseKey] = 'updated order as cancelled';
                break;
        }

        // get order with this payment id and update
        $orders = wc_get_orders([
            'gc_ob_payment_id' => $event->links->payment
        ]);


        if (!empty($orders)) {
            $order = $orders[0];

            // update order status accordingly / add order note
            if ($order->has_status($orderStatus)) {
                $order->add_order_note($orderNote);
            }
            else {
                $order->update_status($orderStatus, $orderNote);
            }

            $logger->info('Webhook payment event, order: ' . $order->id . ' status updated to: ' . $orderStatus, array( 'source' => 'GoCardless Gateway' ));
        }
        else {
            $logger->error('Webhook payment event recieved, but no order exists with Payment ID: ' . $event->links->payment, array( 'source' => 'GoCardless Gateway' ));
        }

    }

}

?>