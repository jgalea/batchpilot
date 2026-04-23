import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { createApi } from '../api';
import { useCatalog } from '../hooks/useCatalog';
import { BuilderContext } from '../state/builderContext';
import { reducer, initialState } from '../state/builderReducer';
import TargetPicker from '../components/TargetPicker';
import FilterList from '../components/FilterList';

const OperationsBuilder = ( { api = createApi() } ) => {
	const { catalog, error } = useCatalog( api );
	const [ state, dispatch ] = useReducer( reducer, initialState );

	if ( error ) {
		return (
			<Notice status="error" role="alert">
				{ error.message }
			</Notice>
		);
	}
	if ( ! catalog ) {
		return <Spinner />;
	}

	return (
		<BuilderContext.Provider value={ { state, dispatch, catalog, api } }>
			<div>
				<h1>{ __( 'Operations Builder', 'content-ops' ) }</h1>
				<section>
					<h2>{ __( 'Target', 'content-ops' ) }</h2>
					<TargetPicker
						targets={ catalog.targets }
						selected={ state.target }
						onSelect={ ( slug ) =>
							dispatch( { type: 'SET_TARGET', target: slug } )
						}
					/>
				</section>
				{ state.target && (
					<section>
						<h2>{ __( 'Filters', 'content-ops' ) }</h2>
						<FilterList
							filters={ state.filters }
							defs={
								(
									catalog.targets.find(
										( t ) => t.slug === state.target
									) || { filters: [] }
								).filters
							}
							dispatch={ dispatch }
						/>
					</section>
				) }
			</div>
		</BuilderContext.Provider>
	);
};

export default OperationsBuilder;
