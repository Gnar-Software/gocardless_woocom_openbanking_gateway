# GO CARDLESS OPEN BANKING WOOCOMMERCE PAYMENT GATEWAY

GoCardless open banking payment gateway for Woocommerce. Uses GC's open banking billing request flow and JS dropin.


# TO DO

- if reuse customer is on in settings -> check if customer exists in GC, if so get customer ID, if not create it. Use the customer ID in billing request setup, and lock customer details in flow.
- disable button until something
- checkout form submit -> we need to override WC standard ajax behaviour to stop the error processing order error
- success redirect URL -> we should use ($order->get_checkout_order_received_url();) but order object is not available at point modal is created
- sanitize - billing email


- add links customer id to billing request
- implement retrieveCustomer()