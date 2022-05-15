<?php

class gateway_woocom extends WC_Payment_Gateway {

    public bool $testMode;
    public bool $active = false;
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
        $this->method_title = 'Go Cardless Open Banking';
        $this->method_description = 'Go Cardless open banking integration using the billing request flow';

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

        $webhookURL = get_home_url() . '/wp-json/' . WEBHOOK_NAMESPACE . '/' . WEBHOOK_ROUTE_PAYMENTS;

        // ENABLE, TITLE, DESCRIPTION, TEST MODE, ACCESS TOKEN

        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Go Cardless Open Banking Gateway',
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
                'default' => 'Pay with an instant bank payment, and setup a direct debit where required',
                'required'=> true
            ),
            'test_mode' => array(
                'title'   => 'Enable Sandbox Mode',
                'type'    => 'checkbox',
                'label'   => 'Turn on test mode',
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
                'description' => 'Generate your webhook secret in the GoCardless Dashboard: <br/><br/> - Give your webhook a meaningfull name such as your website address <br/> - Use this URL: "' . $webhookURL . '".<br/> - Paste the secret generated above.'
            )
        );

    }


    /**
     *  CREATE ORDER
     */

    public function process_payment($order_id) {
        global $woocommerce;
        $logger = wc_get_logger();

        $order = new WC_Order( $order_id );

        // Payment errors
        if (isset($_POST['gc_ob_error'])) {

            if ($order->has_status('pending')) {
                $order->add_order_note('GC Payment flow was not completed');
            }
            else {
                $order->update_status('pending', 'GC Payment flow was not completed');
            }

            $logger->info('GC: payment flow was not completed', array( 'source' => 'GoCardless Gateway' ));
            wc_add_notice( __('GoCardless payment error: did not complete payment flow', 'woothemes'), 'error' );
            return;
        }

        if (isset($_POST['gc_ob_br_error'])) {

            $error = sanitize_text_field($_POST['gc_ob_error']);

            if ($order->has_status('pending')) {
                $order->add_order_note('GC Payment flow -> Payment flow was completed but there was an error');
            }
            else {
                $order->update_status('pending', 'GC Payment flow -> Payment flow was completed but there was an error');
            }

            $logger->error('GC error: Payment flow was completed but there was an error ' . $error, array( 'source' => 'GoCardless Gateway' ));
            wc_add_notice( __('GoCardless payment error: sorry something went wrong', 'woothemes'), 'error' );
            return;
        }

        if (!isset($_POST['gc_ob_customer_id']) || !isset($_POST['gc_ob_payment_ref']) || !isset($_POST['gc_ob_payment_id'])) {
            if ($order->has_status('pending')) {
                $order->add_order_note('Error recieving payment reference from GC');
            }
            else {
                $order->update_status('pending', __( 'Error recieving payment reference from GC' , 'woocommerce' ));
            }

            $logger->error('Error recieving customer ID | payment ref | payment ID from GC', array( 'source' => 'GoCardless Gateway' ));
            wc_add_notice( __('GoCardless payment error: error recieving payment reference from GC', 'woothemes'), 'error' );
            return;
        }

        // Set payment details
        $this->customerID = $_POST['gc_ob_customer_id'];
        $this->paymentRef = $_POST['gc_ob_payment_ref'];
        $this->paymentID  = $_POST['gc_ob_payment_id'];

        $order->update_meta_data('gc_ob_customer_id', $this->customerID);
        $order->update_meta_data('gc_ob_payment_ref', $this->paymentRef);
        $order->update_meta_data('gc_ob_payment_id', $this->paymentID);


        // get payment status
        $this->paymentStatus = $this->verifyPayment();

        // Bail if payment status is failed
        if ($this->paymentStatus == 'failed') {
            $order->update_status('failed', 'GC Payment was declined by the customers bank');
            $logger->info('GC payment was declined during checkout flow -> order: ' . $order_id, array( 'source' => 'GoCardless Gateway' ));
            wc_add_notice( __('GoCardless payment error: payment was declined by your bank', 'woothemes'), 'error' );
            return;
        }

        // set order status if it's confirmed
        if ($this->paymentStatus == 'confirmed') {
            $orderNote = 'Go Cardless Payment Succesful: CustomerID - ' . $this->customerID . ' PaymentRef - ' . $this->paymentRef . ' PaymentID - ' . $this->paymentID;
            $logger->info('GC payment was confirmed during checkout flow -> order: ' . $order_id, array( 'source' => 'GoCardless Gateway' ));
            $order->update_status('processing', $orderNote);
        }
        else {
            // else .. Customer bank authorised / awaiting payment
            $orderNote = 'Go Cardless Instant bank payment authorised (awaiting payment): CustomerID - ' . $this->customerID . ' PaymentRef - ' . $this->paymentRef . ' PaymentID - ' . $this->paymentID;
            $logger->info('GC payment was successful but payment is still pending at checkout completion -> order: ' . $order_id, array( 'source' => 'GoCardless Gateway' ));
            $order->update_status('pending_payment', $orderNote);
        }

        // Empty cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );

    }


    /**
     *  VERIFY PAYMENT WITH GO CARDLESS
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
                GC_SANDBOX_API_BASE,
                $this->testMode
            );
        }
        else {
            $gatewayGocardless = new gateway_gocardless(
                $this->liveToken,
                GC_LIVE_API_BASE,
                $this->testMode,
                $this->reuseCustomers
            );
        }

        $paymentStatus = $gatewayGocardless->verifyPayment($this->paymentID);

        return $paymentStatus;


    }

}

?>