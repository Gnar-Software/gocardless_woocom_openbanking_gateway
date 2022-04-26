# GO CARDLESS OPEN BANKING WOOCOMMERCE PAYMENT GATEWAY

GoCardless open banking payment gateway for Woocommerce. Uses GC's open banking billing request flow and JS dropin.


# TO DO

- disable button until something
- checkout form submit -> we need to override WC standard ajax behaviour to stop the error processing order error
- success redirect URL -> we should use ($order->get_checkout_order_received_url();) but order object is not available at point modal is created