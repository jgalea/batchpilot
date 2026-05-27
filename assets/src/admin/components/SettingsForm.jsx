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

const Section = ( { title, description, children } ) => (
	<section className="co-settings__section">
		<header className="co-settings__section-header">
			<h2 className="co-settings__section-title">{ title }</h2>
			{ description && (
				<p className="co-settings__section-description">
					{ description }
				</p>
			) }
		</header>
		<div className="co-settings__fields">{ children }</div>
	</section>
);

const Field = ( { label, help, children } ) => (
	<div className="co-settings__field">
		<div className="co-settings__field-text">
			<span className="co-settings__field-label">{ label }</span>
			{ help && (
				<span className="co-settings__field-help">{ help }</span>
			) }
		</div>
		<div className="co-settings__field-input">{ children }</div>
	</div>
);

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
		return (
			<div className="co-settings co-settings--loading">
				<Spinner />
				<span className="co-settings__loading-text">
					{ __( 'Loading settings…', 'content-ops' ) }
				</span>
			</div>
		);
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
		<div className="co-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					className="co-settings__notice"
				>
					{ notice.text }
				</Notice>
			) }

			<Section
				title={ __( 'Execution', 'content-ops' ) }
				description={ __(
					'Control when Content Ops flips from synchronous runs to background processing via Action Scheduler.',
					'content-ops'
				) }
			>
				<Field
					label={ __( 'Async threshold', 'content-ops' ) }
					help={ __(
						'Operations matching at least this many items run in the background. Lower = safer on shared hosts.',
						'content-ops'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Async threshold', 'content-ops' ) }
						type="number"
						min={ 1 }
						value={ String( values.async_threshold ) }
						onChange={ ( v ) =>
							update( {
								async_threshold: parseInt( v, 10 ) || 0,
							} )
						}
					/>
				</Field>

				<Field
					label={ __( 'Batch size', 'content-ops' ) }
					help={ __(
						'How many items Content Ops processes per batch. Keep low if your host is slow or memory-bound.',
						'content-ops'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Batch size', 'content-ops' ) }
						type="number"
						min={ 1 }
						value={ String( values.batch_size ) }
						onChange={ ( v ) =>
							update( { batch_size: parseInt( v, 10 ) || 0 } )
						}
					/>
				</Field>
			</Section>

			<Section
				title={ __( 'Delete behaviour', 'content-ops' ) }
				description={ __(
					'Defaults applied when running a Delete operation. You can always override per run.',
					'content-ops'
				) }
			>
				<Field
					label={ __( 'Permanent delete by default', 'content-ops' ) }
					help={ __(
						'When on, Delete skips the Trash and removes items immediately. Undo will not be available.',
						'content-ops'
					) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							values.delete_permanent_default
								? __( 'Skip trash', 'content-ops' )
								: __( 'Move to trash', 'content-ops' )
						}
						checked={ !! values.delete_permanent_default }
						onChange={ ( v ) =>
							update( { delete_permanent_default: v } )
						}
					/>
				</Field>
			</Section>

			<Section
				title={ __( 'History retention', 'content-ops' ) }
				description={ __(
					'How long Content Ops keeps operation records, snapshots, and undo data.',
					'content-ops'
				) }
			>
				<Field
					label={ __( 'Retention window', 'content-ops' ) }
					help={ __(
						'Days to keep completed operations. Older entries (and their undo snapshots) are pruned on a daily cron.',
						'content-ops'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Retention (days)', 'content-ops' ) }
						type="number"
						min={ 1 }
						value={ String( values.history_retention_days ) }
						onChange={ ( v ) =>
							update( {
								history_retention_days: parseInt( v, 10 ) || 0,
							} )
						}
					/>
				</Field>
			</Section>

			<div className="co-settings__actions">
				<Button
					variant="primary"
					isBusy={ saving }
					onClick={ save }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'content-ops' )
						: __( 'Save settings', 'content-ops' ) }
				</Button>
			</div>
		</div>
	);
};

export default SettingsForm;
