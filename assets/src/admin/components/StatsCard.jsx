import { useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const StatsCard = ( { api } ) => {
	const [ ops, setOps ] = useState( null );
	const [ schedules, setSchedules ] = useState( null );

	useEffect( () => {
		api.listOperations( { limit: 100, offset: 0 } )
			.then( setOps )
			.catch( () => setOps( [] ) );
		if ( api.listSchedules ) {
			api.listSchedules()
				.then( ( res ) => setSchedules( res.schedules || [] ) )
				.catch( () => setSchedules( null ) );
		}
	}, [ api ] );

	if ( ops === null ) {
		return <Spinner />;
	}

	const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
	const parseTs = ( s ) => {
		if ( ! s ) {
			return NaN;
		}
		const hasTz = /[zZ]|[+-]\d{2}:?\d{2}$/.test( s );
		return Date.parse( hasTz ? s : s + 'Z' );
	};
	const thisWeek = ops.filter(
		( op ) => parseTs( op.created_at ) >= weekAgo
	);
	const items = thisWeek.reduce(
		( sum, op ) => sum + ( op.affected_count || 0 ),
		0
	);

	const activeSchedules = ( schedules || [] ).filter( ( s ) => s.enabled );
	const nextRun = activeSchedules
		.map( ( s ) => parseTs( s.next_run_at ) )
		.filter( ( t ) => ! Number.isNaN( t ) )
		.sort( ( a, b ) => a - b )[ 0 ];

	return (
		<div className="bp-metrics">
			<div className="bp-metric">
				<p className="bp-metric__label">
					{ __( 'Operations', 'batchpilot' ) }
				</p>
				<p
					className="bp-metric__value"
					data-testid="stats-ops-this-week"
				>
					{ thisWeek.length }
				</p>
			</div>
			<div className="bp-metric">
				<p className="bp-metric__label">
					{ __( 'Items affected', 'batchpilot' ) }
				</p>
				<p
					className="bp-metric__value"
					data-testid="stats-items-affected"
				>
					{ items }
				</p>
			</div>
			{ schedules !== null && (
				<>
					<div className="bp-metric">
						<p className="bp-metric__label">
							{ __( 'Active schedules', 'batchpilot' ) }
						</p>
						<p
							className="bp-metric__value"
							data-testid="stats-active-schedules"
						>
							{ activeSchedules.length }
						</p>
					</div>
					<div className="bp-metric">
						<p className="bp-metric__label">
							{ __( 'Next scheduled run', 'batchpilot' ) }
						</p>
						<p
							className="bp-metric__value bp-metric__value--muted"
							data-testid="stats-next-run"
						>
							{ nextRun === undefined
								? __( 'None', 'batchpilot' )
								: new Date( nextRun ).toLocaleString() }
						</p>
					</div>
				</>
			) }
		</div>
	);
};

export default StatsCard;
