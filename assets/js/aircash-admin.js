window.aircashCheckConfiguration = function () {
    // Hide the "Connect with Aircash" button if account is approved
    if (jQuery('aircash-account-approved-icon').length) {
        jQuery('#aircash-connect').hide();
    }
}
jQuery(document).ready(function () {
    jQuery('body').on('submit', '#form-new-account', function (event) {
        let isValid = true;
        let aircashPartnerNameElement = jQuery('#woocommerce_aircash-woocommerce_aircash-partner-name');
        let aircashPartnerEmailElement = jQuery('#woocommerce_aircash-woocommerce_aircash-partner-email');
        let aircashPartnerPhoneElement = jQuery('#woocommerce_aircash-woocommerce_aircash-partner-phone');
        if (aircashPartnerPhoneElement.val() === '') {
            aircashPartnerPhoneElement.focus();
            isValid = false;
        }
        if (aircashPartnerEmailElement.val() === '') {
            aircashPartnerEmailElement.focus();
            isValid = false;
        }
        if (aircashPartnerNameElement.val() === '') {
            aircashPartnerNameElement.focus();
            isValid = false;
        }
        return isValid;
    });
    if (window.location.hash === '#aircash-new-account-request') {
        tb_show('Connect with Aircash', jQuery('#aircash-new-account-request-url').data('url'));
    }
});