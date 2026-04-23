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

describe( 'FilterRow extra types', () => {
	it( 'renders a date input for date type', async () => {
		const onChange = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: 'modified_before', value: null } }
				defs={ [
					{
						key: 'modified_before',
						label: 'Modified before',
						type: 'date',
						schema: {},
					},
				] }
				onChange={ onChange }
				onRemove={ () => {} }
			/>
		);
		const input = screen.getByLabelText( 'Modified before' );
		expect( input ).toHaveAttribute( 'type', 'date' );
		await userEvent.type( input, '2026-04-01' );
		expect( onChange ).toHaveBeenLastCalledWith( {
			key: 'modified_before',
			value: '2026-04-01',
		} );
	} );

	it( 'renders numeric input for user/post id types', async () => {
		const onChange = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: 'author', value: null } }
				defs={ [
					{
						key: 'author',
						label: 'Author ID',
						type: 'user',
						schema: {},
					},
				] }
				onChange={ onChange }
				onRemove={ () => {} }
			/>
		);
		const input = screen.getByLabelText( /author id/i );
		await userEvent.type( input, '7' );
		expect( onChange ).toHaveBeenLastCalledWith( {
			key: 'author',
			value: 7,
		} );
	} );

	it( 'renders taxonomy input with slug and term IDs', async () => {
		const onChange = jest.fn();
		render(
			<FilterRow
				row={ { id: '1', key: 'taxonomy', value: null } }
				defs={ [
					{
						key: 'taxonomy',
						label: 'Taxonomy',
						type: 'taxonomy',
						schema: {},
					},
				] }
				onChange={ onChange }
				onRemove={ () => {} }
			/>
		);
		await userEvent.type(
			screen.getByLabelText( /taxonomy slug/i ),
			'category'
		);
		await userEvent.type( screen.getByLabelText( /term ids/i ), '3, 5' );
		expect( onChange ).toHaveBeenLastCalledWith( {
			key: 'taxonomy',
			value: { taxonomy: 'category', term_ids: [ 3, 5 ] },
		} );
	} );
} );
