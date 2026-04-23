import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const ExecutionResult = ( { execution, historyUrl } ) => {
	if ( ! execution ) {
		return null;
	}
	if ( execution.status === 'queued' ) {
		return (
			<div role="status">
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Operation queued. It will run in the background.',
						'content-ops'
					) }{ ' ' }
					<a
						href={ `${ historyUrl }&operation=${ execution.operation_id }` }
					>
						{ __( 'View in history', 'content-ops' ) }
					</a>
				</Notice>
			</div>
		);
	}
	const { processed = 0, succeeded = 0, failed = 0 } = execution.batch || {};
	return (
		<div role="status">
			<Notice
				status={ failed > 0 ? 'warning' : 'success' }
				isDismissible={ false }
			>
				{ sprintf(
					/* translators: 1: processed count, 2: succeeded count, 3: failed count */
					__(
						'%1$d processed · %2$d succeeded · %3$d failed',
						'content-ops'
					),
					processed,
					succeeded,
					failed
				) }{ ' ' }
				<a
					href={ `${ historyUrl }&operation=${ execution.operation_id }` }
				>
					{ __( 'View in history', 'content-ops' ) }
				</a>
			</Notice>
		</div>
	);
};

export default ExecutionResult;
