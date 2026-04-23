import { __ } from '@wordpress/i18n';

const DESCRIPTIONS = {
	delete: __(
		'Trash or hard-delete matched items. Trashed items can be restored from history.',
		'content-ops'
	),
	duplicate: __(
		'Copy matched items with meta, taxonomies, and featured images. Target status configurable.',
		'content-ops'
	),
	edit: __(
		'Bulk change status, author, dates, taxonomy, comment status, password, or menu order. Changes are snapshotted for undo.',
		'content-ops'
	),
};

const OperationPicker = ( { operations, supported, selected, onSelect } ) => {
	const visible = operations.filter( ( op ) =>
		supported.includes( op.slug )
	);
	return (
		<div className="co-choice-grid" role="group" aria-label="Operation">
			{ visible.map( ( op ) => {
				const isSelected = selected === op.slug;
				return (
					<button
						type="button"
						key={ op.slug }
						className={ `co-choice${
							isSelected ? ' co-choice--selected' : ''
						}` }
						aria-pressed={ isSelected }
						aria-label={ op.label }
						onClick={ () => onSelect( op.slug ) }
					>
						<span className="co-choice__label">{ op.label }</span>
						<span className="co-choice__description">
							{ DESCRIPTIONS[ op.slug ] || '' }
						</span>
						<span className="co-choice__meta">
							<span className="co-chip">{ op.slug }</span>
							{ op.supports_undo && (
								<span className="co-chip co-chip--accent">
									{ __( 'undoable', 'content-ops' ) }
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
