import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import HistoryTable from '../components/HistoryTable';

const rows = ( n ) =>
	Array.from( { length: n }, ( _, i ) => ( {
		id: i + 1,
		type: 'delete',
		target: 'post',
		affected_count: 5,
		status: 'completed',
		user_id: 1,
		created_at: '2026-04-01 10:00:00',
		completed_at: '2026-04-01 10:00:01',
		filters: {},
		params: {},
		affected_ids: [ 10, 11 ],
	} ) );

describe( 'HistoryTable', () => {
	it( 'loads first page and paginates', async () => {
		const api = {
			listOperations: jest
				.fn()
				.mockResolvedValueOnce( rows( 20 ) )
				.mockResolvedValueOnce( rows( 3 ) ),
		};
		render( <HistoryTable api={ api } pageSize={ 20 } /> );
		await waitFor( () =>
			expect( screen.getAllByRole( 'row' ) ).toHaveLength( 21 )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: /next/i } )
		);
		await waitFor( () =>
			expect( api.listOperations ).toHaveBeenCalledWith( {
				limit: 20,
				offset: 20,
			} )
		);
	} );
} );
