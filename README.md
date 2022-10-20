# Instant Bank Payments via GoCardless for WooCommerce

GoCardless open banking payment gateway for Woocommerce. Uses GC's open banking billing request flow and JS dropin.

Requires: PHP 7.4, Wordpress 5.8

Tested: PHP 7.4, Wordpress 6.0

# SCHEDULED

- Woocommerce subscription support
- Refund support
- BUG: Rounding issue - price sent to GC cannot be parsed correctly if it's not 2 decimal places


# ACTIONS / FILTERS / HOOKS

// checkout post validation hook -> prevent GC flow start
- filter -> checkout_submitted_pre_gc_flow
    - arguments =   $errorMessages (array)  // add error message to array to prevent start of GC flow
                    $checkoutFields (json)  // unsanitized object of checkout field key values
                    $requiredFields (json)  // unsanitized array of required checkout field keys