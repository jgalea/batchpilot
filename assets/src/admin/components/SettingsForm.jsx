import { useEffect, useState } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeError } from '../api';

const SettingsForm = ( { api } ) => {
	const [ values, setValues ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		api.getSettings()
			.then( setValues )
			.catch( ( e ) =>
				setNotice( {
					status: 'error',
					text: normalizeError( e ).message,
				} )
			);
	}, [ api ] );

	if ( ! values ) {
		return <Spinner />;
	}

	const update = ( patch ) =>
		setValues( ( prev ) => ( { ...prev, ...patch } ) );

	const save = async () => {
		setSaving( true );
		try {
			const next = await api.saveSettings( values );
			setValues( next );
			setNotice( {
				status: 'success',
				text: __( 'Settings saved.', 'content-ops' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				text: normalizeError( err ).message,
			} );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="content-ops-settings-form">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<h2>{ __( 'General', 'content-ops' ) }</h2>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Async threshold', 'content-ops' ) }
				type="number"
				value={ String( values.async_threshold ) }
				onChange={ ( v ) =>
					update( { async_threshold: parseInt( v, 10 ) || 0 } )
				}
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Batch size', 'content-ops' ) }
				type="number"
				value={ String( values.batch_size ) }
				onChange={ ( v ) =>
					update( { batch_size: parseInt( v, 10 ) || 0 } )
				}
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Default to permanent delete (skip trash)',
					'content-ops'
				) }
				checked={ !! values.delete_permanent_default }
				onChange={ ( v ) => update( { delete_permanent_default: v } ) }
			/>

			<h2>{ __( 'History', 'content-ops' ) }</h2>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Retention (days)', 'content-ops' ) }
				type="number"
				value={ String( values.history_retention_days ) }
				onChange={ ( v ) =>
					update( {
						history_retention_days: parseInt( v, 10 ) || 0,
					} )
				}
			/>

			<Button variant="primary" isBusy={ saving } onClick={ save }>
				{ __( 'Save settings', 'content-ops' ) }
			</Button>
		</div>
	);
};

export default SettingsForm;
