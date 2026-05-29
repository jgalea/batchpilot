import { __ } from '@wordpress/i18n';

const DESCRIPTIONS = {
	delete: __(
		'Trash or hard-delete matched items. Trashed items can be restored from history.',
		'batchpilot'
	),
	duplicate: __(
		'Copy matched items with meta, taxonomies, and featured images. Target status configurable.',
		'batchpilot'
	),
	edit: __(
		'Bulk change status, author, dates, taxonomy, comment status, password, or menu order. Changes are snapshotted for undo.',
		'batchpilot'
	),
};

const OperationPicker = ( { operations, supported, selected, onSelect } ) => {
	const visible = operations.filter( ( op ) =>
		supported.includes( op.slug )
	);
	return (
		<div className="bp-choice-grid" role="group" aria-label="Operation">
			{ visible.map( ( op ) => {
				const isSelected = selected === op.slug;
				return (
					<button
						type="button"
						key={ op.slug }
						className={ `bp-choice${
							isSelected ? ' bp-choice--selected' : ''
						}` }
						aria-pressed={ isSelected }
						aria-label={ op.label }
						onClick={ () => onSelect( op.slug ) }
					>
						<span className="bp-choice__label">{ op.label }</span>
						<span className="bp-choice__description">
							{ DESCRIPTIONS[ op.slug ] || '' }
						</span>
						<span className="bp-choice__meta">
							<span className="bp-chip">{ op.slug }</span>
							{ op.supports_undo && (
								<span className="bp-chip bp-chip--accent">
									{ __( 'undoable', 'batchpilot' ) }
								</span>
							) }
						</span>
					</button>
				);
			} ) }
		</div>
	);
};

export default OperationPicker;
