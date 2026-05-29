import { test, expect } from './fixtures';

test.describe( 'Operations Builder — happy path', () => {
	test.beforeEach( async ( { requestUtils, admin } ) => {
		await requestUtils.activatePlugin( 'batchpilot' );
		for ( let i = 0; i < 3; i++ ) {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: {
					title: `E2E draft ${ i + 1 }`,
					status: 'draft',
					content: 'seeded',
				},
			} );
		}
		await admin.visitAdminPage(
			'admin.php',
			'page=batchpilot-operations'
		);
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'admin can preview and execute a delete on drafts', async ( {
		page,
	} ) => {
		await expect(
			page.getByRole( 'heading', { name: 'Operations', level: 1 } )
		).toBeVisible();

		await page.getByRole( 'button', { name: /^Posts/ } ).click();

		await page.getByRole( 'button', { name: 'Add filter' } ).click();
		await page.getByRole( 'menuitem', { name: 'Status' } ).click();
		await page.getByRole( 'checkbox', { name: 'Draft' } ).check();

		await page
			.getByRole( 'button', { name: /^Delete\b/, exact: false } )
			.first()
			.click();

		await expect(
			page.getByText( /3 items matched/i )
		).toBeVisible( { timeout: 5000 } );
		await expect( page.getByText( 'E2E draft 1' ) ).toBeVisible();

		const executeBtn = page.getByRole( 'button', {
			name: /^Delete 3 items$/i,
		} );
		await expect( executeBtn ).toBeEnabled();
		await executeBtn.click();

		await expect(
			page.locator( ':text-matches("3 succeeded", "i")' ).first()
		).toBeVisible( { timeout: 10000 } );
	} );
} );
