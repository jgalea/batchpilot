import {
	Button,
	ToggleControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const EnumInput = ( { def, value, onChange } ) => {
	const options =
		def.schema && Array.isArray( def.schema.options )
			? def.schema.options
			: null;
	const multiple = Boolean( def.schema && def.schema.multiple );
	if ( ! options ) {
		return (
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ def.label }
				help={
					multiple
						? __( 'Comma-separated values.', 'content-ops' )
						: ''
				}
				value={
					Array.isArray( value ) ? value.join( ',' ) : value || ''
				}
				onChange={ ( v ) =>
					onChange(
						multiple
							? v
									.split( ',' )
									.map( ( s ) => s.trim() )
									.filter( Boolean )
							: v
					)
				}
			/>
		);
	}
	if ( multiple ) {
		return (
			<fieldset>
				<legend>{ def.label }</legend>
				{ options.map( ( o ) => {
					const checked =
						Array.isArray( value ) && value.includes( o.value );
					const id = `content-ops-enum-${ def.key }-${ o.value }`;
					return (
						<div key={ o.value }>
							<input
								id={ id }
								type="checkbox"
								checked={ checked }
								onChange={ () => {
									const arr = Array.isArray( value )
										? [ ...value ]
										: [];
									onChange(
										checked
											? arr.filter(
													( v ) => v !== o.value
											  )
											: [ ...arr, o.value ]
									);
								} }
							/>
							<label htmlFor={ id }>{ o.label }</label>
						</div>
					);
				} ) }
			</fieldset>
		);
	}
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ def.label }
			value={ value || '' }
			options={ [
				{ label: __( 'Choose…', 'content-ops' ), value: '' },
				...options,
			] }
			onChange={ onChange }
		/>
	);
};

const BoolInput = ( { def, value, onChange } ) => (
	<ToggleControl
		label={ def.label }
		checked={ !! value }
		onChange={ onChange }
	/>
);

const DateInput = ( { def, value, onChange } ) => (
	<div>
		<label htmlFor={ `co-date-${ def.key }` }>{ def.label }</label>
		<input
			id={ `co-date-${ def.key }` }
			type="date"
			value={ value || '' }
			onChange={ ( e ) => onChange( e.target.value ) }
		/>
	</div>
);

const IdInput = ( { def, value, onChange } ) => (
	<TextControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		label={ def.label }
		type="number"
		value={ value === null || value === undefined ? '' : String( value ) }
		onChange={ ( v ) => onChange( v === '' ? null : parseInt( v, 10 ) ) }
	/>
);

const TaxonomyInput = ( { value, onChange } ) => {
	const initialTax = ( value && value.taxonomy ) || '';
	const initialIds =
		value && Array.isArray( value.term_ids )
			? value.term_ids.join( ', ' )
			: '';
	const [ tax, setTax ] = useState( initialTax );
	const [ ids, setIds ] = useState( initialIds );

	const emit = ( nextTax, nextIds ) => {
		const termIds = nextIds
			.split( ',' )
			.map( ( s ) => parseInt( s.trim(), 10 ) )
			.filter( ( n ) => ! Number.isNaN( n ) );
		onChange( { taxonomy: nextTax, term_ids: termIds } );
	};

	return (
		<div>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Taxonomy slug', 'content-ops' ) }
				value={ tax }
				onChange={ ( v ) => {
					setTax( v );
					emit( v, ids );
				} }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Term IDs', 'content-ops' ) }
				value={ ids }
				help={ __( 'Comma-separated.', 'content-ops' ) }
				onChange={ ( v ) => {
					setIds( v );
					emit( tax, v );
				} }
			/>
		</div>
	);
};

const FilterRow = ( { row, defs, onChange, onRemove } ) => {
	const def = defs.find( ( d ) => d.key === row.key ) || null;
	const keyOptions = [
		{ label: __( 'Choose filter…', 'content-ops' ), value: '' },
		...defs.map( ( d ) => ( { label: d.label, value: d.key } ) ),
	];

	return (
		<div className="content-ops-filter-row" role="group">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Filter', 'content-ops' ) }
				value={ row.key || '' }
				options={ keyOptions }
				onChange={ ( key ) =>
					onChange( { key: key || null, value: null } )
				}
			/>
			{ def && def.type === 'enum' && (
				<EnumInput
					def={ def }
					value={ row.value }
					onChange={ ( value ) =>
						onChange( { key: def.key, value } )
					}
				/>
			) }
			{ def && def.type === 'bool' && (
				<BoolInput
					def={ def }
					value={ row.value }
					onChange={ ( value ) =>
						onChange( { key: def.key, value } )
					}
				/>
			) }
			{ def && def.type === 'date' && (
				<DateInput
					def={ def }
					value={ row.value }
					onChange={ ( value ) =>
						onChange( { key: def.key, value } )
					}
				/>
			) }
			{ def && ( def.type === 'user' || def.type === 'post' ) && (
				<IdInput
					def={ def }
					value={ row.value }
					onChange={ ( value ) =>
						onChange( { key: def.key, value } )
					}
				/>
			) }
			{ def && def.type === 'taxonomy' && (
				<TaxonomyInput
					value={ row.value }
					onChange={ ( value ) =>
						onChange( { key: def.key, value } )
					}
				/>
			) }
			<Button isDestructive onClick={ onRemove }>
				{ __( 'Remove', 'content-ops' ) }
			</Button>
		</div>
	);
};

export default FilterRow;
