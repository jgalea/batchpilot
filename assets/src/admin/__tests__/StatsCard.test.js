import { render, screen, waitFor } from '@testing-library/react';
import StatsCard from '../components/StatsCard';

describe( 'StatsCard', () => {
	it( 'shows counts derived from operations list', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [
				{
					id: 1,
					affected_count: 5,
					status: 'completed',
					created_at: new Date().toISOString(),
				},
				{
					id: 2,
					affected_count: 3,
					status: 'completed',
					created_at: new Date().toISOString(),
				},
			] ),
		};
		render( <StatsCard api={ api } /> );
		await waitFor( () =>
			expect(
				screen.getByTestId( 'stats-ops-this-week' )
			).toHaveTextContent( '2' )
		);
		expect(
			screen.getByTestId( 'stats-items-affected' )
		).toHaveTextContent( '8' );
	} );
} );
