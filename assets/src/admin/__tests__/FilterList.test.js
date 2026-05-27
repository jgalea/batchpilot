import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterList from '../components/FilterList';

describe( 'FilterList', () => {
	const defs = [
		{ key: 'status', label: 'Status', type: 'enum', schema: {} },
		{ key: 'author', label: 'Author', type: 'user' },
	];

	it( 'shows empty state with warning copy when no filters are set', () => {
		render(
			<FilterList filters={ [] } defs={ defs } dispatch={ () => {} } />
		);
		expect( screen.getByText( /no filters yet/i ) ).toBeInTheDocument();
		expect(
			screen.getByText( /will match every item/i )
		).toBeInTheDocument();
	} );

	it( 'dispatches ADD_FILTER with key when a filter def is picked', async () => {
		const dispatch = jest.fn();
		render(
			<FilterList filters={ [] } defs={ defs } dispatch={ dispatch } />
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: /add filter/i } )
		);
		await userEvent.click(
			screen.getByRole( 'menuitem', { name: /status/i } )
		);

		expect( dispatch ).toHaveBeenCalledWith( {
			type: 'ADD_FILTER',
			key: 'status',
		} );
	} );

	it( 'disables add button when all filter defs are already used', () => {
		render(
			<FilterList
				filters={ [
					{ id: 'a', key: 'status', value: null },
					{ id: 'b', key: 'author', value: 1 },
				] }
				defs={ defs }
				dispatch={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /all filters added/i } )
		).toBeDisabled();
	} );
} );
