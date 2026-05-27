import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const labelFor = ( operation, count ) => {
	switch ( operation ) {
		case 'delete':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Delete %d items', 'content-ops' ),
				count
			);
		case 'duplicate':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Duplicate %d items', 'content-ops' ),
				count
			);
		case 'edit':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Bulk-edit %d items', 'content-ops' ),
				count
			);
		default:
			return sprintf(
				/* translators: %d: number of items */
				__( 'Run on %d items', 'content-ops' ),
				count
			);
	}
};

// Threshold above which we demand an extra confirmation even when filters exist.
const LARGE_THRESHOLD = 100;

const ExecuteButton = ( {
	preview,
	operation,
	onExecute,
	executing,
	hasFilters = true,
} ) => {
	const [ confirmed, setConfirmed ] = useState( false );
	const disabled = ! preview || ! preview.preview_token || executing;
	const isDestructive = operation === 'delete';
	const count = preview ? preview.count : 0;
	const unfiltered = ! hasFilters && count > 0;
	const large = count >= LARGE_THRESHOLD;
	const needsConfirm = isDestructive && ( unfiltered || large );
	const canRun = ! disabled && ( ! needsConfirm || confirmed );

	const label = preview
		? labelFor( operation, count )
		: __( 'Preview first', 'content-ops' );

	return (
		<div className="co-execute">
			{ unfiltered && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="co-execute__notice"
				>
					{ __(
						'No filters added. This will match EVERY item of this target. Add filters to narrow the set.',
						'content-ops'
					) }
				</Notice>
			) }
			{ needsConfirm && ! unfiltered && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="co-execute__notice"
				>
					{ sprintf(
						/* translators: %d: number of items */
						__(
							'You are about to delete %d items. Double-check the preview above.',
							'content-ops'
						),
						count
					) }
				</Notice>
			) }
			{ needsConfirm && (
				<label
					className="co-execute__confirm"
					htmlFor="co-execute__confirm-input"
				>
					<input
						id="co-execute__confirm-input"
						type="checkbox"
						checked={ confirmed }
						onChange={ ( e ) => setConfirmed( e.target.checked ) }
					/>
					<span>
						{ __(
							'I understand — proceed with this destructive action.',
							'content-ops'
						) }
					</span>
				</label>
			) }
			<Button
				variant="primary"
				isBusy={ executing }
				isDestructive={ isDestructive }
				disabled={ ! canRun }
				onClick={ onExecute }
				className="co-execute__button"
			>
				{ label }
			</Button>
		</div>
	);
};

export default ExecuteButton;
