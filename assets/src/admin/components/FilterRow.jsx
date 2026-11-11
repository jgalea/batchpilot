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
				hideLabelFromVision
				label={ def.label }
				placeholder={
					multiple
						? __( 'comma-separated values', 'batchpilot' )
						: __( 'value', 'batchpilot' )
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
			<div className="bp-filter-row__enum-multi" role="group">
				{ options.map( ( o ) => {
					const checked =
						Array.isArray( value ) && value.includes( o.value );
					const id = `bp-enum-${ def.key }-${ o.value }`;
					return (
						<label
							key={ o.value }
							htmlFor={ id }
							className="bp-chip"
						>
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
							<span>{ o.label }</span>
						</label>
					);
				} ) }
			</div>
		);
	}
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			hideLabelFromVision
			label={ def.label }
			value={ value || '' }
			options={ [
				{ label: __( 'Choose…', 'batchpilot' ), value: '' },
				...options,
			] }
			onChange={ onChange }
		/>
	);
};

const BoolInput = ( { value, onChange } ) => (
	<ToggleControl
		__nextHasNoMarginBottom
		label={ value ? __( 'Yes', 'batchpilot' ) : __( 'No', 'batchpilot' ) }
		checked={ !! value }
		onChange={ onChange }
	/>
);

const DateInput = ( { def, value, onChange } ) => (
	<input
		type="date"
		className="bp-filter-row__date"
		aria-label={ def.label }
		value={ value || '' }
		onChange={ ( e ) => onChange( e.target.value ) }
	/>
);

const IdInput = ( { def, value, onChange } ) => (
	<TextControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		hideLabelFromVision
		label={ def.label }
		type="number"
		placeholder={ __( 'ID', 'batchpilot' ) }
		value={ value === null || value === undefined ? '' : String( value ) }
		onChange={ ( v ) => onChange( v === '' ? null : parseInt( v, 10 ) ) }
	/>
);

const IdsInput = ( { def, value, onChange } ) => {
	const initial = Array.isArray( value ) ? value.join( ', ' ) : '';
	const [ raw, setRaw ] = useState( initial );
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			hideLabelFromVision
			label={ def.label }
			placeholder={ __(
				'comma-separated IDs, e.g. 12, 34, 56',
				'batchpilot'
			) }
			value={ raw }
			onChange={ ( v ) => {
				setRaw( v );
				const ids = v
					.split( ',' )
					.map( ( s ) => parseInt( s.trim(), 10 ) )
					.filter( ( n ) => ! Number.isNaN( n ) && n > 0 );
				onChange( ids );
			} }
		/>
	);
};

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
		<div className="bp-filter-row__taxonomy">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ __( 'Taxonomy slug', 'batchpilot' ) }
				placeholder={ __( 'taxonomy (e.g. category)', 'batchpilot' ) }
				value={ tax }
				onChange={ ( v ) => {
					setTax( v );
					emit( v, ids );
				} }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ __( 'Term IDs', 'batchpilot' ) }
				placeholder={ __( 'term IDs (e.g. 12, 34)', 'batchpilot' ) }
				value={ ids }
				onChange={ ( v ) => {
					setIds( v );
					emit( tax, v );
				} }
			/>
		</div>
	);
};

const TextInput = ( { def, value, onChange } ) => (
	<TextControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		hideLabelFromVision
		label={ def.label }
		placeholder={ __( 'text to look for', 'batchpilot' ) }
		value={ value || '' }
		onChange={ onChange }
	/>
);

const META_COMPARE_LABELS = {
	'=': __( 'equals', 'batchpilot' ),
	'!=': __( 'does not equal', 'batchpilot' ),
	'>': __( 'greater than', 'batchpilot' ),
	'>=': __( 'at least', 'batchpilot' ),
	'<': __( 'less than', 'batchpilot' ),
	'<=': __( 'at most', 'batchpilot' ),
	LIKE: __( 'contains', 'batchpilot' ),
	'NOT LIKE': __( 'does not contain', 'batchpilot' ),
	EXISTS: __( 'exists', 'batchpilot' ),
	'NOT EXISTS': __( 'does not exist', 'batchpilot' ),
};

const MetaInput = ( { def, value, onChange } ) => {
	const current = { key: '', compare: '=', value: '', ...( value || {} ) };
	const compares =
		def.schema && Array.isArray( def.schema.compares )
			? def.schema.compares
			: Object.keys( META_COMPARE_LABELS );
	const needsValue = ! [ 'EXISTS', 'NOT EXISTS' ].includes( current.compare );
	const emit = ( patch ) => onChange( { ...current, ...patch } );

	return (
		<div className="bp-filter-row__meta">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ __( 'Custom field key', 'batchpilot' ) }
				placeholder={ __( 'field key (e.g. price)', 'batchpilot' ) }
				value={ current.key }
				onChange={ ( key ) => emit( { key } ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ __( 'Comparison', 'batchpilot' ) }
				value={ current.compare }
				options={ compares.map( ( c ) => ( {
					label: META_COMPARE_LABELS[ c ] || c,
					value: c,
				} ) ) }
				onChange={ ( compare ) => emit( { compare } ) }
			/>
			{ needsValue && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ __( 'Custom field value', 'batchpilot' ) }
					placeholder={ __( 'value', 'batchpilot' ) }
					value={ current.value }
					onChange={ ( v ) => emit( { value: v } ) }
				/>
			) }
		</div>
	);
};

const removeIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="18"
		height="18"
		aria-hidden="true"
	>
		<path
			fill="currentColor"
			d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
		/>
	</svg>
);

const FilterRow = ( { row, defs, onChange, onRemove } ) => {
	const def = defs.find( ( d ) => d.key === row.key ) || null;

	if ( ! def ) {
		const keyOptions = [
			{ label: __( 'Choose filter…', 'batchpilot' ), value: '' },
			...defs.map( ( d ) => ( { label: d.label, value: d.key } ) ),
		];
		return (
			<div className="bp-filter-row bp-filter-row--empty" role="group">
				<div className="bp-filter-row__picker">
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Filter', 'batchpilot' ) }
						value=""
						options={ keyOptions }
						onChange={ ( key ) =>
							onChange( { key: key || null, value: null } )
						}
					/>
				</div>
				<Button
					className="bp-filter-row__remove"
					onClick={ onRemove }
					label={ __( 'Remove filter', 'batchpilot' ) }
					icon={ removeIcon }
				/>
			</div>
		);
	}

	const typeClass = `bp-filter-row--type-${ def.type }`;

	return (
		<div className={ `bp-filter-row ${ typeClass }` } role="group">
			<span className="bp-filter-row__label">{ def.label }</span>
			<div className="bp-filter-row__value">
				{ def.type === 'enum' && (
					<EnumInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'bool' && (
					<BoolInput
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'date' && (
					<DateInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ ( def.type === 'user' || def.type === 'post' ) && (
					<IdInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'ids' && (
					<IdsInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'text' && (
					<TextInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'meta' && (
					<MetaInput
						def={ def }
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
				{ def.type === 'taxonomy' && (
					<TaxonomyInput
						value={ row.value }
						onChange={ ( value ) =>
							onChange( { key: def.key, value } )
						}
					/>
				) }
			</div>
			<Button
				className="bp-filter-row__remove"
				onClick={ onRemove }
				label={ __( 'Remove filter', 'batchpilot' ) }
				icon={ removeIcon }
			/>
		</div>
	);
};

export default FilterRow;
