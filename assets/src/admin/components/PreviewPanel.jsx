import {
	Card,
	CardBody,
	CardHeader,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const PreviewPanel = ( { preview, previewing, previewError } ) => {
	if ( previewError ) {
		return (
			<div role="alert">
				<Notice status="error" isDismissible={ false }>
					{ previewError.message }
				</Notice>
			</div>
		);
	}
	if ( previewing && ! preview ) {
		return <Spinner />;
	}
	if ( ! preview ) {
		return (
			<p>{ __( 'Add filters to see a live count.', 'content-ops' ) }</p>
		);
	}

	return (
		<Card>
			<CardHeader>
				{ sprintf(
					/* translators: %d: number of matched items */
					__( 'Matched: %d items', 'content-ops' ),
					preview.count
				) }
				{ previewing && <Spinner /> }
			</CardHeader>
			<CardBody>
				{ preview.warnings && preview.warnings.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						<ul>
							{ preview.warnings.map( ( w, i ) => (
								<li key={ i }>{ w }</li>
							) ) }
						</ul>
					</Notice>
				) }
				<table className="widefat">
					<thead>
						<tr>
							<th>{ __( 'ID', 'content-ops' ) }</th>
							<th>{ __( 'Title', 'content-ops' ) }</th>
							<th>{ __( 'Status', 'content-ops' ) }</th>
							<th>{ __( 'Date', 'content-ops' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ ( preview.display_rows || [] ).map( ( r ) => (
							<tr key={ r.id }>
								<td>{ r.id }</td>
								<td>
									<a href={ r.edit_url || '#' }>
										{ r.title ||
											__( '(no title)', 'content-ops' ) }
									</a>
								</td>
								<td>{ r.status }</td>
								<td>{ r.date }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
		</Card>
	);
};

export default PreviewPanel;
