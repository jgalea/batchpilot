import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExecuteButton from '../components/ExecuteButton';

describe( 'ExecuteButton', () => {
	it( 'is disabled when there is no preview', () => {
		render(
			<ExecuteButton
				preview={ null }
				onExecute={ () => {} }
				executing={ false }
			/>
		);
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );

	it( 'shows "Delete N items" when a fresh preview is present', () => {
		render(
			<ExecuteButton
				preview={ { count: 7, preview_token: 't' } }
				operation="delete"
				onExecute={ () => {} }
				executing={ false }
				hasFilters
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /delete 7 items/i } )
		).toBeInTheDocument();
	} );

	it( 'calls onExecute on click', async () => {
		const onExecute = jest.fn();
		render(
			<ExecuteButton
				preview={ { count: 1, preview_token: 't' } }
				operation="duplicate"
				onExecute={ onExecute }
				executing={ false }
				hasFilters
			/>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: /duplicate 1/i } )
		);
		expect( onExecute ).toHaveBeenCalled();
	} );

	it( 'blocks destructive execute when no filters are set until confirmed', async () => {
		const onExecute = jest.fn();
		render(
			<ExecuteButton
				preview={ { count: 500, preview_token: 't' } }
				operation="delete"
				onExecute={ onExecute }
				executing={ false }
				hasFilters={ false }
			/>
		);
		const btn = screen.getByRole( 'button', {
			name: /delete 500 items/i,
		} );
		expect( btn ).toBeDisabled();

		await userEvent.click( screen.getByRole( 'checkbox' ) );
		expect( btn ).not.toBeDisabled();
	} );
} );
