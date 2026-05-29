import { Button, DropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FilterRow from './FilterRow';

const filterIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="20"
		height="20"
		aria-hidden="true"
	>
		<path
			fill="currentColor"
			d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"
		/>
	</svg>
);

const FilterList = ( { filters, defs, dispatch } ) => {
	const usedKeys = new Set( filters.map( ( f ) => f.key ).filter( Boolean ) );
	const availableDefs = ( defs || [] ).filter(
		( d ) => ! usedKeys.has( d.key )
	);

	const addFilter = ( key ) => dispatch( { type: 'ADD_FILTER', key } );

	const menuControls = availableDefs.map( ( def ) => ( {
		title: def.label,
		onClick: () => addFilter( def.key ),
	} ) );

	return (
		<div className="bp-filter-list">
			{ filters.length === 0 ? (
				<div className="bp-filter-empty" role="status">
					<strong className="bp-filter-empty__title">
						{ __( 'No filters yet', 'batchpilot' ) }
					</strong>
					<span className="bp-filter-empty__hint">
						{ __(
							'Without filters, this operation will match EVERY item of the selected target. Add at least one filter to narrow the set.',
							'batchpilot'
						) }
					</span>
				</div>
			) : (
				<ul className="bp-filter-list__items">
					{ filters.map( ( row ) => (
						<li key={ row.id } className="bp-filter-list__item">
							<FilterRow
								row={ row }
								defs={ defs }
								onChange={ ( patch ) =>
									dispatch( {
										type: 'UPDATE_FILTER',
										id: row.id,
										patch,
									} )
								}
								onRemove={ () =>
									dispatch( {
										type: 'REMOVE_FILTER',
										id: row.id,
									} )
								}
							/>
						</li>
					) ) }
				</ul>
			) }
			<div className="bp-filter-list__actions">
				{ menuControls.length > 0 ? (
					<DropdownMenu
						icon={ filterIcon }
						label={ __( 'Add filter', 'batchpilot' ) }
						text={ __( 'Add filter', 'batchpilot' ) }
						controls={ menuControls }
						toggleProps={ {
							variant: 'secondary',
							className: 'bp-filter-list__add',
						} }
					/>
				) : (
					<Button
						variant="secondary"
						disabled
						className="bp-filter-list__add"
					>
						{ __( 'All filters added', 'batchpilot' ) }
					</Button>
				) }
				{ filters.length > 1 && (
					<span className="bp-filter-list__meta">
						{ __( 'All filters must match (AND).', 'batchpilot' ) }
					</span>
				) }
			</div>
		</div>
	);
};

export default FilterList;
