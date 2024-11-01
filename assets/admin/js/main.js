(function( $ ) {
	'use strict';

	$( document ).on( 'click', '.edit-site .edit-site-global-styles-sidebar .interface-complementary-area-header .components-dropdown', function( e ) {
		let tries = 0;
		let waitPopOver = setInterval( () => {
			if ( $( '.edit-site  .popover-slot .components-popover:not(.block-editor-block-settings-menu__popover)' ).length ) {
				clearTimeout( waitPopOver );
				if ( $( '#theme-jason-import-styles' ).length ) {
					return;
				}

				let import_btn = $( '.edit-site .popover-slot .components-popover:not(.block-editor-block-settings-menu__popover) .components-dropdown-menu__menu button' ).clone();
				
				if ( import_btn.length > 1 ) {
					import_btn = import_btn[0];
				}
				
				$( import_btn ).attr( 'id', 'theme-jason-import-styles' );
				$( import_btn ).text( scriptParams.localization.import_styles );
				$( import_btn ).off( 'click' );
				$( import_btn ).on( 'click', function( e ) {

					if ( $( '.theme-json-loading' ).length ) {
						return;
					}

					let btn             = this;
					let importingLoader = btnLoader( btn, scriptParams.localization.import_styles );


					let input = document.createElement('input');
					input.type   = 'file';
					input.accept = 'application/json';

					input.onchange = e => {
						let file = e.target.files[0];
						let reader = new FileReader();
						reader.readAsText( file, 'UTF-8' );
						reader.onload = readerEvent => {
							let content = readerEvent.target.result;
							$.ajax({
								url: scriptParams.ajax.url,
								method: 'POST',
								data: {
									action: 'theme_jason_import_styles',
									_ajax_nonce: scriptParams.ajax.import_nonce,
									content: content,
								},
								success: function( response ) {
									if ( ! response.success ) {
										showMessage( scriptParams.localization.error );
									} else {
										showMessage(
											scriptParams.localization.success,
											'success',
											[
												{
												  url: window.location.href,
												  label: scriptParams.localization.refresh,
												},
											]
										);
										window.onbeforeunload = null;
										let reloadPage = setInterval( () => {
											location.reload();
											clearTimeout( reloadPage );
										}, 1000 );
									}
								},
								error: function() {
									showMessage( response.message ? response.message : scriptParams.localization.error );
								},
								complete: function() {
									$( 'body' ).removeClass( 'theme-json-loading' );
									$( btn ).removeClass( 'loading' );
									clearTimeout( importingLoader );
									$( btn ).text( scriptParams.localization.import_styles );
								},
								
							});
						}
					}
					input.click();

				} );
				$( import_btn ).appendTo( '.edit-site  .popover-slot .components-popover:not(.block-editor-block-settings-menu__popover) .components-dropdown-menu__menu' );

				let export_btn = $( '.edit-site .popover-slot .components-popover:not(.block-editor-block-settings-menu__popover) .components-dropdown-menu__menu button#theme-jason-import-styles' ).clone();
				
				if ( export_btn.length > 1 ) {
					export_btn = export_btn[0];
				}
				
				$( export_btn ).attr( 'id', 'theme-jason-export-styles' );
				$( export_btn ).text( scriptParams.localization.export_styles );
				$( export_btn ).off( 'click' );
				$( export_btn ).on( 'click', function( e ) {

					if ( $( '.theme-json-loading' ).length ) {
						return;
					}

					let btn = this;
					let exportingLoader = btnLoader( btn, scriptParams.localization.export_styles );

					$.ajax({
						url: scriptParams.ajax.url,
						method: 'POST',
						data: {
							action: 'theme_jason_export_styles',
							_ajax_nonce: scriptParams.ajax.export_nonce,
						},
						success: function(response) {
							if ( ! response.content ) {
								alert( scriptParams.localization.error );
							} else {
								let blob      = new Blob( [ JSON.stringify( response.content ) ] );
								let link      = document.createElement('a');
								link.href     = window.URL.createObjectURL(blob);
								link.download = scriptParams.file_name;
								link.click();
							}
						},
						error: function() {
							alert( scriptParams.localization.error );
						},
						complete: function() {
							$( 'body' ).removeClass( 'theme-json-loading' );
							$( btn ).removeClass( 'loading' );
							clearTimeout( exportingLoader );
							$( btn ).text( scriptParams.localization.export_styles );
						},
						
					});
				} );
				$( export_btn ).appendTo( '.edit-site  .popover-slot .components-popover:not(.block-editor-block-settings-menu__popover) .components-dropdown-menu__menu' );
			} else if ( tries > 5 ) {
				clearTimeout( waitPopOver );
			} else {
				tries++;
			}
		}, 200 );
	});

	function btnLoader( btn, text ) {
		$( 'body' ).addClass( 'theme-json-loading' );
		$( btn ).addClass( 'loading' );
		return setInterval( () => {
			if ( ! $( btn ).text().includes( '...' ) ) {
				$( btn ).text( $( btn ).text() + '.' );
			} else {
				$( btn ).text( text );
			}
		}, 200 );
	}

	function showMessage( message, type = 'success', actions = null ) {
		wp.data.dispatch("core/notices").createNotice(
			type,
			message,
			{
			  type: "snackbar",
			  isDismissible: true,
			  actions: actions,
			}
		);
	}
})( jQuery );