import { __ } from '@wordpress/i18n';
import HealthPanel from '../components/HealthPanel';
import { createApi } from '../api';

const Dashboard = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Dashboard', 'content-ops' ) }</h1>
		<HealthPanel api={ api } />
	</div>
);

export default Dashboard;
