import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	Spinner,
	Notice,
} from '@wordpress/components';
import { normalizeError } from '../api';

const hposDetail = ( hpos ) => {
	if ( ! hpos.available ) {
		return __( 'Not applicable.', 'content-ops' );
	}
	if ( ! hpos.enabled ) {
		return __( 'Available but disabled.', 'content-ops' );
	}
	return '';
};

const Row = ( { id, label, status, detail } ) => (
	<li data-testid={ `health-${ id }` } data-status={ status }>
		<span
			className={ `content-ops-health-dot content-ops-health-dot--${ status }` }
			aria-hidden="true"
		/>
		<strong>{ label }</strong>
		{ detail && (
			<span className="content-ops-health-detail"> { detail }</span>
		) }
	</li>
);

const HealthPanel = ( { api } ) => {
	const [ report, setReport ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		api.fetchDoctor()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setReport( data );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( normalizeError( e ) );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ api ] );

	if ( error ) {
		return (
			<div role="alert">
				<Notice status="error" isDismissible={ false }>
					{ error.message }
				</Notice>
			</div>
		);
	}
	if ( ! report ) {
		return <Spinner />;
	}

	const items = [
		{
			id: 'action_scheduler',
			label: __( 'Action Scheduler', 'content-ops' ),
			status: report.action_scheduler.available ? 'ok' : 'warn',
		},
		{
			id: 'abilities_api',
			label: __( 'Abilities API', 'content-ops' ),
			status: report.abilities_api.available ? 'ok' : 'warn',
			detail: report.abilities_api.available
				? ''
				: __(
						'Optional — install to expose MCP tools.',
						'content-ops'
				  ),
		},
		{
			id: 'hpos',
			label: __( 'HPOS (WooCommerce)', 'content-ops' ),
			status: report.hpos.enabled ? 'ok' : 'warn',
			detail: hposDetail( report.hpos ),
		},
		{
			id: 'tables',
			label: __( 'Database tables', 'content-ops' ),
			status: report.tables.missing.length === 0 ? 'ok' : 'error',
			detail:
				report.tables.missing.length === 0
					? ''
					: report.tables.missing.join( ', ' ),
		},
		{
			id: 'cron',
			label: __( 'WP-Cron', 'content-ops' ),
			status: report.cron.disabled ? 'warn' : 'ok',
			detail: report.cron.disabled
				? __( 'DISABLE_WP_CRON is set.', 'content-ops' )
				: '',
		},
	];

	return (
		<Card>
			<CardHeader>
				{ __( 'Environment health', 'content-ops' ) }
			</CardHeader>
			<CardBody>
				<ul className="content-ops-health-list">
					{ items.map( ( item ) => (
						<Row key={ item.id } { ...item } />
					) ) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default HealthPanel;
