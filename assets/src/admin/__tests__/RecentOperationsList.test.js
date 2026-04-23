import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import RecentOperationsList from '../components/RecentOperationsList';

describe( 'RecentOperationsList', () => {
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
} );
