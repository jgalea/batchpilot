import { useEffect, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { defaultApi, normalizeError } from '../api';
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

const readQuery = () =>
	typeof window === 'undefined'
		? new URLSearchParams()
		: new URLSearchParams( window.location.search );

const newRowId = () => Math.random().toString( 36 ).slice( 2, 10 );

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

const OperationsBuilder = ( { api = defaultApi } ) => {
	const { catalog, error } = useCatalog( api );
	const [ state, dispatch ] = useReducer( reducer, initialState );
	const { preview, previewing, previewError } = useDebouncedPreview(
		api,
		state,
		filtersToArgs
	);

	useEffect( () => {
		if ( ! catalog ) {
			return;
		}
		const query = readQuery();
		const presetSlug = query.get( 'preset' );
		if ( presetSlug ) {
			const preset = ( catalog.presets || [] ).find(
				( p ) => p.slug === presetSlug
			);
			if ( preset ) {
				dispatch( { type: 'SET_TARGET', target: preset.target } );
				dispatch( {
					type: 'SET_OPERATION',
					operation: preset.operation,
				} );
				dispatch( {
					type: 'SET_FILTERS',
					filters: Object.entries( preset.filters || {} ).map(
						( [ key, value ] ) => ( {
							id: newRowId(),
							key,
							value,
						} )
					),
				} );
				dispatch( {
					type: 'SET_PARAMS',
					params: preset.params || {},
				} );
				return;
			}
		}

		const rerunId = query.get( 'rerun' );
		if ( rerunId ) {
			api.getOperation( parseInt( rerunId, 10 ) )
				.then( ( op ) => {
					dispatch( { type: 'SET_TARGET', target: op.target } );
					dispatch( { type: 'SET_OPERATION', operation: op.type } );
					dispatch( {
						type: 'SET_FILTERS',
						filters: Object.entries( op.filters || {} ).map(
							( [ key, value ] ) => ( {
								id: newRowId(),
								key,
								value,
							} )
						),
					} );
					dispatch( {
						type: 'SET_PARAMS',
						params: op.params || {},
					} );
				} )
				.catch( () => {} );
			return;
		}

		const urlTarget = query.get( 'target' );
		const urlOperation = query.get( 'operation' );
		if ( urlTarget && urlOperation ) {
			dispatch( { type: 'SET_TARGET', target: urlTarget } );
			dispatch( { type: 'SET_OPERATION', operation: urlOperation } );
			const urlIds = query
				.getAll( 'filters[ids][]' )
				.map( ( v ) => parseInt( v, 10 ) )
				.filter( ( n ) => ! Number.isNaN( n ) );
			if ( urlIds.length > 0 ) {
				dispatch( {
					type: 'SET_FILTERS',
					filters: [ { id: newRowId(), key: 'ids', value: urlIds } ],
				} );
			}
		}
	}, [ catalog ] ); // eslint-disable-line react-hooks/exhaustive-deps

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
