import {
	Button,
	ToggleControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
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
			<Button isDestructive onClick={ onRemove }>
				{ __( 'Remove', 'content-ops' ) }
			</Button>
		</div>
	);
};

export default FilterRow;
