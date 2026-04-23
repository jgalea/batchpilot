import * as path from 'path';
import { defineConfig } from '@playwright/test';

process.env.WP_ARTIFACTS_PATH ??= path.join( process.cwd(), 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

export default defineConfig( {
	testDir: __dirname,
	timeout: 60_000,
	retries: 0,
	globalSetup: require.resolve( './global-setup.ts' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		storageState: process.env.STORAGE_STATE_PATH,
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	reporter: 'list',
} );
