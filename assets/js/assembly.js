jQuery(function($) {
    'use strict';

    // onject to handle Assmebly payment form
    var se_assembly_form = {
        init: function() {
            if ($('form.woocommerce-checkout').length) {
                this.form = $('form.woocommerce-checkout');
            }

            promisepay.configure()

            $('form.woocommerce-checkout').on('submit', this.onSubmit);
        },

        block: function() {
            se_assembly_form.form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },

        isAssemblyChosen: function() {
            return $( '#payment_method_assembly' ).is( ':checked' );
        },

        unblock: function() {
            se_assembly_form.form.unblock();
        },

        onSubmit: function(e) {
            if (se_assembly_form.isAssemblyChosen()) {
                e.preventDefault();
                // se_assembly_form.block(); // block it !!!!!!

                var card = $('#assembly-card-number').val().replace(/ /g,'');
                var expires = $('#assembly-card-expiry').val();
                var cvc =  $('#assembly-card-cvc').val();
                var name = $('#assembly-card-name').val();

                promisepay.createCardAccount("CARD_TOKEN", {
                    full_name: name,
                    number: card,
                    expiry_month: "02",
                    expiry_year: "2018",
                    cvv: "123"
                }, function(data) {
                    console.log(data);
                }, function(data) {
                    console.log(data);
                });
            }
        }
    };

    // se_assembly_form.init();
});