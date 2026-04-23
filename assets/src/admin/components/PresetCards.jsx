import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PresetCards = ( { presets, operationsUrl } ) => {
	if ( ! presets || presets.length === 0 ) {
		return <p>{ __( 'No presets available.', 'content-ops' ) }</p>;
	}
	return (
		<div className="content-ops-preset-grid">
			{ presets.map( ( p ) => {
				const href = `${ operationsUrl }&preset=${ encodeURIComponent(
					p.slug
				) }`;
				return (
					<Card key={ p.slug }>
						<CardHeader>
							<a href={ href }>{ p.label }</a>
						</CardHeader>
						<CardBody>
							<p>{ p.description }</p>
							<p>
								<code>{ p.target }</code> ·{ ' ' }
								<code>{ p.operation }</code>
							</p>
						</CardBody>
					</Card>
				);
			} ) }
		</div>
	);
};

export default PresetCards;
