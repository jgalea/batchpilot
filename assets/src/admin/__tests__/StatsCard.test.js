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

	it( 'shows schedule tiles with zero and none when Pro has no schedules', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [] ),
			listSchedules: jest.fn().mockResolvedValue( { schedules: [] } ),
		};
		render( <StatsCard api={ api } /> );
		await waitFor( () =>
			expect(
				screen.getByTestId( 'stats-active-schedules' )
			).toHaveTextContent( '0' )
		);
		expect( screen.getByTestId( 'stats-next-run' ) ).toHaveTextContent(
			'None'
		);
	} );

	it( 'counts enabled schedules only', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [] ),
			listSchedules: jest.fn().mockResolvedValue( {
				schedules: [
					{ enabled: true, next_run_at: '2030-01-01 00:00:00' },
					{ enabled: false, next_run_at: '2029-01-01 00:00:00' },
				],
			} ),
		};
		render( <StatsCard api={ api } /> );
		await waitFor( () =>
			expect(
				screen.getByTestId( 'stats-active-schedules' )
			).toHaveTextContent( '1' )
		);
		expect( screen.getByTestId( 'stats-next-run' ).textContent ).toContain(
			'2030'
		);
	} );

	it( 'hides schedule tiles when the schedules endpoint is unavailable', async () => {
		const api = {
			listOperations: jest.fn().mockResolvedValue( [] ),
			listSchedules: jest
				.fn()
				.mockRejectedValue( { code: 'rest_no_route' } ),
		};
		render( <StatsCard api={ api } /> );
		await screen.findByTestId( 'stats-ops-this-week' );
		await waitFor( () => expect( api.listSchedules ).toHaveBeenCalled() );
		expect( screen.queryByText( 'N/A' ) ).toBeNull();
		expect( screen.queryByTestId( 'stats-active-schedules' ) ).toBeNull();
	} );
} );
