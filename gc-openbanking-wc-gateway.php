<?php

/*
 * Plugin Name: GoCardless Open Banking WC payment gateway
 * Description: A woocoommerce payment gateway integration with GoCardless open banking API
 * Version: 1.0.0
 * Author: evince | Adam Kent & Dan Vince
 * Author URI: https://www.evince.uk
 * License: GPLv2 or later
 * Text Domain: gc-openbanking-wc-gateway
*/


define( 'PLUGIN_DIR',                       plugin_dir_path( __FILE__ ) );
define( 'LIB_DIR',                          plugin_dir_path( __FILE__ ) . '/lib' );
define( 'JS_DIR',                           plugin_dir_url( __FILE__ ) . '/js' );
define( 'GC_JS_DROPIN_URI',                 'https://pay.gocardless.com/billing/static/dropin/v2/initialise.js' );
define( 'GC_SANDBOX_API_BASE',              'https://api-sandbox.gocardless.com/' );
define( 'GC_LIVE_API_BASE',                 'https://api.gocardless.com/' );
define( 'GC_BILLING_REQUEST_ENDPOINT',      'billing_requests' );
define( 'GC_BILLING_REQUEST_FLOW_ENDPOINT', 'billing_request_flows' );
define( 'GC_PAYMENTS_ENDPOINT',             'payments' );
define( 'GC_API_VERSION',                   '2015-07-06' );
define( 'WC_ORDER_RECIEVED_URL',            '/order-recieved' );



class gc_ob_wc_gateway {

    public object $gatewayWoocom;
    public object $gatewayGocardless;


    public function __construct() {

        // INSTANTIATE GATEWAY
        add_action( 'plugins_loaded', [$this, 'instantiateGateway'] );

        // ADD GATEWAY TO GATEWAYS
        add_filter( 'woocommerce_payment_gateways', [$this, 'addGateway']);

        // REGISTER SCRIPTS
        add_action( 'wp_enqueue_scripts', [$this, 'enqueueScripts'] );

        // REGISTER AJAX ACTIONS
        add_action( 'wp_ajax_initBillingRequest', [$this, 'initBillingRequestController'] );
        add_action( 'wp_ajax_nopriv_initBillingRequest', [$this, 'initBillingRequestController'] );

    }


    /**
     *  ADD GATEWAY TO WOOCOM GATEWAYS
     */

    public function addGateway($gateways) {
        array_push($gateways, 'gateway_woocom');
        return $gateways;
    }


    /**
     *  INSTANTIATE GATEWAY
     */

    public function instantiateGateway() {
        include_once( LIB_DIR . '/gateway-woocom.php' );
        include_once( LIB_DIR . '/gateway-gocardless.php' );

        $this->gatewayWoocom = new gateway_woocom();

        // instantiate gocardless class if gateway is enabled
        if ($this->gatewayWoocom->active) {
            
            // test mode
            if ($this->gatewayWoocom->testMode) {
                $this->gatewayGocardless = new gateway_gocardless(
                    $this->gatewayWoocom->sandboxToken,
                    GC_SANDBOX_API_BASE,
                    $this->gatewayWoocom->testMode,
                    $this->gatewayWoocom->reuseCustomers
                );
            }
    
            // live mode
            else {
                $this->gatewayGocardless = new gateway_gocardless(
                    $this->gatewayWoocom->liveToken,
                    GC_LIVE_API_BASE,
                    $this->gatewayWoocom->testMode,
                    $this->gatewayWoocom->reuseCustomers
                );
            }

        }

    }


    /**
     *  PLUGIN ACTIVATION
     */

    public function pluginActivation() {
        
    }


    /**
     *  ENQUEUE & LOCALIZE SCRIPTS
     */

    public function enqueueScripts() {
        wp_enqueue_script( 'gc-dropin', GC_JS_DROPIN_URI, array(), '1.0.0' );
        wp_enqueue_script( 'gc-wc-gateway', JS_DIR . '/gc-wc-gateway.js', array( 'jquery', 'gc-dropin' ), '1.0.0' );
    
        $gcGatewayVars = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'basket_url'=> wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url()
        ];

        wp_localize_script( 'gc-wc-gateway', 'gcGateway', $gcGatewayVars );
    }


    /**
     *  INITIATE CLASSES FOR AJAX BILLING REQUEST
     */

    public function initBillingRequestController() {
        $this->instantiateGateway();
        $this->gatewayGocardless->initBillingRequest();
    }
}

new gc_ob_wc_gateway();

?>