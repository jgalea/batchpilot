import { __ } from '@wordpress/i18n';
import HistoryTable from '../components/HistoryTable';
import { createApi } from '../api';

const History = ( { api = createApi() } ) => (
	<div>
		<h1>{ __( 'Operations history', 'content-ops' ) }</h1>
		<HistoryTable api={ api } />
	</div>
);

export default History;
