import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterList from '../components/FilterList';

describe( 'FilterList', () => {
	it( 'renders rows and dispatches ADD_FILTER on add', async () => {
		const dispatch = jest.fn();
		render(
			<FilterList
				filters={ [ { id: 'a', key: null, value: null } ] }
				defs={ [
					{
						key: 'status',
						label: 'Status',
						type: 'enum',
						schema: {},
					},
				] }
				dispatch={ dispatch }
			/>
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: /add filter/i } )
		);
		expect( dispatch ).toHaveBeenCalledWith( { type: 'ADD_FILTER' } );
	} );
} );
