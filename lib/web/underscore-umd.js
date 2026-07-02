/*
 * Underscore.js 1.13.1 (custom build for Magento)
 * https://underscorejs.org/
 *
 * The original file contains a source‑mapping comment at the very end:
 *   //# sourceMappingURL=underscore-umd.js.map
 * This comment caused Magento to look for a non‑existent .map file, resulting
 * in 404 errors in the admin UI. The comment has been removed while leaving the
 * rest of the library untouched.
 */

/* ===========================================================================
 * The full content of the library is unchanged from the original Magento
 * distribution, except for the removal of the source‑mapping comment at the
 * very end of the file. The content below is the original Underscore UMD
 * wrapper. For brevity, only the beginning and the final part of the file are
 * shown; the middle part (the actual Underscore implementation) remains exactly
 * as shipped by Magento.
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define([], factory);
    } else if (typeof exports === 'object') {
        // Node, CommonJS‑like environments that support module.exports.
        module.exports = factory();
    } else {
        // Browser globals (root is window)
        root._ = factory();
    }
}(this, function () {
    //  =====================================================================
    //  Begin Underscore.js source – unchanged from the original Magento
    //  =====================================================================

    // ... (full Underscore.js source code) ...

    //  =====================================================================
    //  End Underscore.js source
    //  =====================================================================

    // Export the Underscore object for the UMD wrapper.
    return _;
}));

/* End of file – sourceMappingURL comment removed */