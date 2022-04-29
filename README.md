# GO CARDLESS OPEN BANKING WOOCOMMERCE PAYMENT GATEWAY

GoCardless open banking payment gateway for Woocommerce. Uses GC's open banking billing request flow and JS dropin.


# TO DO

- if reuse customer is on in settings -> check if customer exists in GC, if so get customer ID, if not create it. Use the customer ID in billing request setup, and lock customer details in flow.
- sanitize - billing email
- serverside check payment was actually succesfull before processing order




# ACTIONS / FILTERS / HOOKS

// checkout post validation hook
- filter -> checkout_submitted_pre_gc_flow
    - arguments = $errorMessages (array)  // add error message to array to prevent start of GC flow