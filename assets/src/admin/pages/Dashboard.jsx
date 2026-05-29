import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import StatsCard from '../components/StatsCard';
import RecentOperationsList from '../components/RecentOperationsList';
import PresetCards from '../components/PresetCards';
import { defaultApi } from '../api';
import { getBootstrap } from '../bootstrap';

const actionLink = ( operationsUrl, op ) =>
	`${ operationsUrl }&operation=${ op }`;

const Dashboard = ( { api = defaultApi, bootstrap = getBootstrap() } ) => {
	const [ presets, setPresets ] = useState( [] );
	useEffect( () => {
		api.fetchCatalog()
			.then( ( c ) => setPresets( c.presets || [] ) )
			.catch( () => setPresets( [] ) );
	}, [ api ] );

	const opsUrl = bootstrap.pages.operations;

	return (
		<div className="bp-stack">
			<header className="bp-page-header">
				<span className="bp-eyebrow">
					{ __( 'Overview', 'batchpilot' ) }
				</span>
				<h1>{ __( 'BatchPilot', 'batchpilot' ) }</h1>
				<p className="bp-page-subtitle">
					{ __(
						'Bulk delete, duplicate, and edit posts on WordPress. Preview before executing; undo from history.',
						'batchpilot'
					) }
				</p>
			</header>

			<section>
				<h2 className="bp-section-title">
					{ __( 'Quick actions', 'batchpilot' ) }
				</h2>
				<div className="bp-preset-cards">
					<a
						className="bp-preset-card"
						href={ actionLink( opsUrl, 'delete' ) }
					>
						<p className="bp-preset-card__title">
							{ __( 'Bulk delete', 'batchpilot' ) }
						</p>
						<p className="bp-preset-card__description">
							{ __(
								'Trash or hard-delete posts matching filters. Undo from history within retention.',
								'batchpilot'
							) }
						</p>
						<div className="bp-preset-card__meta">
							<span className="bp-chip bp-chip--accent">
								{ __( 'operation', 'batchpilot' ) }
							</span>
							<span className="bp-chip">delete</span>
						</div>
					</a>
					<a
						className="bp-preset-card"
						href={ actionLink( opsUrl, 'duplicate' ) }
					>
						<p className="bp-preset-card__title">
							{ __( 'Bulk duplicate', 'batchpilot' ) }
						</p>
						<p className="bp-preset-card__description">
							{ __(
								'Copy posts with meta, taxonomies, and featured images. Target status and title suffix configurable.',
								'batchpilot'
							) }
						</p>
						<div className="bp-preset-card__meta">
							<span className="bp-chip bp-chip--accent">
								{ __( 'operation', 'batchpilot' ) }
							</span>
							<span className="bp-chip">duplicate</span>
						</div>
					</a>
					<a
						className="bp-preset-card"
						href={ actionLink( opsUrl, 'edit' ) }
					>
						<p className="bp-preset-card__title">
							{ __( 'Bulk edit', 'batchpilot' ) }
						</p>
						<p className="bp-preset-card__description">
							{ __(
								'Reassign authors, shift dates, change status, add/remove taxonomy terms. Snapshots enable undo.',
								'batchpilot'
							) }
						</p>
						<div className="bp-preset-card__meta">
							<span className="bp-chip bp-chip--accent">
								{ __( 'operation', 'batchpilot' ) }
							</span>
							<span className="bp-chip">edit</span>
						</div>
					</a>
				</div>
			</section>

			<section>
				<h2 className="bp-section-title">
					{ __( 'This week', 'batchpilot' ) }
				</h2>
				<StatsCard api={ api } />
			</section>

			<section>
				<h2 className="bp-section-title">
					{ __( 'Environment', 'batchpilot' ) }
				</h2>
				<HealthPanel api={ api } />
			</section>

			{ presets.length > 0 && (
				<section>
					<h2 className="bp-section-title">
						{ __( 'Common cleanups', 'batchpilot' ) }
					</h2>
					<PresetCards presets={ presets } operationsUrl={ opsUrl } />
				</section>
			) }

			<section>
				<h2 className="bp-section-title">
					{ __( 'Recent operations', 'batchpilot' ) }
				</h2>
				<RecentOperationsList api={ api } />
			</section>
		</div>
	);
};

export default Dashboard;
