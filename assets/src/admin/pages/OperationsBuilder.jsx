import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { createApi, normalizeError } from '../api';
import { useCatalog } from '../hooks/useCatalog';
import { useDebouncedPreview } from '../hooks/useDebouncedPreview';
import { getBootstrap } from '../bootstrap';
import { BuilderContext } from '../state/builderContext';
import { reducer, initialState } from '../state/builderReducer';
import TargetPicker from '../components/TargetPicker';
import FilterList from '../components/FilterList';
import OperationPicker from '../components/OperationPicker';
import OperationParamsForm from '../components/OperationParamsForm';
import PreviewPanel from '../components/PreviewPanel';
import ExecuteButton from '../components/ExecuteButton';
import ExecutionResult from '../components/ExecutionResult';

const filtersToArgs = ( rows ) => {
	const out = {};
	for ( const row of rows ) {
		if (
			row.key &&
			row.value !== null &&
			row.value !== undefined &&
			row.value !== ''
		) {
			out[ row.key ] = row.value;
		}
	}
	return out;
};

const OperationsBuilder = ( { api = createApi() } ) => {
	const { catalog, error } = useCatalog( api );
	const [ state, dispatch ] = useReducer( reducer, initialState );
	const { preview, previewing, previewError } = useDebouncedPreview(
		api,
		state,
		filtersToArgs
	);

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

	const bootstrap = getBootstrap();

	const execute = async () => {
		if ( ! preview || ! preview.preview_token ) {
			return;
		}
		dispatch( { type: 'SET_EXECUTING', value: true } );
		try {
			const res = await api.execute( {
				preview_token: preview.preview_token,
				target: state.target,
				operation: state.operation,
				filters: filtersToArgs( state.filters ),
				params: state.params,
			} );
			dispatch( { type: 'SET_EXECUTION', execution: res } );
		} catch ( err ) {
			dispatch( {
				type: 'SET_PREVIEW_ERROR',
				error: normalizeError( err ),
			} );
			dispatch( { type: 'SET_EXECUTING', value: false } );
		}
	};

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
				{ state.target && (
					<section>
						<h2>{ __( 'Operation', 'content-ops' ) }</h2>
						<OperationPicker
							operations={ catalog.operations }
							supported={ [ 'delete', 'duplicate', 'edit' ] }
							selected={ state.operation }
							onSelect={ ( slug ) =>
								dispatch( {
									type: 'SET_OPERATION',
									operation: slug,
								} )
							}
						/>
						{ state.operation && (
							<OperationParamsForm
								schema={
									(
										catalog.operations.find(
											( o ) => o.slug === state.operation
										) || {}
									).params_schema
								}
								value={ state.params }
								onChange={ ( params ) =>
									dispatch( {
										type: 'SET_PARAMS',
										params,
									} )
								}
							/>
						) }
					</section>
				) }
				{ state.operation && (
					<section>
						<h2>{ __( 'Preview & Execute', 'content-ops' ) }</h2>
						<PreviewPanel
							preview={ preview }
							previewing={ previewing }
							previewError={ previewError }
						/>
						<ExecuteButton
							preview={ preview }
							operation={ state.operation }
							onExecute={ execute }
							executing={ state.executing }
						/>
						{ state.execution && (
							<ExecutionResult
								execution={ state.execution }
								historyUrl={ bootstrap.pages.history }
							/>
						) }
					</section>
				) }
			</div>
		</BuilderContext.Provider>
	);
};

export default OperationsBuilder;
