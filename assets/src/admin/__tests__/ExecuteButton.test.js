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

	it( 'asks for confirmation before any destructive operation runs', async () => {
		window.confirm = jest.fn( () => false );
		const onExecute = jest.fn();
		render(
			<ExecuteButton
				preview={ { count: 3, preview_token: 't' } }
				operation="user-delete"
				destructive
				onExecute={ onExecute }
				executing={ false }
				hasFilters
			/>
		);
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( window.confirm ).toHaveBeenCalled();
		expect( onExecute ).not.toHaveBeenCalled();

		window.confirm = jest.fn( () => true );
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( onExecute ).toHaveBeenCalled();
	} );

	it( 'requires the checkbox for unfiltered destructive operations', () => {
		render(
			<ExecuteButton
				preview={ { count: 3, preview_token: 't' } }
				operation="term-delete"
				destructive
				onExecute={ () => {} }
				executing={ false }
				hasFilters={ false }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /run on 3/i } )
		).toBeDisabled();
	} );
} );
