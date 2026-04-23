import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeError } from '../api';

const UNDOABLE = [ 'delete', 'duplicate', 'edit' ];

const RecentOperationsList = ( { api } ) => {
	const [ ops, setOps ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const reload = () =>
		api
			.listOperations( { limit: 5, offset: 0 } )
			.then( setOps )
			.catch( () => setOps( [] ) );

	useEffect( () => {
		reload();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const undo = async ( id ) => {
		try {
			const r = await api.undoOperation( id );
			setNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %d: number of items restored. */
					__( 'Restored %d', 'content-ops' ),
					r.restored
				),
			} );
			reload();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				text: normalizeError( err ).message,
			} );
		}
	};

	if ( ops === null ) {
		return <Spinner />;
	}

	return (
		<div>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ ops.length === 0 && (
				<p className="co-empty">
					{ __( 'No operations yet.', 'content-ops' ) }
				</p>
			) }
			{ ops.length > 0 && (
				<ul className="recent-operations">
					{ ops.map( ( op ) => (
						<li key={ op.id }>
							<span className="co-chip co-chip--accent">
								{ op.type }
							</span>
							<span className="co-chip">{ op.target }</span>
							<time>
								{ op.affected_count }{ ' ' }
								{ __( 'items', 'content-ops' ) } · { op.status }
							</time>
							{ UNDOABLE.includes( op.type ) &&
								op.status === 'completed' && (
									<Button
										variant="secondary"
										onClick={ () => undo( op.id ) }
										style={ { marginLeft: 'auto' } }
									>
										{ __( 'Undo', 'content-ops' ) }
									</Button>
								) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
};

export default RecentOperationsList;
