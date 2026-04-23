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
		<div className="co-stack">
			<header className="co-page-header">
				<span className="co-eyebrow">
					{ __( 'Overview', 'content-ops' ) }
				</span>
				<h1>{ __( 'Content Ops', 'content-ops' ) }</h1>
				<p className="co-page-subtitle">
					{ __(
						'Bulk delete, duplicate, and edit posts on WordPress. Preview before executing; undo from history.',
						'content-ops'
					) }
				</p>
			</header>

			<section>
				<h2 className="co-section-title">
					{ __( 'Quick actions', 'content-ops' ) }
				</h2>
				<div className="co-preset-cards">
					<a
						className="co-preset-card"
						href={ actionLink( opsUrl, 'delete' ) }
					>
						<p className="co-preset-card__title">
							{ __( 'Bulk delete', 'content-ops' ) }
						</p>
						<p className="co-preset-card__description">
							{ __(
								'Trash or hard-delete posts matching filters. Undo from history within retention.',
								'content-ops'
							) }
						</p>
						<div className="co-preset-card__meta">
							<span className="co-chip co-chip--accent">
								{ __( 'operation', 'content-ops' ) }
							</span>
							<span className="co-chip">delete</span>
						</div>
					</a>
					<a
						className="co-preset-card"
						href={ actionLink( opsUrl, 'duplicate' ) }
					>
						<p className="co-preset-card__title">
							{ __( 'Bulk duplicate', 'content-ops' ) }
						</p>
						<p className="co-preset-card__description">
							{ __(
								'Copy posts with meta, taxonomies, and featured images. Target status and title suffix configurable.',
								'content-ops'
							) }
						</p>
						<div className="co-preset-card__meta">
							<span className="co-chip co-chip--accent">
								{ __( 'operation', 'content-ops' ) }
							</span>
							<span className="co-chip">duplicate</span>
						</div>
					</a>
					<a
						className="co-preset-card"
						href={ actionLink( opsUrl, 'edit' ) }
					>
						<p className="co-preset-card__title">
							{ __( 'Bulk edit', 'content-ops' ) }
						</p>
						<p className="co-preset-card__description">
							{ __(
								'Reassign authors, shift dates, change status, add/remove taxonomy terms. Snapshots enable undo.',
								'content-ops'
							) }
						</p>
						<div className="co-preset-card__meta">
							<span className="co-chip co-chip--accent">
								{ __( 'operation', 'content-ops' ) }
							</span>
							<span className="co-chip">edit</span>
						</div>
					</a>
				</div>
			</section>

			<section>
				<h2 className="co-section-title">
					{ __( 'This week', 'content-ops' ) }
				</h2>
				<StatsCard api={ api } />
			</section>

			<section>
				<h2 className="co-section-title">
					{ __( 'Environment', 'content-ops' ) }
				</h2>
				<HealthPanel api={ api } />
			</section>

			{ presets.length > 0 && (
				<section>
					<h2 className="co-section-title">
						{ __( 'Common cleanups', 'content-ops' ) }
					</h2>
					<PresetCards presets={ presets } operationsUrl={ opsUrl } />
				</section>
			) }

			<section>
				<h2 className="co-section-title">
					{ __( 'Recent operations', 'content-ops' ) }
				</h2>
				<RecentOperationsList api={ api } />
			</section>
		</div>
	);
};

export default Dashboard;
