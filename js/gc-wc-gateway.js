(function($) {

    $(document).ready(function() {

        // CHECKOUT SUBMIT BUTTON CLICK
        let submitBtn     =  $( 'form.checkout [type="submit"]' );
        let paymentMethod = $( 'form.checkout input[name="payment_method"]:checked' );

        $( submitBtn ).on( 'click', function(e) {
            var payment_method = $( paymentMethod ).val();

            if (payment_method == 'gc_ob_wc_gateway') {

                e.preventDefault();
                e.stopPropagation();
 
                // disable btn
                $(submitBtn).prop('disabled', false);
                
                console.log('gcob place order init');
                initGCFlow();
            }
        });

    });


    // INIT GOCARDLESS FLOW

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

        var requiredFields = getRequiredCheckoutFields(checkoutFields);
        formdata.append('required_fields', JSON.stringify(requiredFields));

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

        // NO VALIDATION ISSUES, REMOVE PRE-EXISTING WC ERRORS FROM DOM
        displayWoocomErrors(null);

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

        // TODO: disable checkout form & btn for failsafe
        checkoutFormOverlay();
        
        // assemble form data
        var formdata = new FormData();
        formdata.append('action', 'gcobSubmitCheckout');
        formdata.append('security', gcGateway.security)

        var customerID = billingRequest.resources.customer.id;
        var paymentRef = billingRequest.links.payment_request;
        var paymentID  = billingRequest.links.payment_request_payment;
        formdata.append('gc_ob_customer_id', customerID);
        formdata.append('gc_ob_payment_ref', customerID);
        formdata.append('gc_ob_payment_id', customerID);

        var checkoutFields = getFormData($( 'form.checkout' ));
        formdata.append('checkout_fields', JSON.stringify(checkoutFields));

        ajaxSubmitCheckout();
    }


    // PAYMENT WINDOW CLOSED OR ERROR

    function paymentFlowError(error, metadata) {
        console.log('we had a issue!');
        console.log('error: ' + JSON.stringify(error));
        console.log('metadata: ' + JSON.stringify(metadata));

        displayWoocomErrors('We have not processed your payment: ' + error);

        gatewayFlowAlreadyStarted = false;
        return false;

    }


    // AJAX SUBMIT CHECKOUT TO ORDER CREATE HANDLER

    function ajaxSubmitCheckout(formdata) {

        $.ajax({
            type: 'POST',
            url: gcGateway.ajax_url,
            contentType: false,
            processData: false,
            data: formdata,
            success: function(data) {
                // checkout submit errors
                if (data.error) {
                    console.log('Checkout submit error: ' + data.error);

                    // TODO: display error to user (still redirect to order recieved?)
                }
                // success -> redirect to order recieved
                else {
                    console.log('Success, redirecting to order recieved: ' + data.redirect);
                }
            },
            error: function(data) {
                // checkout submit ajax error
                console.log('Checkout submit ajax error: ' + data);
            }
        });

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

        if (errors == null) {
            parent.empty();
            return;
        }

        errors.forEach(function(error) {
            parent.append('<li>' + error + '</li>');
            console.log(error);
        });

    }


    // GET FORM DATA

    function getFormData($form) {
        var unindexed_array = $form.serializeArray();
        var indexed_array = {};
    
        $.map(unindexed_array, function(n, i){
            indexed_array[n['name']] = n['value'];
        });
    
        return indexed_array;
    }


    // GET REQUIRED CHECKOUT FIELDS

    function getRequiredCheckoutFields(fields) {

        var requiredFields = [];

        Object.keys(fields).forEach(function(key) {
            var parentRow = $('#' + key).closest('p');

            if ($(parentRow).hasClass('validate-required')) {
                requiredFields.push(key);
            }
        });

        return requiredFields;
    }


    // CHECKOUT FORM OVERLAY

    function checkoutFormOverlay() {

        $('form.checkout').append('<div id="gcob_overlay" style="position: absolute; top: 0px; left: 0px; background: rgba(0,0,0,0.3); width: 100%; height: 100%; display: flex; align-items: center;"></div>');
        $('#gcob_overlay').append('<p style="width: 100%; text-align: center;">Processing your order...</p>')

    }


})(jQuery, gcGateway);


