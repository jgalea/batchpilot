import { __ } from '@wordpress/i18n';

const PresetCards = ( { presets, operationsUrl } ) => {
	if ( ! presets || presets.length === 0 ) {
		return (
			<p className="bp-empty">
				{ __( 'No presets available.', 'batchpilot' ) }
			</p>
		);
	}
	return (
		<div className="bp-preset-cards">
			{ presets.map( ( p ) => {
				const href = `${ operationsUrl }&preset=${ encodeURIComponent(
					p.slug
				) }`;
				return (
					<a key={ p.slug } href={ href } className="bp-preset-card">
						<p className="bp-preset-card__title">{ p.label }</p>
						<p className="bp-preset-card__description">
							{ p.description }
						</p>
						<div className="bp-preset-card__meta">
							<span className="bp-chip">{ p.target }</span>
							<span className="bp-chip">{ p.operation }</span>
						</div>
					</a>
				);
			} ) }
		</div>
	);
};

export default PresetCards;
