import { TextControl, ToggleControl } from '@wordpress/components';

const OperationParamsForm = ( { schema, value, onChange } ) => {
	if ( ! schema || schema.type !== 'object' || ! schema.properties ) {
		return null;
	}
	const update = ( key, v ) => onChange( { ...value, [ key ]: v } );

	return (
		<div className="content-ops-op-params">
			{ Object.entries( schema.properties ).map( ( [ key, prop ] ) => {
				if ( prop.type === 'boolean' ) {
					const checked =
						value[ key ] !== undefined
							? !! value[ key ]
							: !! prop.default;
					return (
						<ToggleControl
							key={ key }
							__nextHasNoMarginBottom
							label={ key }
							checked={ checked }
							onChange={ ( v ) => update( key, v ) }
						/>
					);
				}
				if ( prop.type === 'integer' ) {
					const current = value[ key ];
					return (
						<TextControl
							key={ key }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ key }
							type="number"
							value={
								current === undefined || current === null
									? ''
									: String( current )
							}
							onChange={ ( v ) =>
								update(
									key,
									v === '' ? undefined : parseInt( v, 10 )
								)
							}
						/>
					);
				}
				return (
					<TextControl
						key={ key }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ key }
						value={ value[ key ] || '' }
						onChange={ ( v ) => update( key, v ) }
					/>
				);
			} ) }
		</div>
	);
};

export default OperationParamsForm;
