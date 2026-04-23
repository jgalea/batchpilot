import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import FilterRow from '../components/FilterRow';

const defs = [
	{
		key: 'status',
		label: 'Status',
		type: 'enum',
		schema: { multiple: true },
	},
	{ key: 'has_comments', label: 'Has comments', type: 'bool', schema: {} },
];

describe( 'FilterRow', () => {
	it( 'renders a key picker populated from filter defs', () => {
		render(
			<FilterRow
				row={ { id: '1', key: null, value: null } }
				defs={ defs }
				onChange={ () => {} }
				onRemove={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'combobox', { name: /filter/i } )
		).toBeInTheDocument();
	} );

	it( 'calls onChange when key is selected', async () => {
		const onChange = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: null, value: null } }
				defs={ defs }
				onChange={ onChange }
				onRemove={ () => {} }
			/>
		);
		await userEvent.selectOptions(
			screen.getByRole( 'combobox', { name: /filter/i } ),
			'status'
		);
		expect( onChange ).toHaveBeenCalledWith( {
			key: 'status',
			value: null,
		} );
	} );

	it( 'renders bool toggle for bool types', async () => {
		const onChange = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: 'has_comments', value: null } }
				defs={ defs }
				onChange={ onChange }
				onRemove={ () => {} }
			/>
		);
		const toggle = screen.getByRole( 'checkbox' );
		await userEvent.click( toggle );
		expect( onChange ).toHaveBeenCalledWith( {
			key: 'has_comments',
			value: true,
		} );
	} );

	it( 'triggers onRemove', async () => {
		const onRemove = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: 'status', value: null } }
				defs={ defs }
				onChange={ () => {} }
				onRemove={ onRemove }
			/>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: /remove/i } )
		);
		expect( onRemove ).toHaveBeenCalled();
	} );
} );
