import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import HistoryTable from '../components/HistoryTable';
import OperationDetailsModal from '../components/OperationDetailsModal';
import { createApi, normalizeError } from '../api';
import { getBootstrap } from '../bootstrap';

const History = ( { api = createApi(), bootstrap = getBootstrap() } ) => {
	const [ viewing, setViewing ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );

	const onRowAction = async ( action, row ) => {
		if ( action === 'view' ) {
			setViewing( row );
		}
		if ( action === 'undo' ) {
			try {
				const r = await api.undoOperation( row.id );
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: %s: restored count or identifier */
						__( 'Restored: %s', 'content-ops' ),
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
			<h1>{ __( 'Operations history', 'content-ops' ) }</h1>
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
