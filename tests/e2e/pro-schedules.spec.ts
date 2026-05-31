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
		await page.waitForTimeout( 500 );
		await page.screenshot( {
			path: `${ SHOTS_DIR }/pro-02-schedule-form.png`,
			fullPage: true,
		} );

		// Fill in a schedule that will trash drafts.
		await page.getByLabel( 'Name' ).fill( 'UI test schedule' );
		await page.getByLabel( 'Target' ).selectOption( 'post' );
		await page.getByLabel( 'Operation' ).selectOption( 'delete' );
		await page.getByLabel( 'Recurrence' ).selectOption( '86400' );
		await page
			.getByLabel( 'Filters (JSON)' )
			.fill( '{"status":["draft"]}' );
		await page.getByLabel( 'Params (JSON)' ).fill( '{"permanent":false}' );

		await page.getByRole( 'button', { name: 'Create schedule' } ).click();

		// After creation the modal closes and the new schedule appears in the table.
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
