import { test } from './fixtures';

const SHOTS_DIR =
	process.env.SHOTS_DIR || 'test-results/screenshots';

test.describe( 'BatchPilot wp.org screenshots', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'batchpilot' );
		await requestUtils.deleteAllPosts();
		for ( let i = 0; i < 8; i++ ) {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: {
					title: `Sample draft ${ i + 1 }`,
					status: 'draft',
					content: `Draft content ${ i + 1 }`,
				},
			} );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
	} );

	test( '01 Operations Builder', async ( { page, admin } ) => {
		await admin.visitAdminPage(
			'admin.php',
			'page=batchpilot-operations'
		);
		await page
			.getByRole( 'heading', { name: 'Operations', level: 1 } )
			.waitFor();
		await page.getByRole( 'button', { name: /^Posts/ } ).click();
		await page.getByRole( 'button', { name: 'Add filter' } ).click();
		await page.getByRole( 'menuitem', { name: 'Status' } ).click();
		await page.getByRole( 'checkbox', { name: 'Draft' } ).check();
		await page.waitForTimeout( 600 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/01-operations-builder.png`,
			fullPage: true,
		} );
	} );

	test( '02 Live preview', async ( { page, admin, requestUtils } ) => {
		// Make sure drafts exist for THIS test specifically.
		await requestUtils.deleteAllPosts();
		const created: number[] = [];
		for ( let i = 0; i < 5; i++ ) {
			const post = await requestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: {
					title: `Draft for preview ${ i + 1 }`,
					status: 'draft',
					content: 'Sample content.',
				},
			} );
			created.push( post.id );
		}

		await admin.visitAdminPage(
			'admin.php',
			`page=batchpilot-operations&target=post&operation=delete&filters[status][]=draft`
		);
		await page
			.getByRole( 'heading', { name: 'Operations', level: 1 } )
			.waitFor();

		// Deep-link prefill should populate state, but give it time to settle
		// then trigger preview.
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 1500 );
		await page
			.getByText( /items matched/i )
			.first()
			.waitFor( { timeout: 15000 } );
		await page.waitForTimeout( 500 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/02-preview-panel.png`,
			fullPage: true,
		} );
	} );

	test( '03 History screen', async ( { page, admin, requestUtils } ) => {
		// Run one operation so the history table has content.
		const password = await requestUtils.rest< {
			password: string;
		} >( {
			method: 'POST',
			path: '/wp/v2/users/me/application-passwords',
			data: { name: 'batchpilot-screenshot' },
		} );

		const auth = Buffer.from(
			`admin:${ password.password }`
		).toString( 'base64' );

		// Preview
		const previewRes = await fetch(
			`http://localhost:8900/index.php?rest_route=/batchpilot/v1/preview`,
			{
				method: 'POST',
				headers: {
					Authorization: `Basic ${ auth }`,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					target: 'post',
					operation: 'delete',
					filters: { status: [ 'draft' ] },
				} ),
			}
		);
		const preview = ( await previewRes.json() ) as {
			preview_token: string;
		};

		// Execute
		await fetch(
			`http://localhost:8900/index.php?rest_route=/batchpilot/v1/execute`,
			{
				method: 'POST',
				headers: {
					Authorization: `Basic ${ auth }`,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					target: 'post',
					operation: 'delete',
					filters: { status: [ 'draft' ] },
					preview_token: preview.preview_token,
				} ),
			}
		);

		await admin.visitAdminPage( 'admin.php', 'page=batchpilot-history' );
		await page
			.getByRole( 'heading', { name: 'History' } )
			.first()
			.waitFor();
		await page.waitForTimeout( 600 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/03-history.png`,
			fullPage: true,
		} );
	} );

	test( '04 Dashboard', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=batchpilot' );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 800 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/04-dashboard.png`,
			fullPage: true,
		} );
	} );
} );
