import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const HistoryTable = ( { api, pageSize = 20, onRowAction } ) => {
	const [ page, setPage ] = useState( 0 );
	const [ rows, setRows ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		api.listOperations( { limit: pageSize, offset: page * pageSize } )
			.then( ( r ) => {
				if ( ! cancelled ) {
					setRows( r );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setRows( [] );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ api, page, pageSize ] );

	if ( rows === null ) {
		return <Spinner />;
	}

	return (
		<div>
			<table className="widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Date', 'content-ops' ) }</th>
						<th>{ __( 'Type', 'content-ops' ) }</th>
						<th>{ __( 'Target', 'content-ops' ) }</th>
						<th>{ __( 'Items', 'content-ops' ) }</th>
						<th>{ __( 'Status', 'content-ops' ) }</th>
						<th>{ __( 'User', 'content-ops' ) }</th>
						<th>{ __( 'Actions', 'content-ops' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( r ) => (
						<tr key={ r.id }>
							<td>{ r.created_at }</td>
							<td>{ r.type }</td>
							<td>{ r.target }</td>
							<td>{ r.affected_count }</td>
							<td>{ r.status }</td>
							<td>{ r.user_id }</td>
							<td>
								<Button
									variant="link"
									onClick={ () =>
										onRowAction && onRowAction( 'view', r )
									}
								>
									{ __( 'Details', 'content-ops' ) }
								</Button>
								{ r.status === 'completed' && (
									<Button
										variant="link"
										onClick={ () =>
											onRowAction &&
											onRowAction( 'undo', r )
										}
									>
										{ __( 'Undo', 'content-ops' ) }
									</Button>
								) }
								<Button
									variant="link"
									onClick={ () =>
										onRowAction && onRowAction( 'rerun', r )
									}
								>
									{ __( 'Re-run', 'content-ops' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<div className="content-ops-pagination">
				<Button
					variant="secondary"
					disabled={ page === 0 }
					onClick={ () => setPage( ( p ) => Math.max( 0, p - 1 ) ) }
				>
					{ __( 'Previous', 'content-ops' ) }
				</Button>
				<span>
					{ __( 'Page', 'content-ops' ) } { page + 1 }
				</span>
				<Button
					variant="secondary"
					disabled={ rows.length < pageSize }
					onClick={ () => setPage( ( p ) => p + 1 ) }
				>
					{ __( 'Next', 'content-ops' ) }
				</Button>
			</div>
		</div>
	);
};

export default HistoryTable;
