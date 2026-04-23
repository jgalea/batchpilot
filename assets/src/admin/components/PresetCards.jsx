import { __ } from '@wordpress/i18n';

const PresetCards = ( { presets, operationsUrl } ) => {
	if ( ! presets || presets.length === 0 ) {
		return (
			<p className="co-empty">
				{ __( 'No presets available.', 'content-ops' ) }
			</p>
		);
	}
	return (
		<div className="co-preset-cards">
			{ presets.map( ( p ) => {
				const href = `${ operationsUrl }&preset=${ encodeURIComponent(
					p.slug
				) }`;
				return (
					<a key={ p.slug } href={ href } className="co-preset-card">
						<p className="co-preset-card__title">{ p.label }</p>
						<p className="co-preset-card__description">
							{ p.description }
						</p>
						<div className="co-preset-card__meta">
							<span className="co-chip">{ p.target }</span>
							<span className="co-chip">{ p.operation }</span>
						</div>
					</a>
				);
			} ) }
		</div>
	);
};

export default PresetCards;
