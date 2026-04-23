import { __ } from '@wordpress/i18n';
import SettingsForm from '../components/SettingsForm';
import { createApi } from '../api';
import { getBootstrap } from '../bootstrap';

const Settings = ( { api = createApi(), bootstrap = getBootstrap() } ) => (
	<div>
		<h1>{ __( 'Settings', 'content-ops' ) }</h1>
		<SettingsForm api={ api } />
		<h2>{ __( 'AI agent access', 'content-ops' ) }</h2>
		<p>
			{ __(
				'Generate a scoped Application Password in the WordPress users admin:',
				'content-ops'
			) }{ ' ' }
			<a href={ bootstrap.adminUrl + 'users.php' }>
				{ __( 'Users admin', 'content-ops' ) }
			</a>
		</p>
	</div>
);

export default Settings;
