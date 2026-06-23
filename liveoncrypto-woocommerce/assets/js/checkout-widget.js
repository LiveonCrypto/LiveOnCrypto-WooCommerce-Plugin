(function () {
	'use strict';

	var strings = window.LiveOnCryptoWCStrings || {};
	var EVENT_MESSAGES = {
		'payment:created': strings.paymentCreated || 'Payment session created. Follow the LiveOnCrypto instructions to complete payment.',
		'payment:detected': strings.paymentDetected || 'Payment detected. Waiting for network confirmation.',
		'payment:confirming': strings.paymentConfirming || 'Payment is confirming on the network.',
		'payment:paid': strings.paymentPaid || 'Payment received. We are finalizing your order.',
		'payment:expired': strings.paymentExpired || 'This payment session has expired. Refresh the page to start again.',
		'payment:error': strings.paymentError || 'LiveOnCrypto reported a payment error. Please try again or contact the store.',
	};

	function setStatus(panel, message) {
		var status = panel.querySelector('[data-liveoncrypto-wc-status]');
		if (status) {
			status.textContent = message;
		}
	}

	function parseConfig(panel) {
		var configNode = panel.querySelector('[data-liveoncrypto-wc-config]');
		if (!configNode) {
			return null;
		}

		try {
			return JSON.parse(configNode.textContent || '{}');
		} catch (error) {
			return null;
		}
	}

	function whenWidgetReady(callback, attempts) {
		var remainingAttempts = attempts || 80;

		if (window.LiveOnCryptoWidget) {
			callback(window.LiveOnCryptoWidget);
			return;
		}

		if (remainingAttempts <= 0) {
			callback(null);
			return;
		}

		window.setTimeout(function () {
			whenWidgetReady(callback, remainingAttempts - 1);
		}, 125);
	}

	function attachEventHandlers(widget, panel) {
		Object.keys(EVENT_MESSAGES).forEach(function (eventName) {
			if (widget && typeof widget.on === 'function') {
				widget.on(eventName, function () {
					setStatus(panel, EVENT_MESSAGES[eventName]);
				});
			}
		});
	}

	function createWidget(WidgetConstructor, config, panel) {
		if (WidgetConstructor && typeof WidgetConstructor.init === 'function') {
			return WidgetConstructor.init(config);
		}

		if (typeof WidgetConstructor === 'function') {
			return new WidgetConstructor(config);
		}

		if (WidgetConstructor && typeof WidgetConstructor.create === 'function') {
			return WidgetConstructor.create(config);
		}

		setStatus(panel, strings.initFailed || 'The LiveOnCrypto payment widget could not be initialized. Please refresh this page.');
		return null;
	}

	function openWidget(widget) {
		if (widget && typeof widget.open === 'function') {
			widget.open();
			return;
		}

		if (widget && typeof widget.render === 'function') {
			widget.render();
		}
	}

	function initPanel(panel) {
		var button = panel.querySelector('[data-liveoncrypto-wc-pay]');
		var config = parseConfig(panel);

		if (!button || !config) {
			setStatus(panel, strings.detailsUnavailable || 'LiveOnCrypto payment details are unavailable. Please contact the store.');
			return;
		}

		whenWidgetReady(function (WidgetConstructor) {
			var widget;

			if (!WidgetConstructor) {
				setStatus(panel, strings.widgetNotLoaded || 'The LiveOnCrypto payment widget did not load. Refresh this page or contact the store for assistance.');
				return;
			}

			widget = createWidget(WidgetConstructor, config, panel);
			if (!widget) {
				return;
			}

			attachEventHandlers(widget, panel);
			button.disabled = false;
			setStatus(panel, strings.widgetReady || 'LiveOnCrypto is ready. Click Pay with Crypto to continue.');

			button.addEventListener('click', function () {
				setStatus(panel, strings.openingWidget || 'Opening the LiveOnCrypto payment widget…');
				openWidget(widget);
			});
		});
	}

	window.LiveOnCryptoWCCheckout = {
		version: '0.1.0',
		init: function init() {
			document.querySelectorAll('[data-liveoncrypto-wc-widget]').forEach(initPanel);
		},
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', window.LiveOnCryptoWCCheckout.init);
	} else {
		window.LiveOnCryptoWCCheckout.init();
	}
}());
