<?php

class gateway_woocom extends WC_Payment_Gateway {

    public bool $testMode;
    public bool $active = false;
    public string $sandboxToken;
    public string $liveToken;


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

        $this->testMode = (bool) $this->get_option('test_mode');
        $this->sandboxToken = $this->get_option('sandbox_access_token');
        $this->liveToken = $this->get_option('live_access_token');

        // enable
        if ($this->enabled !== 'no') {
            $this->active = true;
        }
    }


    /**
     *  WOOCOM PAYMENT METHOD SETTING FIELDS
     */

    public function init_form_fields() {

        // ENABLE, TITLE, DESCRIPTION, TEST MODE, ACCESS TOKEN

        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Go Cardless Open Banking Gateway',
                'default' => 'yes'
            ),
            'payment_method_title' => array(
                'title'   => 'Payment Method Title',
                'type'    => 'text',
                'default' => 'Instant bank payment'
            ),
            'description' => array(
                'title'   => 'Payment Method Description',
                'type'    => 'text',
                'default' => 'Pay with an instant bank payment, and setup a direct debit where required'
            ),
            'test_mode' => array(
                'title'   => 'Enable Test Mode',
                'type'    => 'checkbox',
                'label'   => 'Turn on test mode',
                'default' => 'no'
            ),
            'sandbox_access_token' => array(
                'title'   => 'Sandbox access token',
                'type'    => 'text'
            ),
            'live_access_token' => array(
                'title'   => 'Live access token',
                'type'    => 'text'
            )
        );

    }


    /**
     *  SAVE PAYMENT METHOD SETTING FIELDS (not required?)
     */

    public function saveGatewaySettings() {

        $enabled = (!empty($_POST['woocommerce_gc_ob_wc_gateway_enabled'])) ? $_POST['woocommerce_gc_ob_wc_gateway_enabled'] : 0 ;
        $methodTitle = (!empty($_POST['woocommerce_gc_ob_wc_gateway_payment_method_title'])) ? $_POST['woocommerce_gc_ob_wc_gateway_payment_method_title'] : '' ;
        $methodDesc = (!empty($_POST['woocommerce_gc_ob_wc_gateway_description'])) ? $_POST['woocommerce_gc_ob_wc_gateway_description'] : '' ;
        $testMode = (!empty($_POST['woocommerce_gc_ob_wc_gateway_test_mode'])) ? $_POST['woocommerce_gc_ob_wc_gateway_test_mode'] : 0 ;
        $sandboxToken = (!empty($_POST['woocommerce_gc_ob_wc_gateway_sandbox_access_token'])) ? $_POST['woocommerce_gc_ob_wc_gateway_sandbox_access_token'] : '' ;
        $liveToken = (!empty($_POST['woocommerce_gc_ob_wc_gateway_live_access_token'])) ? $_POST['woocommerce_gc_ob_wc_gateway_live_access_token'] : '' ;
        
    }


    /**
     *   REGISTER SCRIPTS
     */

    public function registerScripts() {



    }

}


?>