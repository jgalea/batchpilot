import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OperationParamsForm from '../components/OperationParamsForm';

describe( 'OperationParamsForm', () => {
	it( 'renders a boolean schema field as a ToggleControl', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						permanent: { type: 'boolean', default: false },
					},
				} }
				value={ {} }
				onChange={ onChange }
			/>
		);
		await userEvent.click(
			screen.getByRole( 'checkbox', { name: /permanent/i } )
		);
		expect( onChange ).toHaveBeenCalledWith( { permanent: true } );
	} );

	it( 'renders a string schema field as a TextControl', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						target_status: { type: 'string', default: 'draft' },
					},
				} }
				value={ {} }
				onChange={ onChange }
			/>
		);
		await userEvent.type( screen.getByLabelText( /target_status/i ), 'x' );
		expect( onChange ).toHaveBeenLastCalledWith( { target_status: 'x' } );
	} );
} );
