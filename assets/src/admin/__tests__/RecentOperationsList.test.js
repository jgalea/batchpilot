import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import RecentOperationsList from '../components/RecentOperationsList';

describe( 'RecentOperationsList', () => {
	beforeEach( () => {
		window.confirm = jest.fn( () => true );
	} );

	it( 'renders last five operations with an undo action for delete', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [
				{
					id: 10,
					type: 'delete',
					target: 'post',
					affected_count: 3,
					status: 'completed',
					created_at: '2026-04-20 10:00:00',
				},
			] ),
			undoOperation: jest.fn().mockResolvedValue( { restored: 3 } ),
		};
		render( <RecentOperationsList api={ api } /> );
		await waitFor( () =>
			expect( screen.getByText( /delete/i ) ).toBeInTheDocument()
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: /undo/i } )
		);
		expect( api.undoOperation ).toHaveBeenCalledWith( 10 );
	} );

	it( 'reports duplicates that were skipped because they were edited', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [
				{
					id: 11,
					type: 'duplicate',
					target: 'post',
					affected_count: 3,
					status: 'completed',
					created_at: '2026-04-20 10:00:00',
				},
			] ),
			undoOperation: jest
				.fn()
				.mockResolvedValue( { restored: 2, skipped: 1 } ),
		};
		render( <RecentOperationsList api={ api } /> );
		await waitFor( () =>
			expect( screen.getByText( /duplicate/i ) ).toBeInTheDocument()
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: /undo/i } )
		);
		expect(
			await screen.findAllByText(
				/Restored 2. Skipped 1 that were edited/
			)
		).not.toHaveLength( 0 );
	} );
} );
