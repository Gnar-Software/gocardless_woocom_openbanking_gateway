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
    public $gcCustomerID;


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
        $this->paymentDescription = $this->createPaymentDesc();

        $response['paymentAmount'] = $this->paymentAmount;
        $response['currency'] = $this->paymentCurrency;
        $response['paymentDescription'] = $this->paymentDescription;
        $response['mode'] = $this->mode;

        
        // customer
        // if ($this->reuseCustomer) {
        //     $customerEmail = $this->getCustomerEmail();
        //     if (!empty($customerEmail)) {
        //         $this->gcCustomerID = $this->retrieveCustomer($customerEmail);
        //     }
        // }


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

        $response['status'] = 'success';

        die(json_encode($response));
    }


    /**
     *  GET CUSTOMER EMAIL
     */

    private function getCustomerEmail() {

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return $current_user->user_email;
        }
        else {
            if (!empty($_POST['billing_email'])) {
                return empty($_POST['billing_email']);
            }
        }

        return;
    }


    /**
     *  RETRIEVE GC CUSTOMER
     */

    private function retrieveCustomer($customerEmail) {
        
    }


    /**
     *  CREATE BILLING REQUEST
     */

    public function createBillingRequest() {
        $params = (object) [];

        // customer exists in GC
        if (!empty($this->gcCustomerID)) {
            $params = (object) [
                'billing_requests' => (object) [
                    'payment_request' => (object) [
                        'currency'    => $this->paymentCurrency,
                        'amount'      => $this->paymentAmount,
                        'description' => $this->paymentDescription
                    ],
                    'links'           => (object) [
                        'customer'    => $this->gcCustomerID
                    ]
                ]
            ];
        }

        // customer doesn't exist in GC
        else {
            $params = (object) [
                'billing_requests' => (object) [
                    'payment_request' => (object) [
                        'currency'    => $this->paymentCurrency,
                        'amount'      => $this->paymentAmount,
                        'description' => $this->paymentDescription
                    ]
                ]
            ];
        }


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
     *  CREATE PAYMENT DESCRIPTION
     */

    private function createPaymentDesc() {
        global $woocommerce;
        $paymentDescription = [];

        foreach ( WC()->cart->get_cart() as $key => $val ) {
            $product = $val['data'];
            array_push($paymentDescription, $product->get_name());
        }

        return implode(', ', $paymentDescription);
    }


    /**
     *  VERIFY PAYMENT STATUS
     *  @param string payment id
     *  @return string payment status
     */

    public function verifyPayment($paymentID) {

        $getURL = $this->apiBaseURL . GC_PAYMENTS_ENDPOINT . '/' . $paymentID;
        $response = $this->getAPIRequest($getURL);

        error_log(json_encode($response), 0);
        
        $status = '';

        if (isset($response->payments->status)){
            $status = $response->payments->status;
        }

        return $status;

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


    /**
     *  SEND API GET REQUEST
     */

    private function getAPIRequest($URI) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
            'GoCardless-Version: ' . GC_API_VERSION
        ];
        
        $ch = curl_init($URI);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }
}

?>