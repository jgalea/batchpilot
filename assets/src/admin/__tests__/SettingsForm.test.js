import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SettingsForm from '../components/SettingsForm';

describe( 'SettingsForm', () => {
	it( 'loads settings, edits them, and saves', async () => {
		const api = {
			getSettings: jest.fn().mockResolvedValue( {
				async_threshold: 100,
				batch_size: 50,
				delete_permanent_default: false,
				history_retention_days: 30,
				role_caps: {},
			} ),
			saveSettings: jest.fn().mockResolvedValue( {
				async_threshold: 250,
				batch_size: 50,
				delete_permanent_default: false,
				history_retention_days: 30,
				role_caps: {},
			} ),
		};
		render( <SettingsForm api={ api } /> );
		const threshold = await screen.findByLabelText( /async threshold/i );
		await userEvent.clear( threshold );
		await userEvent.type( threshold, '250' );
		await userEvent.click(
			screen.getByRole( 'button', { name: /save/i } )
		);
		await waitFor( () =>
			expect( api.saveSettings ).toHaveBeenCalledWith(
				expect.objectContaining( { async_threshold: 250 } )
			)
		);
		expect(
			( await screen.findAllByText( /saved/i ) ).length
		).toBeGreaterThan( 0 );
	} );
} );
