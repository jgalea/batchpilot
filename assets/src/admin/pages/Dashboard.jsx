import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import StatsCard from '../components/StatsCard';
import RecentOperationsList from '../components/RecentOperationsList';
import { createApi } from '../api';

const Dashboard = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
		<StatsCard api={ api } />
		<HealthPanel api={ api } />
		<RecentOperationsList api={ api } />
	</div>
);

export default Dashboard;
