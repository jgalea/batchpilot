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
		return __( 'just now', 'content-ops' );
	}
	if ( diff < HOUR ) {
		const m = Math.floor( diff / MIN );
		return sprintf(
			/* translators: %d: number of minutes */
			_n( '%d minute ago', '%d minutes ago', m, 'content-ops' ),
			m
		);
	}
	if ( diff < DAY ) {
		const h = Math.floor( diff / HOUR );
		return sprintf(
			/* translators: %d: number of hours */
			_n( '%d hour ago', '%d hours ago', h, 'content-ops' ),
			h
		);
	}
	const d = Math.floor( diff / DAY );
	return sprintf(
		/* translators: %d: number of days */
		_n( '%d day ago', '%d days ago', d, 'content-ops' ),
		d
	);
};

const statusLabel = {
	completed: __( 'Completed', 'content-ops' ),
	running: __( 'Running', 'content-ops' ),
	failed: __( 'Failed', 'content-ops' ),
	queued: __( 'Queued', 'content-ops' ),
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
			<div className="co-history co-history--loading" role="status">
				<Spinner />
				<span>{ __( 'Loading history…', 'content-ops' ) }</span>
			</div>
		);
	}

	if ( rows.length === 0 && page === 0 ) {
		return (
			<div className="co-history co-history--empty" role="status">
				<strong className="co-history__empty-title">
					{ __( 'No operations yet', 'content-ops' ) }
				</strong>
				<span className="co-history__empty-hint">
					{ __(
						'Run your first bulk operation from the Operations page. It will appear here with full details and an Undo option.',
						'content-ops'
					) }
				</span>
			</div>
		);
	}

	return (
		<div className={ `co-history${ loading ? ' is-loading' : '' }` }>
			<table className="co-history__table">
				<thead>
					<tr>
						<th>{ __( 'When', 'content-ops' ) }</th>
						<th>{ __( 'Operation', 'content-ops' ) }</th>
						<th>{ __( 'Target', 'content-ops' ) }</th>
						<th className="co-history__col-count">
							{ __( 'Items', 'content-ops' ) }
						</th>
						<th>{ __( 'Status', 'content-ops' ) }</th>
						<th>{ __( 'User', 'content-ops' ) }</th>
						<th className="co-history__col-actions">
							{ __( 'Actions', 'content-ops' ) }
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
									className="co-history__time"
								>
									{ relativeTime( r.created_at ) }
								</time>
							</td>
							<td>
								<span
									className={ `co-chip co-chip--op co-chip--op-${ r.type }` }
								>
									{ r.type }
								</span>
							</td>
							<td>
								<span className="co-history__target">
									{ r.target }
								</span>
							</td>
							<td className="co-history__col-count">
								<span className="co-history__count">
									{ r.affected_count }
								</span>
							</td>
							<td>
								<span
									className={ `co-status co-status--${ r.status }` }
								>
									<span
										className="co-status__dot"
										aria-hidden="true"
									/>
									{ statusLabel[ r.status ] || r.status }
								</span>
							</td>
							<td>
								<span className="co-history__user">
									{ r.user_id && r.user_id > 0
										? `#${ r.user_id }`
										: __( 'CLI', 'content-ops' ) }
								</span>
							</td>
							<td className="co-history__col-actions">
								<div className="co-history__row-actions">
									<Button
										variant="tertiary"
										onClick={ () =>
											onRowAction &&
											onRowAction( 'view', r )
										}
									>
										{ __( 'Details', 'content-ops' ) }
									</Button>
									{ r.status === 'completed' && (
										<Button
											variant="tertiary"
											onClick={ () =>
												onRowAction &&
												onRowAction( 'undo', r )
											}
										>
											{ __( 'Undo', 'content-ops' ) }
										</Button>
									) }
									<Button
										variant="tertiary"
										onClick={ () =>
											onRowAction &&
											onRowAction( 'rerun', r )
										}
									>
										{ __( 'Re-run', 'content-ops' ) }
									</Button>
								</div>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<nav
				className="co-history__pagination"
				aria-label={ __( 'History pagination', 'content-ops' ) }
			>
				<Button
					variant="secondary"
					disabled={ page === 0 }
					onClick={ () => setPage( ( p ) => Math.max( 0, p - 1 ) ) }
				>
					{ __( '← Previous', 'content-ops' ) }
				</Button>
				<span className="co-history__page-indicator">
					{ sprintf(
						/* translators: %d: page number, 1-indexed */
						__( 'Page %d', 'content-ops' ),
						page + 1
					) }
				</span>
				<Button
					variant="secondary"
					disabled={ rows.length < pageSize }
					onClick={ () => setPage( ( p ) => p + 1 ) }
				>
					{ __( 'Next →', 'content-ops' ) }
				</Button>
			</nav>
		</div>
	);
};

export default HistoryTable;
