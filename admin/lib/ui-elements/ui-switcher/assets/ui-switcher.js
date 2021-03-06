/**
 * Switcher
 */
(function($){
	"use strict";

	CHERRY_API.utilites.namespace('ui_elements.switcher');
	CHERRY_API.ui_elements.switcher = {
		init: function ( target ) {
			var self = this;
			if( CHERRY_API.status.document_ready ){
				self.render( target );
			}else{
				CHERRY_API.variable.$document.on('ready', self.render( target ) );
			}
		},
		render: function ( target ) {

			jQuery('.cherry-switcher-wrap', target).each(function(){
				var
					input = jQuery('.cherry-input-switcher', this)
				,	inputValue = ( input.val() === "true" )
				;

				if( !inputValue ){
					jQuery('.sw-enable', this).removeClass('selected');
					jQuery('.sw-disable', this).addClass('selected');

				}else{
					jQuery('.sw-enable', this).addClass('selected');
					jQuery('.sw-disable', this).removeClass('selected');

				}
			});

			jQuery('.cherry-switcher-wrap', target).on('click', function () {
				var
					input = jQuery('.cherry-input-switcher', this)
				,	inputValue = ( input.val() === "true" )
				,	true_slave = ( typeof input.data('true-slave') != 'undefined' ) ? input.data('true-slave') : null
				,	false_slave = ( typeof input.data('false-slave') != 'undefined' ) ? input.data('false-slave') : null
				;

				if( !inputValue ){
					jQuery('.sw-enable', this).addClass('selected');
					jQuery('.sw-disable', this).removeClass('selected');
					input.attr('value', true );

					input.trigger('switcher_enabled_event', [true_slave, false_slave]);
				}else{
					jQuery('.sw-disable', this).addClass('selected');
					jQuery('.sw-enable', this).removeClass('selected');
					input.attr('value', false );

					input.trigger('switcher_disabled_event', [true_slave, false_slave]);
				}
			})
		}
	};
	CHERRY_API.ui_elements.switcher.init( jQuery('body') );
}(jQuery));
