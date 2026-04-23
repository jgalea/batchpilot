import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FilterRow from './FilterRow';

const FilterList = ( { filters, defs, dispatch } ) => (
	<div className="content-ops-filter-list">
		{ filters.map( ( row ) => (
			<FilterRow
				key={ row.id }
				row={ row }
				defs={ defs }
				onChange={ ( patch ) =>
					dispatch( { type: 'UPDATE_FILTER', id: row.id, patch } )
				}
				onRemove={ () =>
					dispatch( { type: 'REMOVE_FILTER', id: row.id } )
				}
			/>
		) ) }
		<Button
			variant="secondary"
			onClick={ () => dispatch( { type: 'ADD_FILTER' } ) }
		>
			{ __( 'Add filter', 'content-ops' ) }
		</Button>
		<p className="description">
			{ __( 'Match mode: all filters must match.', 'content-ops' ) }
		</p>
	</div>
);

export default FilterList;
