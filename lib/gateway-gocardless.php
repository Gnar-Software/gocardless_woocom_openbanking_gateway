<?php

class gateway_gocardless {

    public $successURI;
    public $failedURI;
    public $accessToken;
    public $apiBaseURL;
    public $billingRequestFlowID;
    public $paymentCurrency;
    public $paymentAmount;
    public $paymentDescription;


    public function __construct($accesstoken, $apiBaseUrl) {
        $this->acessToken = $accesstoken;
        $this->apiBaseURL = $apiBaseUrl;

    }


    /**
     *  INIT BILLING REQUEST
     */

    public function initBillingRequest() {

        // prepare transaction details
        $cartData = WC()->session->get('cart');
        //die(var_dump($cartData));

        // create billing request
        createBillingRequest();

        // create billing request flow
        createBillingRequestFlow();

        // register GC checkout script and localize

    }


    /**
     *  CREATE BILLING REQUEST
     */

    public function createBillingRequest() {

        $params = (object) [
            'billing_requests' => (object) [
                'payment_request' => (object) [
                    'currency'    => $this->currency,
                    'amount'      => $this->paymentAmount,
                    'description' => $this->paymentDescription
                ]
            ]
        ];

        $response = $this->postAPIRequest($params, GC_BILLING_REQUEST_ENDPOINT);

    }


    /**
     *  CREATE BILLING REQUEST FLOW
     */

    public function createBillingRequestFlow() {

        if (empty($this->billingRequestFlowID)) {
            return;
        }




    }


    /**
     *  SEND API POST REQUEST
     */

    private function postAPIRequest($body, $endpoint ) {
        $response = '';

        $URI = $this->apiBaseURL . $endpoint;
        $ch = curl_init($URI);

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}

?>