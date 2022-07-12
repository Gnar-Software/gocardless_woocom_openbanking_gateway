(function($) {

    var gatewayFlowAlreadyStarted = false;

    $(document).ready(function() {

        // INTERRUPT WC CHECKOUT PLACE ORDER UNTIL AFTER GC FLOW IS COMPLETE
        $( 'form.checkout' ).on( 'checkout_place_order', function() {
            var payment_method = jQuery( 'form.checkout input[name="payment_method"]:checked' ).val();

            if (payment_method == 'gc_ob_wc_gateway' && !gatewayFlowAlreadyStarted) {
                
                gatewayFlowAlreadyStarted = true;
                console.log('gateway place order init');
                initGCFlow();
                return false;
            }

            gatewayFlowAlreadyStarted = false;
            return true;
        });

    });


    // INIT GO CARDLESS FLOW

    function initGCFlow() {

        // REMOVE ERROR FROM FORM IF PRESENT
        var errorField = $('input[name="gc_ob_error"]');

        if (errorField) {
            errorField.remove();
        }

        // GET ENTERED BILLING EMAIL
        var billingEmail = $('input[name="billing_email"]');
        

        // TRIGGER SERVER BILLING REQUEST
        var formdata = new FormData();
        formdata.append('action', 'initBillingRequest');
        formdata.append('billing_email', billingEmail);
        formdata.append('security', gcGateway.security)

        var checkoutFields = getFormData($( 'form.checkout' ));

        formdata.append('checkout_fields', JSON.stringify(checkoutFields));

        console.log('checkout fields: ' + JSON.stringify(checkoutFields));
        ajaxTriggerBillingRequest(formdata);
    }


    // AJAX TRIGGER BILLING REQUEST

    function ajaxTriggerBillingRequest(formdata) {

        $.ajax({
            type: 'POST',
            url: gcGateway.ajax_url,
            contentType: false,
            processData: false,
            data: formdata,
            success: function(data) {
                triggerGCModal(data);
            },
            error: function(data) {
                billingRequestSetupError(data);
            }
        });

    }


    // TRIGGER GC MODAL

    function triggerGCModal(response) {
        console.log('success: ' + response);
        var responseObj = JSON.parse(response);
        console.log(responseObj);

        // BAIL IF ERRORS
        if (responseObj.status == 'error') {
            console.log('error: ' + JSON.stringify(responseObj.error));

            var modalLaunchErrors = [];
            var modalLaunchErrorObjs = responseObj.error.errors;
            modalLaunchErrorObjs.forEach(function(errorObj) {
                modalLaunchErrors.push(errorObj.field + ': ' + errorObj.message);
            });

            displayWoocomErrors(modalLaunchErrors);
            gatewayFlowAlreadyStarted = false;
            return;
        }

        if (responseObj.validation_error) {
            console.log('validation error: ' + responseObj.validation_error);
            displayWoocomErrors(responseObj.validation_error);
            gatewayFlowAlreadyStarted = false;
            return;
        }

        console.log(responseObj.BR_Flow_ID);
        console.log(responseObj.mode);

        if (!responseObj.BR_Flow_ID || !responseObj.mode) {
            console.log('error: server response object does not contain flow ID or mode');
            gatewayFlowAlreadyStarted = false;
            return;
        }

        // CREATE HANDLER
        const handler = GoCardlessDropin.create({
            billingRequestFlowID: responseObj.BR_Flow_ID,
            environment: responseObj.mode,
            onSuccess: (billingRequest, billingRequestFlow) => {
                paymentFlowComplete(billingRequest, billingRequestFlow);
            },
            onExit: (error, metadata) => {
                paymentFlowError(error, metadata);
            },
        });

        // OPEN DROPIN
        handler.open();

    }


    // PAYMENT FLOW COMPLETE

    function paymentFlowComplete(billingRequest, billingRequestFlow) {
        
        var customerID = billingRequest.resources.customer.id;
        var paymentRef = billingRequest.links.payment_request;
        var paymentID  = billingRequest.links.payment_request_payment;

        var checkoutForm = $('form.checkout');

        if (customerID && paymentRef && paymentID) {
            $(checkoutForm).append('<input type="hidden" name="gc_ob_customer_id" value="' + customerID + '">');
            $(checkoutForm).append('<input type="hidden" name="gc_ob_payment_ref" value="' + paymentRef + '">');
            $(checkoutForm).append('<input type="hidden" name="gc_ob_payment_id" value="' + paymentID + '">');
        }
        else {
            $(checkoutForm).append('<input type="hidden" name="gc_ob_br_error" value="' + billingRequest + '">');
        }
        
        $('form.checkout').submit();
    }


    // PAYMENT WINDOW CLOSED OR ERROR

    function paymentFlowError(error, metadata) {
        console.log('we had a issue!');
        console.log('error: ' + JSON.stringify(error));
        console.log('metadata: ' + JSON.stringify(metadata));

        var checkoutForm = $('form.checkout');

        // determine type of error
        $(checkoutForm).append('<input type="hidden" name="gc_ob_error" value="error">');

        $(checkoutForm).submit();
    }


    // BILLING REQUEST SETUP ERROR

    function billingRequestSetupError(response) {
        console.log('ajax error: ' + response);
    }


    // DISPLAY WOOCOM VALIDATION ERRORS

    function displayWoocomErrors(errors) {
        
        var errorUL = $( '.checkout ul.woocommerce-error' );

        if (errorUL.length == 0) {
            $( 'form.checkout').prepend('<ul class="woocommerce-error"></ul>');
        }

        var parent = $('form.checkout ul.woocommerce-error');

        errors.forEach(function(error) {
            parent.append('<li>' + error + '</li>');
            console.log(error);
        });

    }


    // GET FORM DATA

    function getFormData($form){
        var unindexed_array = $form.serializeArray();
        var indexed_array = {};
    
        $.map(unindexed_array, function(n, i){
            indexed_array[n['name']] = n['value'];
        });
    
        return indexed_array;
    }


})(jQuery, gcGateway);


