import { __ } from '@wordpress/i18n';

const filterCountLabel = ( count ) => {
	if ( count === 1 ) {
		return __( '1 filter available', 'content-ops' );
	}
	return `${ count } ${ __( 'filters available', 'content-ops' ) }`;
};

const TargetPicker = ( { targets, selected, onSelect } ) => (
	<div className="co-choice-grid" role="group" aria-label="Target">
		{ targets.map( ( t ) => {
			const isSelected = selected === t.slug;
			return (
				<button
					type="button"
					key={ t.slug }
					className={ `co-choice${
						isSelected ? ' co-choice--selected' : ''
					}` }
					aria-pressed={ isSelected }
					aria-label={ t.label }
					onClick={ () => onSelect( t.slug ) }
				>
					<span className="co-choice__label">{ t.label }</span>
					<span className="co-choice__meta">
						<span className="co-chip">{ t.slug }</span>
						<span className="co-choice__hint">
							{ filterCountLabel(
								( t.filters || [] ).length
							) }
						</span>
					</span>
				</button>
			);
		} ) }
	</div>
);

export default TargetPicker;
