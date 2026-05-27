import { useContext, useEffect, useRef, useState } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	SelectControl,
	Button,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { BuilderContext } from '../state/builderContext';

const humanizeKey = ( key ) =>
	key.replace( /_/g, ' ' ).replace( /^\w/, ( c ) => c.toUpperCase() );

const FieldShell = ( { label, description, children } ) => (
	<div className="co-op-field">
		<div className="co-op-field__text">
			<span className="co-op-field__label">{ label }</span>
			{ description && (
				<span className="co-op-field__description">
					{ description }
				</span>
			) }
		</div>
		<div className="co-op-field__input">{ children }</div>
	</div>
);

const BooleanField = ( { prop, value, onChange, label, description } ) => {
	const checked = value !== undefined ? !! value : !! prop.default;
	return (
		<FieldShell label={ label } description={ description }>
			<ToggleControl
				__nextHasNoMarginBottom
				label={
					checked
						? __( 'Enabled', 'content-ops' )
						: __( 'Disabled', 'content-ops' )
				}
				checked={ checked }
				onChange={ onChange }
			/>
		</FieldShell>
	);
};

const IntegerField = ( { prop, value, onChange, label, description } ) => (
	<FieldShell label={ label } description={ description }>
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			hideLabelFromVision
			label={ label }
			type="number"
			placeholder={
				prop.default !== undefined ? String( prop.default ) : ''
			}
			value={
				value === undefined || value === null ? '' : String( value )
			}
			onChange={ ( v ) =>
				onChange( v === '' ? undefined : parseInt( v, 10 ) )
			}
		/>
	</FieldShell>
);

const StringField = ( { prop, value, onChange, label, description } ) => (
	<FieldShell label={ label } description={ description }>
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			hideLabelFromVision
			label={ label }
			placeholder={
				prop.default !== undefined ? String( prop.default ) : ''
			}
			value={ value || '' }
			onChange={ ( v ) => onChange( v || undefined ) }
		/>
	</FieldShell>
);

const EnumField = ( { prop, value, onChange, label, description } ) => {
	const options = [
		{ label: __( '— No change —', 'content-ops' ), value: '' },
		...( prop.enum || [] ).map( ( v ) => ( {
			label: humanizeKey( String( v ) ),
			value: String( v ),
		} ) ),
	];
	return (
		<FieldShell label={ label } description={ description }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ label }
				value={ value || '' }
				options={ options }
				onChange={ ( v ) => onChange( v || undefined ) }
			/>
		</FieldShell>
	);
};

const PostStatusField = ( {
	catalog,
	value,
	onChange,
	label,
	description,
} ) => {
	const statuses = catalog?.vocab?.statuses || [];
	const options = [
		{ label: __( '— No change —', 'content-ops' ), value: '' },
		...statuses.map( ( s ) => ( { label: s.label, value: s.value } ) ),
	];
	return (
		<FieldShell label={ label } description={ description }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				hideLabelFromVision
				label={ label }
				value={ value || '' }
				options={ options }
				onChange={ ( v ) => onChange( v || undefined ) }
			/>
		</FieldShell>
	);
};

const PasswordField = ( { value, onChange, label, description } ) => {
	const [ reveal, setReveal ] = useState( false );
	return (
		<FieldShell label={ label } description={ description }>
			<div className="co-op-field__password">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ label }
					type={ reveal ? 'text' : 'password' }
					autoComplete="new-password"
					value={ value || '' }
					onChange={ ( v ) => onChange( v || undefined ) }
				/>
				<Button
					variant="tertiary"
					onClick={ () => setReveal( ( r ) => ! r ) }
					aria-pressed={ reveal }
				>
					{ reveal
						? __( 'Hide', 'content-ops' )
						: __( 'Show', 'content-ops' ) }
				</Button>
			</div>
		</FieldShell>
	);
};

const UserField = ( { value, onChange, label, description } ) => {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const cancelRef = useRef( null );

	useEffect( () => {
		if ( ! value || selected?.id === value ) {
			return;
		}
		apiFetch( { path: `/wp/v2/users/${ value }?context=edit` } )
			.then( ( u ) => setSelected( u ) )
			.catch( () => setSelected( { id: value, name: `#${ value }` } ) );
	}, [ value, selected?.id ] );

	useEffect( () => {
		if ( query.length < 2 ) {
			setResults( [] );
			return undefined;
		}
		setLoading( true );
		if ( cancelRef.current ) {
			cancelRef.current();
		}
		let cancelled = false;
		cancelRef.current = () => {
			cancelled = true;
		};
		apiFetch( {
			path: `/wp/v2/users?search=${ encodeURIComponent(
				query
			) }&context=edit&per_page=10`,
		} )
			.then( ( list ) => {
				if ( ! cancelled ) {
					setResults( list || [] );
					setLoading( false );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ query ] );

	const pick = ( user ) => {
		setSelected( user );
		onChange( user ? user.id : undefined );
		setQuery( '' );
		setResults( [] );
	};

	return (
		<FieldShell label={ label } description={ description }>
			{ value && selected ? (
				<div className="co-op-field__user-selected">
					<span className="co-chip co-chip--accent">
						{ selected.name || `#${ selected.id }` }
						<Button
							variant="tertiary"
							onClick={ () => pick( null ) }
							label={ __( 'Remove', 'content-ops' ) }
							className="co-op-field__user-clear"
						>
							×
						</Button>
					</span>
				</div>
			) : (
				<div className="co-op-field__user-search">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ label }
						placeholder={ __(
							'Type to search users…',
							'content-ops'
						) }
						value={ query }
						onChange={ setQuery }
					/>
					{ query.length >= 2 && (
						<ul className="co-op-field__user-results">
							{ loading && (
								<li className="co-op-field__user-hint">
									{ __( 'Searching…', 'content-ops' ) }
								</li>
							) }
							{ ! loading && results.length === 0 && (
								<li className="co-op-field__user-hint">
									{ __(
										'No matching users.',
										'content-ops'
									) }
								</li>
							) }
							{ results.map( ( u ) => (
								<li key={ u.id }>
									<button
										type="button"
										className="co-op-field__user-option"
										onClick={ () => pick( u ) }
									>
										<span>{ u.name }</span>
										<span className="co-op-field__user-meta">
											#{ u.id }
											{ u.slug ? ` · ${ u.slug }` : '' }
										</span>
									</button>
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</FieldShell>
	);
};

const TaxonomyTermsField = ( {
	catalog,
	value,
	onChange,
	label,
	description,
} ) => {
	const taxonomies = catalog?.vocab?.taxonomies || [];
	const current = value || { taxonomy: '', term_ids: [] };

	const [ termOptions, setTermOptions ] = useState( [] );
	const [ loading, setLoading ] = useState( false );

	useEffect( () => {
		if ( ! current.taxonomy ) {
			setTermOptions( [] );
			return undefined;
		}
		const taxDef = ( catalog?.vocab?.taxonomies || [] ).find(
			( t ) => t.slug === current.taxonomy
		);
		const restBase = taxDef?.rest_base || current.taxonomy;
		setLoading( true );
		let cancelled = false;
		apiFetch( {
			path: `/wp/v2/${ restBase }?per_page=100&orderby=name&order=asc&context=edit&hide_empty=false`,
		} )
			.then( ( list ) => {
				if ( ! cancelled ) {
					setTermOptions(
						( list || [] ).map( ( t ) => ( {
							value: t.id,
							label: `${ t.name } (#${ t.id })`,
						} ) )
					);
					setLoading( false );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoading( false );
					setTermOptions( [] );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ current.taxonomy, catalog?.vocab?.taxonomies ] );

	const setTaxonomy = ( slug ) => {
		onChange( slug ? { taxonomy: slug, term_ids: [] } : undefined );
	};
	const toggleTerm = ( id ) => {
		const next = current.term_ids.includes( id )
			? current.term_ids.filter( ( t ) => t !== id )
			: [ ...current.term_ids, id ];
		onChange(
			next.length === 0
				? undefined
				: { taxonomy: current.taxonomy, term_ids: next }
		);
	};

	const taxOptions = [
		{ label: __( '— Pick a taxonomy —', 'content-ops' ), value: '' },
		...taxonomies.map( ( t ) => ( {
			label: `${ t.label } (${ t.slug })`,
			value: t.slug,
		} ) ),
	];

	return (
		<FieldShell label={ label } description={ description }>
			<div className="co-op-field__taxonomy">
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ __( 'Taxonomy', 'content-ops' ) }
					value={ current.taxonomy || '' }
					options={ taxOptions }
					onChange={ setTaxonomy }
				/>
				{ current.taxonomy && (
					<div className="co-op-field__taxonomy-terms">
						{ loading && (
							<span className="co-op-field__user-hint">
								{ __( 'Loading terms…', 'content-ops' ) }
							</span>
						) }
						{ ! loading && termOptions.length === 0 && (
							<span className="co-op-field__user-hint">
								{ __(
									'No terms found in this taxonomy.',
									'content-ops'
								) }
							</span>
						) }
						{ termOptions.map( ( opt ) => {
							const checked = current.term_ids.includes(
								opt.value
							);
							const id = `co-tax-term-${ current.taxonomy }-${ opt.value }`;
							return (
								<label
									key={ opt.value }
									htmlFor={ id }
									className="co-chip"
								>
									<input
										id={ id }
										type="checkbox"
										checked={ checked }
										onChange={ () =>
											toggleTerm( opt.value )
										}
									/>
									<span>{ opt.label }</span>
								</label>
							);
						} ) }
					</div>
				) }
			</div>
		</FieldShell>
	);
};

const pickField = ( prop ) => {
	if ( prop.widget === 'password' ) {
		return PasswordField;
	}
	if ( prop.widget === 'post_status' ) {
		return PostStatusField;
	}
	if ( prop.widget === 'user' ) {
		return UserField;
	}
	if ( prop.widget === 'taxonomy_terms' ) {
		return TaxonomyTermsField;
	}
	if ( Array.isArray( prop.enum ) && prop.enum.length > 0 ) {
		return EnumField;
	}
	if ( prop.type === 'boolean' ) {
		return BooleanField;
	}
	if ( prop.type === 'integer' ) {
		return IntegerField;
	}
	return StringField;
};

const OperationParamsForm = ( { schema, value, onChange } ) => {
	const ctx = useContext( BuilderContext );
	const catalog = ctx?.catalog;

	if ( ! schema || schema.type !== 'object' || ! schema.properties ) {
		return null;
	}

	const update = ( key, v ) => {
		const next = { ...value };
		if ( v === undefined ) {
			delete next[ key ];
		} else {
			next[ key ] = v;
		}
		onChange( next );
	};

	return (
		<div className="co-op-params">
			{ Object.entries( schema.properties ).map( ( [ key, prop ] ) => {
				const Field = pickField( prop );
				const label = prop.label || humanizeKey( key );
				return (
					<Field
						key={ key }
						prop={ prop }
						value={ value[ key ] }
						catalog={ catalog }
						label={ label }
						description={ prop.description }
						onChange={ ( v ) => update( key, v ) }
					/>
				);
			} ) }
		</div>
	);
};

export default OperationParamsForm;
