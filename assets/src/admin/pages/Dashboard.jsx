import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import StatsCard from '../components/StatsCard';
import RecentOperationsList from '../components/RecentOperationsList';
import PresetCards from '../components/PresetCards';
import { defaultApi } from '../api';
import { getBootstrap } from '../bootstrap';

const Dashboard = ( { api = defaultApi, bootstrap = getBootstrap() } ) => {
	const [ presets, setPresets ] = useState( [] );
	useEffect( () => {
		api.fetchCatalog()
			.then( ( c ) => setPresets( c.presets || [] ) )
			.catch( () => setPresets( [] ) );
	}, [ api ] );

	return (
		<div>
			<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
			<StatsCard api={ api } />
			<HealthPanel api={ api } />
			<h2>{ __( 'Common cleanups', 'content-ops' ) }</h2>
			<PresetCards
				presets={ presets }
				operationsUrl={ bootstrap.pages.operations }
			/>
			<RecentOperationsList api={ api } />
		</div>
	);
};

export default Dashboard;
