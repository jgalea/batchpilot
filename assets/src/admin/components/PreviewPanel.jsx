import { Spinner, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const formatCount = ( n ) => {
	try {
		return new Intl.NumberFormat().format( n );
	} catch ( e ) {
		return String( n );
	}
};

const PreviewPanel = ( { preview, previewing, previewError } ) => {
	if ( previewError ) {
		return (
			<div className="bp-preview bp-preview--error" role="alert">
				<Notice status="error" isDismissible={ false }>
					{ previewError.message }
				</Notice>
			</div>
		);
	}

	if ( ! preview && previewing ) {
		return (
			<div className="bp-preview bp-preview--loading" role="status">
				<Spinner />
				<span className="bp-preview__hint">
					{ __( 'Calculating match…', 'batchpilot' ) }
				</span>
			</div>
		);
	}

	if ( ! preview ) {
		return (
			<div className="bp-preview bp-preview--placeholder" role="status">
				<span className="bp-preview__placeholder-title">
					{ __( 'No preview yet', 'batchpilot' ) }
				</span>
				<span className="bp-preview__hint">
					{ __(
						'Add or adjust a filter above to see how many items will match. The preview refreshes automatically as you type.',
						'batchpilot'
					) }
				</span>
			</div>
		);
	}

	const count = preview.count || 0;
	const rows = preview.display_rows || [];
	const warnings = preview.warnings || [];

	const countLabel = sprintf(
		/* translators: %s: number of matched items, pre-formatted */
		_n( '%s item matched', '%s items matched', count, 'batchpilot' ),
		formatCount( count )
	);

	return (
		<div className="bp-preview">
			<header className="bp-preview__header">
				<div className="bp-preview__count">
					<span className="bp-preview__count-value">
						{ formatCount( count ) }
					</span>
					<span className="bp-preview__count-label">
						{ _n(
							'item matched',
							'items matched',
							count,
							'batchpilot'
						) }
					</span>
				</div>
				<div className="bp-preview__status">
					{ previewing ? (
						<span className="bp-preview__badge bp-preview__badge--live">
							<Spinner />
							<span>{ __( 'Updating…', 'batchpilot' ) }</span>
						</span>
					) : (
						<span className="bp-preview__badge bp-preview__badge--fresh">
							<span
								className="bp-preview__dot"
								aria-hidden="true"
							/>
							<span>{ __( 'Live', 'batchpilot' ) }</span>
						</span>
					) }
				</div>
			</header>

			{ warnings.length > 0 && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="bp-preview__warnings"
				>
					<ul>
						{ warnings.map( ( w, i ) => (
							<li key={ i }>{ w }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ rows.length > 0 ? (
				<div className="bp-preview__rows">
					<div className="bp-preview__rows-header">
						<span>
							{ sprintf(
								/* translators: %s: pre-formatted row count */
								__( 'Showing %s of matches', 'batchpilot' ),
								formatCount( rows.length )
							) }
						</span>
						<span className="bp-preview__rows-hint">
							{ __(
								'Sample preview. Click a title to verify.',
								'batchpilot'
							) }
						</span>
					</div>
					<table className="bp-preview__table">
						<thead>
							<tr>
								<th className="bp-preview__col-id">
									{ __( 'ID', 'batchpilot' ) }
								</th>
								<th>{ __( 'Title', 'batchpilot' ) }</th>
								<th className="bp-preview__col-status">
									{ __( 'Status', 'batchpilot' ) }
								</th>
								<th className="bp-preview__col-date">
									{ __( 'Date', 'batchpilot' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( r ) => (
								<tr key={ r.id }>
									<td className="bp-preview__col-id">
										{ r.id }
									</td>
									<td>
										{ r.edit_url ? (
											<a
												href={ r.edit_url }
												target="_blank"
												rel="noreferrer"
											>
												{ r.title ||
													__(
														'(no title)',
														'batchpilot'
													) }
											</a>
										) : (
											<span>
												{ r.title ||
													__(
														'(no title)',
														'batchpilot'
													) }
											</span>
										) }
									</td>
									<td className="bp-preview__col-status">
										<span className="bp-chip">
											{ r.status }
										</span>
									</td>
									<td className="bp-preview__col-date">
										{ r.date }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : (
				<div className="bp-preview__no-rows">
					<span>
						{ count > 0
							? __(
									'Matches found but no sample rows available.',
									'batchpilot'
							  )
							: __(
									'No items match these filters. Try relaxing a filter or removing one.',
									'batchpilot'
							  ) }
					</span>
				</div>
			) }

			<span className="sr-only" aria-live="polite">
				{ countLabel }
			</span>
		</div>
	);
};

export default PreviewPanel;
