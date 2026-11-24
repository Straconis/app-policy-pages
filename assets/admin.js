/* App Policy Pages: icon and screenshot pickers, and the copyable tester link. */
( function ( $ ) {
	'use strict';
	var l10n = window.appPolicyPages || {};

	$( document ).on( 'click', '.app-policy-media .app-policy-pick', function ( e ) {
		e.preventDefault();
		var $cell = $( this ).closest( '.app-policy-media' );
		var multiple = $cell.data( 'multiple' ) === 1 || $cell.data( 'multiple' ) === '1';
		var frame = wp.media( {
			title: multiple ? l10n.chooseShots : l10n.chooseIcon,
			button: { text: l10n.use },
			library: { type: 'image' },
			multiple: multiple ? 'add' : false
		} );
		frame.on( 'open', function () {
			var ids = String( $cell.find( 'input[type=hidden]' ).val() || '' ).split( ',' ).filter( Boolean );
			var selection = frame.state().get( 'selection' );
			ids.forEach( function ( id ) {
				var att = wp.media.attachment( id );
				att.fetch();
				selection.add( att );
			} );
		} );
		frame.on( 'select', function () {
			var chosen = frame.state().get( 'selection' ).toJSON();
			var $previews = $cell.find( '.app-policy-previews' ).empty();
			var ids = chosen.map( function ( att ) {
				var src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
				$( '<img>' ).attr( { src: src, alt: '' } ).appendTo( $previews );
				return att.id;
			} );
			$cell.find( 'input[type=hidden]' ).val( ids.join( ',' ) );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.app-policy-media .app-policy-clear', function ( e ) {
		e.preventDefault();
		var $cell = $( this ).closest( '.app-policy-media' );
		$cell.find( 'input[type=hidden]' ).val( '' );
		$cell.find( '.app-policy-previews' ).empty();
	} );

	$( document ).on( 'focus click', '.app-policy-copy', function () {
		this.select();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( this.value ).catch( function () {} );
		}
	} );
}( jQuery ) );
