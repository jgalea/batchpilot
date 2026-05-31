import { test, expect } from './fixtures';

const SHOTS_DIR =
	process.env.SHOTS_DIR ||
	'test-results/screenshots';

test.describe( 'BatchPilot Pro — Schedules UI', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'batchpilot' );
		await requestUtils.activatePlugin( 'batchpilot-pro' );
	} );

	test( 'schedules page renders and create flow works', async ( {
		page,
		admin,
	} ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await admin.visitAdminPage(
			'admin.php',
			'page=batchpilot-pro-schedules'
		);
		await page
			.getByRole( 'heading', { name: 'Schedules' } )
			.waitFor( { timeout: 8000 } );
		await page.waitForTimeout( 600 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/pro-01-schedules-list.png`,
			fullPage: true,
		} );

		// Open create modal.
		await page.getByRole( 'button', { name: 'New schedule' } ).click();
		await page
			.getByRole( 'dialog', { name: 'New schedule' } )
			.waitFor( { timeout: 5000 } );
		await page.waitForTimeout( 400 );

		await page.getByLabel( 'Name' ).fill( 'UI test schedule' );
		await page.getByLabel( 'Target' ).selectOption( 'post' );
		await page.waitForTimeout( 300 );

		// Add a Status filter via the FilterEditor.
		await page.getByRole( 'button', { name: 'Add filter' } ).click();
		await page
			.getByRole( 'combobox', { name: 'Filter' } )
			.selectOption( 'status' );
		await page.getByRole( 'checkbox', { name: 'Draft' } ).check();

		await page.getByLabel( 'Operation' ).selectOption( 'delete' );
		await page.waitForTimeout( 300 );

		// Optional: fill notification fields so the screenshot shows the section.
		await page
			.getByLabel( 'Email address' )
			.fill( 'ops@example.com' );

		await page.screenshot( {
			path: `${ SHOTS_DIR }/pro-02-schedule-form.png`,
			fullPage: true,
		} );

		await page.getByRole( 'button', { name: 'Create schedule' } ).click();

		await page
			.getByRole( 'cell', { name: 'UI test schedule' } )
			.waitFor( { timeout: 5000 } );

		await expect(
			page.getByRole( 'cell', { name: 'UI test schedule' } )
		).toBeVisible();
		await page.waitForTimeout( 400 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/pro-03-schedules-after-create.png`,
			fullPage: true,
		} );
	} );
} );
