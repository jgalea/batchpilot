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

	it( 'shows "Will delete N items" when a fresh preview is present', () => {
		render(
			<ExecuteButton
				preview={ { count: 7, preview_token: 't' } }
				operation="delete"
				onExecute={ () => {} }
				executing={ false }
			/>
		);
		expect( screen.getByRole( 'button' ) ).toHaveTextContent(
			/will delete 7/i
		);
	} );

	it( 'calls onExecute on click', async () => {
		const onExecute = jest.fn();
		render(
			<ExecuteButton
				preview={ { count: 1, preview_token: 't' } }
				operation="duplicate"
				onExecute={ onExecute }
				executing={ false }
			/>
		);
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( onExecute ).toHaveBeenCalled();
	} );
} );
