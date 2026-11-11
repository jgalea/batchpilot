/* eslint-disable no-alert */
import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import HistoryTable from '../components/HistoryTable';
import OperationDetailsModal from '../components/OperationDetailsModal';
import { defaultApi, normalizeError } from '../api';
import { getBootstrap } from '../bootstrap';

const History = ( { api = defaultApi, bootstrap = getBootstrap() } ) => {
	const [ viewing, setViewing ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );

	const onRowAction = async ( action, row ) => {
		if ( action === 'view' ) {
			setViewing( row );
		}
		if ( action === 'undo' ) {
			const confirmed = window.confirm(
				// eslint-disable-line no-alert
				__( 'Undo this operation?', 'batchpilot' )
			);
			if ( ! confirmed ) {
				return;
			}
			try {
				const r = await api.undoOperation( row.id );
				setNotice( {
					status: 'success',
					text: r.skipped
						? sprintf(
								/* translators: 1: number of items restored, 2: number of items skipped. */
								__(
									'Restored %1$d. Skipped %2$d that were edited after they were created.',
									'batchpilot'
								),
								r.restored,
								r.skipped
						  )
						: sprintf(
								/* translators: %s: restored count or identifier */
								__( 'Restored: %s', 'batchpilot' ),
								r.restored
						  ),
				} );
				setReloadKey( ( k ) => k + 1 );
			} catch ( err ) {
				setNotice( {
					status: 'error',
					text: normalizeError( err ).message,
				} );
			}
		}
		if ( action === 'rerun' ) {
			const params = new URLSearchParams();
			params.set( 'rerun', String( row.id ) );
			window.open(
				`${ bootstrap.pages.operations }&${ params.toString() }`,
				'_blank',
				'noopener'
			);
		}
	};

	return (
		<div>
			<h1>{ __( 'Operations history', 'batchpilot' ) }</h1>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<HistoryTable
				key={ reloadKey }
				api={ api }
				onRowAction={ onRowAction }
			/>
			{ viewing && (
				<OperationDetailsModal
					operation={ viewing }
					onClose={ () => setViewing( null ) }
				/>
			) }
		</div>
	);
};

export default History;
