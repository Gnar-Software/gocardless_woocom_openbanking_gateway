<?php

class gateway_webhook {

    public object $woocomObj;
    public object $gocardlessObj;


    public function __construct($woocom, $gocardless) {

        $this->woocomObj = $woocom;
        $this->gocardlessObj = $gocardless;

        // register endpoint
        add_action( 'rest_api_init', [$this, 'registerEndpoints'] );

    }


    /**
     *  REGISTER ENDPOINTS
     *  // https://www.onecallsim.com/wp-json/gateway_gc_wc/v1/instant_bank_payment_status
     *  
     */

    public function registerEndpoints() {

        register_rest_route(
            WEBHOOK_NAMESPACE,
            WEBHOOK_ROUTE_PAYMENT_STATUS,
            [
                'methods' => 'GET',
                'callback' => [$this, 'webhookController']
            ]
        );

    }



    /**
     *  WEBHOOK MAIN ENTRY
     */

    public function webhookController(WP_REST_Request $request) {
        
        // authorise


        // implement handler by type


    }


    /**
     *  INSTANT BANK PAYMENT STATUS CHANGE
     */

    public function instantBankPaymentStatus() {



    }


}

?>