import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const labelFor = ( operation, count ) => {
	switch ( operation ) {
		case 'delete':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Will delete %d items', 'content-ops' ),
				count
			);
		case 'duplicate':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Will duplicate %d items', 'content-ops' ),
				count
			);
		case 'edit':
			return sprintf(
				/* translators: %d: number of items */
				__( 'Will edit %d items', 'content-ops' ),
				count
			);
		default:
			return sprintf(
				/* translators: %d: number of items */
				__( 'Will process %d items', 'content-ops' ),
				count
			);
	}
};

const ExecuteButton = ( { preview, operation, onExecute, executing } ) => {
	const disabled = ! preview || ! preview.preview_token || executing;
	const label = preview
		? labelFor( operation, preview.count )
		: __( 'Preview first', 'content-ops' );
	return (
		<Button
			variant="primary"
			isBusy={ executing }
			disabled={ disabled }
			onClick={ onExecute }
		>
			{ label }
		</Button>
	);
};

export default ExecuteButton;
