<?php

class gateway_gocardless {

    public $successURI;
    public $failedURI;
    public $accessToken;
    public $apiBaseURL;
    public $billingRequestID;
    public $billingRequestFlowID;
    public $paymentCurrency;
    public $paymentAmount;
    public $paymentDescription;
    public $mode;


    public function __construct($accesstoken, $apiBaseUrl, $testmode) {
        $this->accessToken = $accesstoken;
        $this->apiBaseURL = $apiBaseUrl;
        
        if ($testmode !== true) {
            $this->mode = 'live';
        }
        else {
            $this->mode = 'sandbox';
        }
 
    }


    /**
     *  INIT BILLING REQUEST
     */

    public function initBillingRequest() {
        global $woocommerce;
        $response = [];

        // prepare order details
        $this->paymentAmount = $woocommerce->cart->total * 100;
        $this->paymentCurrency = get_woocommerce_currency();
        $this->paymentDescription = 'test description, not sure what it should be yet';

        $response['paymentAmount'] = $this->paymentAmount;
        $response['currency'] = $this->paymentCurrency;
        $response['paymentDescription'] = $this->paymentDescription;
        $response['mode'] = $this->mode;


        // billing request
        $billingRequestResponse = $this->createBillingRequest();
        //$response['billingReqResponse'] = $billingRequestResponse;

        if (isset($billingRequestResponse->error)) {
            $response['status'] = 'error';
            $response['error'] = $billingRequestResponse->error;
            die(json_encode($response));
        }
        
        if (isset($billingRequestResponse->billing_requests->id)) {
            $this->billingRequestID = $billingRequestResponse->billing_requests->id;
        }


        // billing request flow
        $billingRequestFlowResponse = $this->createBillingRequestFlow();
        //$response['billingRequestFlowResponse'] = $billingRequestFlowResponse;

        if (isset($billingRequestFlowResponse->error)) {
            $response['status'] = 'error';
            $response['error'] = $billingRequestFlowResponse->error;
            die(json_encode($response));
        }
        
        if (isset($billingRequestFlowResponse->billing_request_flows->id)) {
            $this->billingRequestFlowID = $billingRequestFlowResponse->billing_request_flows->id;
            $response['BR_Flow_ID'] = $this->billingRequestFlowID;
        }


        // register GC checkout script and localize?



        $response['status'] = 'success';

        die(json_encode($response));
    }


    /**
     *  CREATE BILLING REQUEST
     */

    public function createBillingRequest() {

        $params = (object) [
            'billing_requests' => (object) [
                'payment_request' => (object) [
                    'currency'    => $this->paymentCurrency,
                    'amount'      => $this->paymentAmount,
                    'description' => $this->paymentDescription
                ]
            ]
        ];

        $response = $this->postAPIRequest($params, GC_BILLING_REQUEST_ENDPOINT);

        return $response;
    }


    /**
     *  CREATE BILLING REQUEST FLOW
     */

    public function createBillingRequestFlow() {

        $params = (object) [
            'billing_request_flows' => (object) [
                'redirect_uri' => get_site_url() . WC_ORDER_RECIEVED_URL,
                'links' => (object) [
                    'billing_request' => $this->billingRequestID
                ]
            ]
        ];

        $response = $this->postAPIRequest($params, GC_BILLING_REQUEST_FLOW_ENDPOINT);

        return $response;
    }


    /**
     *  SEND API POST REQUEST
     */

    private function postAPIRequest($body, $endpoint) {
        $response = '';

        $URI = $this->apiBaseURL . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'GoCardless-Version: ' . GC_API_VERSION
        ];
        
        $ch = curl_init($URI);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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