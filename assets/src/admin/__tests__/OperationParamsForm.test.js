import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OperationParamsForm from '../components/OperationParamsForm';

describe( 'OperationParamsForm', () => {
	it( 'renders boolean schema field as a toggle and emits onChange', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						permanent: {
							type: 'boolean',
							default: false,
							label: 'Permanent delete',
							description: 'No undo if enabled.',
						},
					},
				} }
				value={ {} }
				onChange={ onChange }
			/>
		);
		expect( screen.getByText( /permanent delete/i ) ).toBeInTheDocument();
		expect( screen.getByText( /no undo if enabled/i ) ).toBeInTheDocument();
		await userEvent.click( screen.getByRole( 'checkbox' ) );
		expect( onChange ).toHaveBeenCalledWith( { permanent: true } );
	} );

	it( 'renders string schema field and emits onChange', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						title_suffix: {
							type: 'string',
							default: ' – Copy',
							label: 'Title suffix',
						},
					},
				} }
				value={ {} }
				onChange={ onChange }
			/>
		);
		await userEvent.type( screen.getByRole( 'textbox' ), 'x' );
		expect( onChange ).toHaveBeenLastCalledWith( { title_suffix: 'x' } );
	} );

	it( 'renders enum as a select and emits the chosen value', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						comment_status: {
							type: 'string',
							enum: [ 'open', 'closed' ],
							label: 'Comments',
						},
					},
				} }
				value={ {} }
				onChange={ onChange }
			/>
		);
		const select = screen.getByRole( 'combobox' );
		await userEvent.selectOptions( select, 'closed' );
		expect( onChange ).toHaveBeenLastCalledWith( {
			comment_status: 'closed',
		} );
	} );

	it( 'clears a field when its value is emptied', async () => {
		const onChange = jest.fn();
		render(
			<OperationParamsForm
				schema={ {
					type: 'object',
					properties: {
						title_suffix: {
							type: 'string',
							label: 'Title suffix',
						},
					},
				} }
				value={ { title_suffix: 'abc' } }
				onChange={ onChange }
			/>
		);
		await userEvent.clear( screen.getByRole( 'textbox' ) );
		expect( onChange ).toHaveBeenLastCalledWith( {} );
	} );
} );
