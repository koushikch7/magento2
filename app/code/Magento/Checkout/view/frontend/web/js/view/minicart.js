define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'uiComponent',
    'mage/template'
], function ($, customerData, Component, mageTemplate) {
    'use strict';

    /**
     * Mini Cart UI component supporting multiple instances on the same page.
     */
    return Component.extend({
        defaults: {
            template: 'Magento_Checkout/minicart/content',
            minicartId: null
        },

        /**
         * Initialize component and bind instance specific events.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            var $element = $(this.element);
            this.minicartId = $element.attr('data-minicart-id') || 'default';
            this._initMiniCart();
            return this;
        },

        /**
         * Bind toggle and overlay click handlers for this minicart instance.
         */
        _initMiniCart: function () {
            var id = this.minicartId,
                $block = $(this.element),
                $toggle = $block.find('#minicart-toggle-' + id),
                $drawer = $block.find('#minicart-content-' + id),
                $overlay = $block.find('#minicart-overlay-' + id);

            // Toggle drawer visibility
            $toggle.on('click', function (e) {
                e.preventDefault();
                $drawer.toggleClass('active');
                $overlay.toggleClass('active');
            });

            // Close drawer when overlay is clicked
            $overlay.on('click', function () {
                $drawer.removeClass('active');
                $overlay.removeClass('active');
            });
        }
    });
});
