(function($) {

    /**
     * DOM READY
     */
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
                $(submitBtn).prop('disabled', true);
                
                console.log('gcob place order init');
                sendNotice('Submit button clicked');

                initGCFlow();
            }
        });


        // IS CHECKOUT PAGE
        if (gcGateway.is_checkout) {
            console.log('is checkout');
            sendNotice('Reached checkout');
        }

        // IS ORDER RECEIVED PAGE
        if (gcGateway.is_order_recieved) {
            console.log('is order recieved');
            sendNotice('Reached order recieved page');
        }

        // SEND ERRORS TO SERVER
        window.addEventListener('error', (event) => {
            var error = event.type + ' ' + event.message + ' ' + event.filename + ' ' + event.lineno;
            sendError(error);
        });

    });


    /**
     * INIT GOCARDLESS FLOW
     */
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


    /**
     * AJAX TRIGGER BILLING REQUEST
     * 
     * @param {*} formdata 
     */
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


    /**
     * TRIGGER GC MODAL
     * 
     * @param {*} response 
     * @returns 
     */
    function triggerGCModal(response) {
        console.log('BR setup response: ' + response);
        var responseObj = JSON.parse(response);
        sendNotice('Billing request response / server side checkout validation: ' + responseObj);

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

        // BAIL IF SERVERSIDE CHECKOUT VALIDATION ERRORS
        if (responseObj.validation_error) {
            console.log('validation error: ' + responseObj.validation_error);
            displayWoocomErrors(responseObj.validation_error);
            gatewayFlowAlreadyStarted = false;
            return;
        }

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

        sendNotice('Opening GC modal');

        // OPEN DROPIN
        handler.open();

    }


    /**
     * PAYMENT FLOW COMPLETE
     * 
     * @param {*} billingRequest 
     * @param {*} billingRequestFlow 
     */
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

        sendNotice('GC payment flow complete - submitting checkout form');
        
        $('form.checkout').submit();
    }


    /**
     * PAYMENT WINDOW CLOSED OR ERROR
     * 
     * @param {*} error 
     * @param {*} metadata 
     * @returns 
     */
    function paymentFlowError(error, metadata) {

        console.log('error: ' + JSON.stringify(error));
        displayWoocomErrors('Sorry we have not been able to process your payment: ' + error);
        sendError('Payment flow error: ' + JSON.stringify(error));
        
        // re-enable btn
        $(submitBtn).prop('disabled', false);

        return;
    }


    /**
     * BILLING REQUEST SETUP ERROR
     * 
     * @param {*} response
     */
    function billingRequestSetupError(response) {
        console.log('ajax error: ' + JSON.stringify(response));
        sendError('Billing request setup error: ' + JSON.stringify(response));
    }


    /**
     * DISPLAY WOOCOM VALIDATION ERRORS
     * 
     * @param {*} errors 
     * @returns 
     */
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


    /**
     * GET FORM DATA
     * 
     * @param {*} $form 
     * @returns 
     */
    function getFormData($form) {
        var unindexed_array = $form.serializeArray();
        var indexed_array = {};
    
        $.map(unindexed_array, function(n, i){
            indexed_array[n['name']] = n['value'];
        });
    
        return indexed_array;
    }


    /**
     * GET REQUIRED CHECKOUT FIELDS
     * 
     * @param {*} fields 
     * @returns 
     */
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


    /**
     * SEND ERRORS TO SERVER AJAX
     * 
     * @param {string} error
     */
    function sendError(error) {

        if (!gcGateway.front_end_logging) {
            return;
        }

        var errorFormData = new FormData();
        errorFormData.append('action', 'clientSideErrorLog');
        errorFormData.append('security', gcGateway.security);
        errorFormData.append('error', error);
        
        $.ajax({
            type: 'POST',
            url: gcGateway.ajax_url,
            contentType: false,
            processData: false,
            data: errorFormData,
            success: function(data) {
                console.log('logged error');
            },
            error: function(data) {
                console.log('logger did not work');
            }
        });

    }


    /**
     * SEND NOTICES TO SERVER AJAX
     * 
     * @param {string} notice
     */
    function sendNotice(notice) {

        if (!gcGateway.front_end_logging) {
            return;
        }

        var errorFormData = new FormData();
        errorFormData.append('action', 'clientSideErrorLog');
        errorFormData.append('security', gcGateway.security);
        errorFormData.append('notice', notice);
        
        $.ajax({
            type: 'POST',
            url: gcGateway.ajax_url,
            contentType: false,
            processData: false,
            data: errorFormData,
            success: function(data) {
                console.log('logged notice');
            },
            error: function(data) {
                console.log('logger did not work');
            }
        });

    }


})(jQuery, gcGateway);


