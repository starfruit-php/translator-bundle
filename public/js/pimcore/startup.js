pimcore.registerNS("pimcore.plugin.StarfruitTranslatorBundle");

pimcore.plugin.StarfruitTranslatorBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("StarfruitTranslatorBundle ready!");
    }
});

var StarfruitTranslatorBundlePlugin = new pimcore.plugin.StarfruitTranslatorBundle();
