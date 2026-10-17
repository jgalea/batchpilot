( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const __ = wp.i18n.__;
	const el = wp.element.createElement;
	const link = function ( href, label ) {
		return el(
			'div',
			{ className: 'batchpilot-copy-action' },
			el( 'a', { href }, label )
		);
	};

	wp.plugins.registerPlugin( 'batchpilot-copy-actions', {
		render() {
			const items = [];
			if ( data.duplicateUrl ) {
				items.push(
					link(
						data.duplicateUrl,
						__( 'Copy to a new draft', 'batchpilot' )
					)
				);
			}
			if ( data.rewriteUrl ) {
				items.push(
					link(
						data.rewriteUrl,
						__( 'Rewrite & Republish', 'batchpilot' )
					)
				);
			}
			if ( data.rewriteCopyUrl ) {
				items.push(
					link(
						data.rewriteCopyUrl,
						__( 'Edit the rewrite', 'batchpilot' )
					)
				);
			}
			if ( ! items.length ) {
				return null;
			}
			return el(
				wp.editor.PluginPostStatusInfo,
				{ className: 'batchpilot-copy-actions' },
				el( 'div', null, items )
			);
		},
	} );

	wp.domReady( function () {
		const notices = wp.data.dispatch( 'core/notices' );
		if ( data.originalEditUrl ) {
			notices.createNotice(
				'info',
				wp.i18n.sprintf(
					/* translators: %s: title of the original post. */
					__(
						'This is a Rewrite & Republish copy of "%s". Publishing or scheduling it replaces the content of the original, which keeps its URL.',
						'batchpilot'
					),
					data.originalTitle
				),
				{
					isDismissible: false,
					actions: [
						{
							label: __( 'View original', 'batchpilot' ),
							url: data.originalEditUrl,
						},
					],
				}
			);
		}
		if ( data.error ) {
			notices.createNotice( 'error', data.error, {
				isDismissible: false,
			} );
		}
		if ( data.rewriteCopyUrl ) {
			notices.createNotice(
				'warning',
				__(
					'A Rewrite & Republish copy of this post exists.',
					'batchpilot'
				),
				{
					isDismissible: false,
					actions: [
						{
							label: __( 'Edit the rewrite', 'batchpilot' ),
							url: data.rewriteCopyUrl,
						},
					],
				}
			);
		}
	} );

	if ( data.originalEditUrl ) {
		let wasSaving = false;
		wp.data.subscribe( function () {
			const editor = wp.data.select( 'core/editor' );
			const saving = editor.isSavingPost() && ! editor.isAutosavingPost();
			if (
				wasSaving &&
				! saving &&
				! editor.didPostSaveRequestFail() &&
				editor.getCurrentPostAttribute( 'status' ) === 'publish'
			) {
				window.location.assign( data.originalEditUrl );
			}
			wasSaving = saving;
		} );
	}
} )( window.wp, window.batchPilotCopy );
