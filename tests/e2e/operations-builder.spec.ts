import { test, expect } from './fixtures';

test.describe( 'Operations Builder — happy path', () => {
	test.beforeEach( async ( { requestUtils, admin } ) => {
		await requestUtils.activatePlugin( 'content-ops' );
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
			'page=content-ops-operations'
		);
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'admin can preview and execute a delete on drafts', async ( {
		page,
	} ) => {
		await expect(
			page.getByRole( 'heading', { name: 'Operations Builder' } )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Posts' } ).click();

		await page.getByRole( 'button', { name: 'Add filter' } ).click();
		await page
			.getByRole( 'combobox', { name: 'Filter' } )
			.selectOption( 'status' );
		await page.getByLabel( 'Status' ).fill( 'draft' );

		await page
			.getByRole( 'button', { name: 'Delete', exact: true } )
			.click();

		await expect( page.getByText( /Matched: 3 items/ ) ).toBeVisible( {
			timeout: 5000,
		} );
		await expect( page.getByText( 'E2E draft 1' ) ).toBeVisible();

		const executeBtn = page.getByRole( 'button', {
			name: /^Delete 3 items$/i,
		} );
		await expect( executeBtn ).toBeEnabled();
		await executeBtn.click();

		await expect( page.getByRole( 'status' ) ).toContainText(
			/3 succeeded/
		);
	} );
} );
