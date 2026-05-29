import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const MIN = 60;
const HOUR = 3600;
const DAY = 86400;

const relativeTime = ( iso ) => {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso.replace( ' ', 'T' ) + 'Z' );
	if ( Number.isNaN( then ) ) {
		return iso;
	}
	const diff = Math.max( 0, Math.floor( ( Date.now() - then ) / 1000 ) );
	if ( diff < MIN ) {
		return __( 'just now', 'batchpilot' );
	}
	if ( diff < HOUR ) {
		const m = Math.floor( diff / MIN );
		return sprintf(
			/* translators: %d: number of minutes */
			_n( '%d minute ago', '%d minutes ago', m, 'batchpilot' ),
			m
		);
	}
	if ( diff < DAY ) {
		const h = Math.floor( diff / HOUR );
		return sprintf(
			/* translators: %d: number of hours */
			_n( '%d hour ago', '%d hours ago', h, 'batchpilot' ),
			h
		);
	}
	const d = Math.floor( diff / DAY );
	return sprintf(
		/* translators: %d: number of days */
		_n( '%d day ago', '%d days ago', d, 'batchpilot' ),
		d
	);
};

const statusLabel = {
	completed: __( 'Completed', 'batchpilot' ),
	running: __( 'Running', 'batchpilot' ),
	failed: __( 'Failed', 'batchpilot' ),
	queued: __( 'Queued', 'batchpilot' ),
};

const HistoryTable = ( { api, pageSize = 20, onRowAction } ) => {
	const [ page, setPage ] = useState( 0 );
	const [ rows, setRows ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		api.listOperations( { limit: pageSize, offset: page * pageSize } )
			.then( ( r ) => {
				if ( ! cancelled ) {
					setRows( r );
					setLoading( false );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setRows( [] );
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ api, page, pageSize ] );

	if ( rows === null ) {
		return (
			<div className="bp-history bp-history--loading" role="status">
				<Spinner />
				<span>{ __( 'Loading history…', 'batchpilot' ) }</span>
			</div>
		);
	}

	if ( rows.length === 0 && page === 0 ) {
		return (
			<div className="bp-history bp-history--empty" role="status">
				<strong className="bp-history__empty-title">
					{ __( 'No operations yet', 'batchpilot' ) }
				</strong>
				<span className="bp-history__empty-hint">
					{ __(
						'Run your first bulk operation from the Operations page. It will appear here with full details and an Undo option.',
						'batchpilot'
					) }
				</span>
			</div>
		);
	}

	return (
		<div className={ `bp-history${ loading ? ' is-loading' : '' }` }>
			<table className="bp-history__table">
				<thead>
					<tr>
						<th>{ __( 'When', 'batchpilot' ) }</th>
						<th>{ __( 'Operation', 'batchpilot' ) }</th>
						<th>{ __( 'Target', 'batchpilot' ) }</th>
						<th className="bp-history__col-count">
							{ __( 'Items', 'batchpilot' ) }
						</th>
						<th>{ __( 'Status', 'batchpilot' ) }</th>
						<th>{ __( 'User', 'batchpilot' ) }</th>
						<th className="bp-history__col-actions">
							{ __( 'Actions', 'batchpilot' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( r ) => (
						<tr key={ r.id }>
							<td>
								<time
									dateTime={ r.created_at }
									title={ r.created_at }
									className="bp-history__time"
								>
									{ relativeTime( r.created_at ) }
								</time>
							</td>
							<td>
								<span
									className={ `bp-chip bp-chip--op bp-chip--op-${ r.type }` }
								>
									{ r.type }
								</span>
							</td>
							<td>
								<span className="bp-history__target">
									{ r.target }
								</span>
							</td>
							<td className="bp-history__col-count">
								<span className="bp-history__count">
									{ r.affected_count }
								</span>
							</td>
							<td>
								<span
									className={ `bp-status bp-status--${ r.status }` }
								>
									<span
										className="bp-status__dot"
										aria-hidden="true"
									/>
									{ statusLabel[ r.status ] || r.status }
								</span>
							</td>
							<td>
								<span className="bp-history__user">
									{ r.user_id && r.user_id > 0
										? `#${ r.user_id }`
										: __( 'CLI', 'batchpilot' ) }
								</span>
							</td>
							<td className="bp-history__col-actions">
								<div className="bp-history__row-actions">
									<Button
										variant="tertiary"
										onClick={ () =>
											onRowAction &&
											onRowAction( 'view', r )
										}
									>
										{ __( 'Details', 'batchpilot' ) }
									</Button>
									{ r.status === 'completed' && (
										<Button
											variant="tertiary"
											onClick={ () =>
												onRowAction &&
												onRowAction( 'undo', r )
											}
										>
											{ __( 'Undo', 'batchpilot' ) }
										</Button>
									) }
									<Button
										variant="tertiary"
										onClick={ () =>
											onRowAction &&
											onRowAction( 'rerun', r )
										}
									>
										{ __( 'Re-run', 'batchpilot' ) }
									</Button>
								</div>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<nav
				className="bp-history__pagination"
				aria-label={ __( 'History pagination', 'batchpilot' ) }
			>
				<Button
					variant="secondary"
					disabled={ page === 0 }
					onClick={ () => setPage( ( p ) => Math.max( 0, p - 1 ) ) }
				>
					{ __( '← Previous', 'batchpilot' ) }
				</Button>
				<span className="bp-history__page-indicator">
					{ sprintf(
						/* translators: %d: page number, 1-indexed */
						__( 'Page %d', 'batchpilot' ),
						page + 1
					) }
				</span>
				<Button
					variant="secondary"
					disabled={ rows.length < pageSize }
					onClick={ () => setPage( ( p ) => p + 1 ) }
				>
					{ __( 'Next →', 'batchpilot' ) }
				</Button>
			</nav>
		</div>
	);
};

export default HistoryTable;
