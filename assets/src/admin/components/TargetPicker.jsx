import { __ } from '@wordpress/i18n';

const filterCountLabel = ( count ) => {
	if ( count === 1 ) {
		return __( '1 filter available', 'batchpilot' );
	}
	return `${ count } ${ __( 'filters available', 'batchpilot' ) }`;
};

const TargetPicker = ( { targets, selected, onSelect } ) => (
	<div className="bp-choice-grid" role="group" aria-label="Target">
		{ targets.map( ( t ) => {
			const isSelected = selected === t.slug;
			return (
				<button
					type="button"
					key={ t.slug }
					className={ `bp-choice${
						isSelected ? ' bp-choice--selected' : ''
					}` }
					aria-pressed={ isSelected }
					aria-label={ t.label }
					onClick={ () => onSelect( t.slug ) }
				>
					<span className="bp-choice__label">{ t.label }</span>
					<span className="bp-choice__meta">
						<span className="bp-chip">{ t.slug }</span>
						<span className="bp-choice__hint">
							{ filterCountLabel( ( t.filters || [] ).length ) }
						</span>
					</span>
				</button>
			);
		} ) }
	</div>
);

export default TargetPicker;
