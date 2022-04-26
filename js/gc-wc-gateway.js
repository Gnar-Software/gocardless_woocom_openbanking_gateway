(function($) {

    $(document).ready(function() {

        $('form[name="checkout"]').submit(function(event) {
            console.log('form was submitted');

            // CHECK GC GATEWAY IS SELECTED

            // STOP DEFAULT BEHAVIOUR & DISABLE BUTTON
            event.preventDefault();
            // disable btn here todo

            // CHECK FORM IS VALIDATED
            

            // TRIGGER SERVER BILLING REQUEST
            var formdata = new FormData();
            formdata.append('action', 'initBillingRequest');
            ajaxTriggerBillingRequest(formdata);

        });

    });


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

        // BAIL IF ERRORS
        if (responseObj.status == 'error') {
            console.log('error: ' + responseObj.error);
            return;
        }

        console.log(responseObj.BR_Flow_ID);
        console.log(responseObj.mode);

        if (!responseObj.BR_Flow_ID || !responseObj.mode) {
            console.log('error: server response object does not contain flow ID or mode');
            return;
        }


        // CREATE HANDLER
        const handler = GoCardlessDropin.create({
            billingRequestFlowID: responseObj.BR_Flow_ID,
            environment: responseObj.mode,
            onSuccess: (billingRequest, billingRequestFlow) => {
                console.log('we had a success!');
            },
            onExit: (error, metadata) => {
                console.log('we had a fail!');
            },
        });


        // OPEN DROPIN
        handler.open();

    }


    // BILLING REQUEST SETUP ERROR

    function billingRequestSetupError(response) {
        console.log('ajax error: ' + response);

    }


})(jQuery, gcGateway);


