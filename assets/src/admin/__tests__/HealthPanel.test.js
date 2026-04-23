import { render, screen, waitFor } from '@testing-library/react';
import HealthPanel from '../components/HealthPanel';

const makeApi = ( doctor ) => ( {
	fetchDoctor: jest.fn().mockResolvedValue( doctor ),
} );

describe( 'HealthPanel', () => {
	it( 'renders green checks for available systems', async () => {
		const api = makeApi( {
			action_scheduler: { available: true },
			abilities_api: { available: false },
			hpos: { available: false, enabled: false },
			tables: { expected: [ 'x' ], missing: [] },
			cron: { disabled: false },
			schema_version: '1',
		} );
		render( <HealthPanel api={ api } /> );
		await waitFor( () =>
			expect(
				screen.getByTestId( 'health-action_scheduler' )
			).toHaveAttribute( 'data-status', 'ok' )
		);
		expect( screen.getByTestId( 'health-abilities_api' ) ).toHaveAttribute(
			'data-status',
			'warn'
		);
		expect( screen.getByTestId( 'health-hpos' ) ).toHaveAttribute(
			'data-status',
			'warn'
		);
		expect( screen.getByTestId( 'health-tables' ) ).toHaveAttribute(
			'data-status',
			'ok'
		);
		expect( screen.getByTestId( 'health-cron' ) ).toHaveAttribute(
			'data-status',
			'ok'
		);
	} );

	it( 'shows error when doctor fetch fails', async () => {
		const api = {
			fetchDoctor: jest
				.fn()
				.mockRejectedValue( { code: 'co.internal', message: 'boom' } ),
		};
		render( <HealthPanel api={ api } /> );
		await waitFor( () =>
			expect( screen.getByRole( 'alert' ) ).toHaveTextContent( /boom/ )
		);
	} );
} );
