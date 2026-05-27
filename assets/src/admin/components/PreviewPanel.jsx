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
			<div className="co-preview co-preview--error" role="alert">
				<Notice status="error" isDismissible={ false }>
					{ previewError.message }
				</Notice>
			</div>
		);
	}

	if ( ! preview && previewing ) {
		return (
			<div className="co-preview co-preview--loading" role="status">
				<Spinner />
				<span className="co-preview__hint">
					{ __( 'Calculating match…', 'content-ops' ) }
				</span>
			</div>
		);
	}

	if ( ! preview ) {
		return (
			<div className="co-preview co-preview--placeholder" role="status">
				<span className="co-preview__placeholder-title">
					{ __( 'No preview yet', 'content-ops' ) }
				</span>
				<span className="co-preview__hint">
					{ __(
						'Add or adjust a filter above to see how many items will match. The preview refreshes automatically as you type.',
						'content-ops'
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
		_n( '%s item matched', '%s items matched', count, 'content-ops' ),
		formatCount( count )
	);

	return (
		<div className="co-preview">
			<header className="co-preview__header">
				<div className="co-preview__count">
					<span className="co-preview__count-value">
						{ formatCount( count ) }
					</span>
					<span className="co-preview__count-label">
						{ _n(
							'item matched',
							'items matched',
							count,
							'content-ops'
						) }
					</span>
				</div>
				<div className="co-preview__status">
					{ previewing ? (
						<span className="co-preview__badge co-preview__badge--live">
							<Spinner />
							<span>{ __( 'Updating…', 'content-ops' ) }</span>
						</span>
					) : (
						<span className="co-preview__badge co-preview__badge--fresh">
							<span
								className="co-preview__dot"
								aria-hidden="true"
							/>
							<span>{ __( 'Live', 'content-ops' ) }</span>
						</span>
					) }
				</div>
			</header>

			{ warnings.length > 0 && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="co-preview__warnings"
				>
					<ul>
						{ warnings.map( ( w, i ) => (
							<li key={ i }>{ w }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ rows.length > 0 ? (
				<div className="co-preview__rows">
					<div className="co-preview__rows-header">
						<span>
							{ sprintf(
								/* translators: %s: pre-formatted row count */
								__( 'Showing %s of matches', 'content-ops' ),
								formatCount( rows.length )
							) }
						</span>
						<span className="co-preview__rows-hint">
							{ __(
								'Sample preview. Click a title to verify.',
								'content-ops'
							) }
						</span>
					</div>
					<table className="co-preview__table">
						<thead>
							<tr>
								<th className="co-preview__col-id">
									{ __( 'ID', 'content-ops' ) }
								</th>
								<th>{ __( 'Title', 'content-ops' ) }</th>
								<th className="co-preview__col-status">
									{ __( 'Status', 'content-ops' ) }
								</th>
								<th className="co-preview__col-date">
									{ __( 'Date', 'content-ops' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( r ) => (
								<tr key={ r.id }>
									<td className="co-preview__col-id">
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
														'content-ops'
													) }
											</a>
										) : (
											<span>
												{ r.title ||
													__(
														'(no title)',
														'content-ops'
													) }
											</span>
										) }
									</td>
									<td className="co-preview__col-status">
										<span className="co-chip">
											{ r.status }
										</span>
									</td>
									<td className="co-preview__col-date">
										{ r.date }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : (
				<div className="co-preview__no-rows">
					<span>
						{ count > 0
							? __(
									'Matches found but no sample rows available.',
									'content-ops'
							  )
							: __(
									'No items match these filters. Try relaxing a filter or removing one.',
									'content-ops'
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
