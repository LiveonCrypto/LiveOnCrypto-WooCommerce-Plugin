(function () {
	'use strict';

	var blocksRegistry = window.wc && window.wc.wcBlocksRegistry;
	var settingsApi = window.wc && window.wc.wcSettings;
	var element = window.wp && window.wp.element;
	var htmlEntities = window.wp && window.wp.htmlEntities;
	var i18n = window.wp && window.wp.i18n;

	if (!blocksRegistry || !settingsApi || !element) {
		return;
	}

	var settings = settingsApi.getSetting('liveoncrypto_data', {});
	var decodeEntities = htmlEntities && htmlEntities.decodeEntities ? htmlEntities.decodeEntities : function (value) {
		return value;
	};
	var __ = i18n && i18n.__ ? i18n.__ : function (value) {
		return value;
	};
	var title = decodeEntities(settings.title || __('Pay with Crypto', 'liveoncrypto-woocommerce'));
	var description = decodeEntities(settings.description || __('Pay securely with cryptocurrency through LiveOnCrypto.', 'liveoncrypto-woocommerce'));

	function Content() {
		return element.createElement('p', null, description);
	}

	blocksRegistry.registerPaymentMethod({
		name: 'liveoncrypto',
		label: title,
		ariaLabel: title,
		content: element.createElement(Content),
		edit: element.createElement(Content),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || ['products']
		}
	});
}());
