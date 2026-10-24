import { useEffect, useState } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	CheckboxControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { normalizeError } from '../api';

const Section = ( { title, description, children } ) => (
	<section className="bp-settings__section">
		<header className="bp-settings__section-header">
			<h2 className="bp-settings__section-title">{ title }</h2>
			{ description && (
				<p className="bp-settings__section-description">
					{ description }
				</p>
			) }
		</header>
		<div className="bp-settings__fields">{ children }</div>
	</section>
);

const Field = ( { label, help, children } ) => (
	<div className="bp-settings__field">
		<div className="bp-settings__field-text">
			<span className="bp-settings__field-label">{ label }</span>
			{ help && (
				<span className="bp-settings__field-help">{ help }</span>
			) }
		</div>
		<div className="bp-settings__field-input">{ children }</div>
	</div>
);

const toggleValue = ( list, value, on ) => {
	const next = ( list || [] ).filter( ( v ) => v !== value );
	return on ? [ ...next, value ] : next;
};

const SettingsForm = ( { api, bootstrap = {} } ) => {
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
			<div className="bp-settings bp-settings--loading">
				<Spinner />
				<span className="bp-settings__loading-text">
					{ __( 'Loading settings…', 'batchpilot' ) }
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
				text: __( 'Settings saved.', 'batchpilot' ),
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
		<div className="bp-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					className="bp-settings__notice"
				>
					{ notice.text }
				</Notice>
			) }

			<Section
				title={ __( 'Execution', 'batchpilot' ) }
				description={ __(
					'Control when BatchPilot flips from synchronous runs to background processing via Action Scheduler.',
					'batchpilot'
				) }
			>
				<Field
					label={ __( 'Async threshold', 'batchpilot' ) }
					help={ __(
						'Operations matching at least this many items run in the background. Lower = safer on shared hosts.',
						'batchpilot'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Async threshold', 'batchpilot' ) }
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
					label={ __( 'Batch size', 'batchpilot' ) }
					help={ __(
						'How many items BatchPilot processes per batch. Keep low if your host is slow or memory-bound.',
						'batchpilot'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Batch size', 'batchpilot' ) }
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
				title={ __( 'Delete behaviour', 'batchpilot' ) }
				description={ __(
					'Defaults applied when running a Delete operation. You can always override per run.',
					'batchpilot'
				) }
			>
				<Field
					label={ __( 'Permanent delete by default', 'batchpilot' ) }
					help={ __(
						'When on, Delete skips the Trash and removes items immediately. Undo will not be available.',
						'batchpilot'
					) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							values.delete_permanent_default
								? __( 'Skip trash', 'batchpilot' )
								: __( 'Move to trash', 'batchpilot' )
						}
						checked={ !! values.delete_permanent_default }
						onChange={ ( v ) =>
							update( { delete_permanent_default: v } )
						}
					/>
				</Field>
			</Section>

			<Section
				title={ __(
					'Duplicate and Rewrite & Republish',
					'batchpilot'
				) }
				description={ __(
					'Controls the Duplicate row action, Copy to a new draft, and Rewrite & Republish in the post list and editor.',
					'batchpilot'
				) }
			>
				<Field
					label={ __( 'Post types', 'batchpilot' ) }
					help={ __(
						'Post types that get these actions.',
						'batchpilot'
					) }
				>
					{ ( bootstrap.postTypes || [] ).map( ( type ) => (
						<CheckboxControl
							key={ type.value }
							__nextHasNoMarginBottom
							label={ type.label }
							checked={ ( values.copy_post_types || [] ).includes(
								type.value
							) }
							onChange={ ( on ) =>
								update( {
									copy_post_types: toggleValue(
										values.copy_post_types,
										type.value,
										on
									),
								} )
							}
						/>
					) ) }
				</Field>

				<Field
					label={ __( 'Roles', 'batchpilot' ) }
					help={ __(
						'Roles allowed to use them. Administrators always can, and users still need permission to edit the post.',
						'batchpilot'
					) }
				>
					{ ( bootstrap.roles || [] ).map( ( role ) => (
						<CheckboxControl
							key={ role.value }
							__nextHasNoMarginBottom
							label={ role.label }
							checked={ ( values.copy_roles || [] ).includes(
								role.value
							) }
							onChange={ ( on ) =>
								update( {
									copy_roles: toggleValue(
										values.copy_roles,
										role.value,
										on
									),
								} )
							}
						/>
					) ) }
				</Field>
			</Section>

			<Section
				title={ __( 'History retention', 'batchpilot' ) }
				description={ __(
					'How long BatchPilot keeps operation records, snapshots, and undo data.',
					'batchpilot'
				) }
			>
				<Field
					label={ __( 'Retention window', 'batchpilot' ) }
					help={ __(
						'Days to keep completed operations and their undo snapshots. Older history is removed daily.',
						'batchpilot'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Retention (days)', 'batchpilot' ) }
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

			<Section
				title={ __( 'Uninstall', 'batchpilot' ) }
				description={ __(
					'What happens to BatchPilot data when the plugin is deleted.',
					'batchpilot'
				) }
			>
				<Field
					label={ __(
						'Delete all BatchPilot data when uninstalling',
						'batchpilot'
					) }
					help={ __(
						'Removes history, undo snapshots, schedules, settings, and capabilities. Off by default, so reinstalling keeps your history.',
						'batchpilot'
					) }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							values.delete_data_on_uninstall
								? __( 'Delete data', 'batchpilot' )
								: __( 'Keep data', 'batchpilot' )
						}
						checked={ !! values.delete_data_on_uninstall }
						onChange={ ( v ) =>
							update( { delete_data_on_uninstall: v } )
						}
					/>
				</Field>
			</Section>

			<div className="bp-settings__actions">
				<Button
					variant="primary"
					isBusy={ saving }
					onClick={ save }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'batchpilot' )
						: __( 'Save settings', 'batchpilot' ) }
				</Button>
			</div>
		</div>
	);
};

export default SettingsForm;
