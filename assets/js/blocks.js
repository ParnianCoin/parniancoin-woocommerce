/* Parnian Pay — Checkout block payment method (no build step needed). */
( function () {
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var getSetting = window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting;
	var el = window.wp && window.wp.element && window.wp.element.createElement;
	if ( ! registry || ! getSetting || ! el ) {
		return;
	}
	var decode = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ) || function ( s ) { return s; };
	var s = getSetting( 'parnian_pay_data', {} );
	var title = decode( s.title || 'ParnianCoin (PARC)' );

	var Label = function () {
		return el(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
			s.icon ? el( 'img', { src: s.icon, alt: '', width: 24, height: 24 } ) : null,
			el( 'span', null, title )
		);
	};
	var Content = function () {
		return s.description ? el( 'div', null, decode( s.description ) ) : null;
	};

	registry.registerPaymentMethod( {
		name: 'parnian_pay',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		supports: { features: s.supports || [ 'products' ] },
	} );
} )();
