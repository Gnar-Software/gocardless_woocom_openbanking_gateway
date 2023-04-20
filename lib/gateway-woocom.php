<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class gateway_woocom extends WC_Payment_Gateway {

    public bool $testMode;
    public bool $active = false;
    public bool $frontEndLogging;
    public string $sandboxToken;
    public string $liveToken;
    public string $customerID;
    public string $paymentRef;
    public string $paymentID;
    public string $paymentStatus;


    public function __construct() {

        // define gateway properties
        $this->id = 'gc_ob_wc_gateway';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'GoCardless Instant Bank Pay';
        $this->method_description = 'Instant bank payments using open banking technology. <br/><br/>Support recurring payments with Instant Bank Pay for WooCommerce via GoCardless Premium Plugin <a href="' . GCOB_PREMIUM_URL . '">available here</a>. <i>(Requires WooCommerce Subscriptions)</i>';

        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            // settings
            $this->init_form_fields();
            $this->init_settings();

            // save settings hook
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options'] );
        }

        if ($this->get_option('test_mode') == 'yes') {
            $this->testMode = true;
        }
        else {
            $this->testMode = false;
        }

        if ($this->get_option('front_end_logging') == 'yes') {
            $this->frontEndLogging = true;
        }
        else {
            $this->frontEndLogging = false;
        }

        $this->sandboxToken = $this->get_option('sandbox_access_token');
        $this->liveToken = $this->get_option('live_access_token');
        $this->title = $this->get_option('payment_method_title');
        $this->description = $this->get_option('description');
        

        // enable
        if ($this->enabled !== 'no') {
            $this->active = true;
        }
    }


    /**
     *  WOOCOM PAYMENT METHOD SETTING FIELDS
     */

    public function init_form_fields() {

        $webhookURL = get_home_url() . '/wp-json/' . GCOB_WEBHOOK_NAMESPACE . '/' . GCOB_WEBHOOK_ROUTE_PAYMENTS;

        // ENABLE, TITLE, DESCRIPTION, TEST MODE, ACCESS TOKEN

        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable GoCardless Instant Bank Pay Gateway',
                'default' => 'yes'
            ),
            'payment_method_title' => array(
                'title'   => 'Payment Method Title *',
                'type'    => 'text',
                'default' => 'Instant bank payment',
                'required'=> true
            ),
            'description' => array(
                'title'   => 'Payment Method Description *',
                'type'    => 'text',
                'default' => 'Pay with an instant bank payment',
                'required'=> true
            ),
            'test_mode' => array(
                'title'   => 'Enable Sandbox Mode',
                'type'    => 'checkbox',
                'label'   => 'Turn on test mode',
                'default' => 'no'
            ),
            'front_end_logging' => array(
                'title'   => 'Enable client side error logging',
                'type'    => 'checkbox',
                'label'   => 'Turn on client side error logging (bad for performance / good for sorting issues)',
                'default' => 'no'
            ),
            'sandbox_access_token' => array(
                'title'   => 'Sandbox access token',
                'type'    => 'text'
            ),
            'live_access_token' => array(
                'title'   => 'Live access token *',
                'type'    => 'text',
                'required'=> true
            ),
            'webhook_secret' => array(
                'title'   => 'Webhook secret *',
                'type'    => 'text',
                'required'=> true,
                'description' => 'Generate your webhook secret in the GoCardless Dashboard: <br/><br/> - Give your webhook a meaningful name such as your website address. <br/> - Use this URL: "' . $webhookURL . '".<br/> - Paste the secret generated above.'
            )
        );

    }


    /**
     *  WC INVOKED PROCESS PAYMENT METHOD
     */

    public function process_payment($order_id) {

        // payment is processed async using webhooks

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );

    }


    /**
     *  CREATE ORDER
     * 
     * @param int $billingRequestID
     */
    public function manualCreateOrder($billingRequestID) {

        global $woocommerce;        
        $logger = wc_get_logger();

        $order = new WC_Order();

        // Add billingRequestID to order
        update_post_meta( $order->ID, 'gc_ob_billing_request_id', $billingRequestID ); 

        if ($order->has_status('pending')) {
            $order->add_order_note('Billing request ID: ' . $billingRequestID);
        }
        else {
            $order->update_status('pending', 'Billing request ID: ' . $billingRequestID);
        }

        // Empty cart
        //$woocommerce->cart->empty_cart();

    }


    /**
     *  VERIFY PAYMENT WITH GOCARDLESS
     */

    public function verifyPayment() {

        /**
         *  Instantiate gocardless class as this is accessed 
         *  by ajax and is out of context otherwise
         */ 

        $gatewayGocardless = (object) [];

        if ($this->testMode) {
            $gatewayGocardless = new gateway_gocardless(
                $this->sandboxToken,
                GCOB_SANDBOX_API_BASE,
                $this->testMode
            );
        }
        else {
            $gatewayGocardless = new gateway_gocardless(
                $this->liveToken,
                GCOB_LIVE_API_BASE,
                $this->testMode,
                $this->reuseCustomers
            );
        }

        $paymentStatus = $gatewayGocardless->verifyPayment($this->paymentID);

        return $paymentStatus;


    }


    /**
     *  CHECKOUT FIELD VALIDATION
     */

    public static function gcValidateCheckoutFields($errors, $checkoutFields, $requiredFields) {

        $checkoutFields = json_decode(stripslashes($checkoutFields), true);
        $requiredFields = json_decode(stripslashes($requiredFields));

        foreach ($requiredFields as $requiredField) {

            // don't require shipping fields if shipping to same address
            if (!isset($checkoutFields['ship_to_different_address'])) {
                if (strpos($requiredField, 'shipping_') !== false) {
                    continue;
                }
            }

            // add error if required field is empty
            if (empty($checkoutFields[$requiredField])) {
                array_push($errors, '<strong>' . $requiredField . '</strong> is a required field' );
            }

        }

        return $errors;

    }



}

?>