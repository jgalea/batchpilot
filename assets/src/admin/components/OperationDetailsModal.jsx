import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const OperationDetailsModal = ( { operation, onClose } ) => (
	<Modal
		title={ __( 'Operation details', 'batchpilot' ) }
		onRequestClose={ onClose }
		shouldCloseOnClickOutside={ true }
	>
		<h3>{ __( 'Filters', 'batchpilot' ) }</h3>
		<pre>{ JSON.stringify( operation.filters || {}, null, 2 ) }</pre>
		<h3>{ __( 'Parameters', 'batchpilot' ) }</h3>
		<pre>{ JSON.stringify( operation.params || {}, null, 2 ) }</pre>
		<h3>{ __( 'Affected IDs', 'batchpilot' ) }</h3>
		<p>{ ( operation.affected_ids || [] ).join( ', ' ) }</p>
		<Button variant="primary" onClick={ onClose }>
			{ __( 'Close', 'batchpilot' ) }
		</Button>
	</Modal>
);

export default OperationDetailsModal;
