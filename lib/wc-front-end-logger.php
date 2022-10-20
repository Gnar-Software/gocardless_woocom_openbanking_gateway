<?php


class wc_front_end_logger {

    /**
     * FRONT END NOTICE LOG
     */

    public static function frontendNotice() {

        global $woocommerce;
        $logger = wc_get_logger();

        if (empty($_POST['notice'])) {
            die(json_encode([
                'status' => 'error'
            ]));
        }

        $notice = sanitize_text_field($_POST['notice']);

        $logger->info('GC front end log: ' . $notice, array( 'source' => 'GoCardless Gateway' ));

        die(json_encode([
            'status' => 'success'
        ]));
    }


    /**
     * FRONT END ERROR LOG
     */

    public static function frontendError() {
        
        global $woocommerce;
        $logger = wc_get_logger();

        if (empty($_POST['error'])) {
            die(json_encode([
                'status' => 'error'
            ]));
        }

        $error = sanitize_text_field($_POST['error']);

        $logger->error('GC front end error: ' . $error, array( 'source' => 'GoCardless Gateway' ));

        $loggerResponse = [
            'status' => 'success'
        ];

        die(json_encode([
            'status' => 'success'
        ]));
    }


}


new wc_front_end_logger();

?>