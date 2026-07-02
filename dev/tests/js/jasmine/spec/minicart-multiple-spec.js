define([
    'jquery',
    'Magento_Checkout/js/view/minicart'
], function ($, MiniCart) {
    'use strict';

    describe('Magento_Checkout/js/view/minicart multiple instances', function () {
        var $container1, $container2;

        beforeEach(function () {
            // First minicart instance
            $container1 = $(
                '<div data-block="minicart" data-minicart-id="first"></div>'
            ).append(
                '<button id="minicart-toggle-first" data-action="toggle-minicart">Toggle</button>' +
                '<div id="minicart-content-first" class="minicart-content"></div>' +
                '<div id="minicart-overlay-first" class="minicart-overlay"></div>'
            ).appendTo('body');

            // Second minicart instance
            $container2 = $(
                '<div data-block="minicart" data-minicart-id="second"></div>'
            ).append(
                '<button id="minicart-toggle-second" data-action="toggle-minicart">Toggle</button>' +
                '<div id="minicart-content-second" class="minicart-content"></div>' +
                '<div id="minicart-overlay-second" class="minicart-overlay"></div>'
            ).appendTo('body');

            // Initialise the UI component for each instance
            new MiniCart({ element: $container1[0] });
            new MiniCart({ element: $container2[0] });
        });

        afterEach(function () {
            $container1.remove();
            $container2.remove();
        });

        it('opens the first minicart without affecting the second', function () {
            $('#minicart-toggle-first').click();
            expect($('#minicart-content-first')).toHaveClass('active');
            expect($('#minicart-content-second')).not.toHaveClass('active');
        });

        it('opens the second minicart without affecting the first', function () {
            $('#minicart-toggle-second').click();
            expect($('#minicart-content-second')).toHaveClass('active');
            expect($('#minicart-content-first')).not.toHaveClass('active');
        });
    });
});
