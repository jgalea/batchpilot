import { useEffect, useState } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const StatsCard = ( { api } ) => {
	const [ ops, setOps ] = useState( null );

	useEffect( () => {
		api.listOperations( { limit: 100, offset: 0 } )
			.then( setOps )
			.catch( () => setOps( [] ) );
	}, [ api ] );

	if ( ops === null ) {
		return <Spinner />;
	}

	const weekAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
	const parseTs = ( s ) => {
		if ( ! s ) {
			return NaN;
		}
		// ISO strings already carry a timezone marker (Z or +/-HH:MM).
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

	return (
		<Card>
			<CardHeader>{ __( 'This week', 'content-ops' ) }</CardHeader>
			<CardBody>
				<p>
					<span data-testid="stats-ops-this-week">
						{ thisWeek.length }
					</span>{ ' ' }
					{ __( 'operations', 'content-ops' ) }
				</p>
				<p>
					<span data-testid="stats-items-affected">{ items }</span>{ ' ' }
					{ __( 'items affected', 'content-ops' ) }
				</p>
				<p data-testid="stats-active-schedules">
					{ __( 'Active schedules: N/A', 'content-ops' ) }
				</p>
				<p data-testid="stats-next-run">
					{ __( 'Next scheduled run: N/A', 'content-ops' ) }
				</p>
			</CardBody>
		</Card>
	);
};

export default StatsCard;
