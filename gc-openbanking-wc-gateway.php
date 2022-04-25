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


define( 'PLUGIN_DIR',                   plugin_dir_path( __FILE__ ) );
define( 'LIB_DIR',                      plugin_dir_path( __FILE__ ) . '/lib' );
define( 'JS_DIR',                       plugin_dir_path( __FILE__ ) . '/js' );
define( 'GC_JS_DROPIN_URI',            'https://pay.gocardless.com/billing/static/dropin/v2/initialise.js' );
define( 'GC_SANDBOX_API_BASE',         'https://api-sandbox.gocardless.com/' );
define( 'GC_LIVE_API_BASE',            'https://api.gocardless.com/' );
define( 'GC_BILLING_REQUEST_ENDPOINT', 'billing_requests' );

//include_once( LIB_DIR . '/gatewat-gocardless.php' );


class gc_ob_wc_gateway {


    public function __construct() {

        // INSTANTIATE GATEWAY
        add_action( 'plugins_loaded', [$this, 'instantiateGateway'] );

        // ADD GATEWAY TO GATEWAYS
        add_filter( 'woocommerce_payment_gateways', [$this, 'addGateway']);

        // REGISTER SCRIPTS
        add_action( 'wp_enqueue_scripts', [$this, 'enqueueScripts'] );

        // REGISTER AJAX ACTIONS

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

        $gatewayWoocom = new gateway_woocom();

        // instantiate gocardless class if gateway is enabled
        if ($gatewayWoocom->active) {
            
            // test mode
            if ($gatewayWoocom->testMode) {
                $gatewayGocardless = new gateway_gocardless(
                    $gatewayWoocom->sandboxToken,
                    GC_SANDBOX_API_BASE
                );
            }
    
            // test mode
            else {
                $gatewayGocardless = new gateway_gocardless(
                    $gatewayWoocom->liveToken,
                    GC_LIVE_API_BASE
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
     *  ENQUEUE SCRIPTS
     */

    public function enqueueScripts() {

        wp_enqueue_script( 'gc-wc-gateway', JS_DIR . '/gc-wc-gateway.js', ['jQuery'], '1.0.0' );
    
    }

}

new gc_ob_wc_gateway();

?>