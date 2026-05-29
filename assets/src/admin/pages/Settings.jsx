import { __ } from '@wordpress/i18n';
import SettingsForm from '../components/SettingsForm';
import { defaultApi } from '../api';
import { getBootstrap } from '../bootstrap';

const Settings = ( { api = defaultApi, bootstrap = getBootstrap() } ) => (
	<div>
		<h1>{ __( 'Settings', 'batchpilot' ) }</h1>
		<SettingsForm api={ api } />
		<h2>{ __( 'AI agent access', 'batchpilot' ) }</h2>
		<p>
			{ __(
				'Generate a scoped Application Password in the WordPress users admin:',
				'batchpilot'
			) }{ ' ' }
			<a href={ bootstrap.adminUrl + 'users.php' }>
				{ __( 'Users admin', 'batchpilot' ) }
			</a>
		</p>
	</div>
);

export default Settings;
