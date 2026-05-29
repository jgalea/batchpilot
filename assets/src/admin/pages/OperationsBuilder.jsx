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

const Step = ( { number, title, description, done, disabled, children } ) => (
	<section
		className={ `bp-step${ disabled ? ' bp-step--disabled' : '' }${
			done ? ' bp-step--done' : ''
		}` }
	>
		<div className="bp-step__header">
			<span className="bp-step__number">{ number }</span>
			<div className="bp-step__titles">
				<h2 className="bp-step__title">{ title }</h2>
				<p className="bp-step__description">{ description }</p>
			</div>
		</div>
		<div className="bp-step__body">{ children }</div>
	</section>
);

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
			<div className="bp-stack">
				<Notice status="error" role="alert">
					{ error.message }
				</Notice>
			</div>
		);
	}
	if ( ! catalog ) {
		return (
			<div className="bp-stack">
				<Spinner />
			</div>
		);
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

	const selectedTarget = catalog.targets.find(
		( t ) => t.slug === state.target
	);
	const selectedOperation = catalog.operations.find(
		( o ) => o.slug === state.operation
	);

	return (
		<BuilderContext.Provider value={ { state, dispatch, catalog, api } }>
			<div className="bp-stack">
				<header className="bp-page-header">
					<span className="bp-eyebrow">
						{ __( 'Builder', 'batchpilot' ) }
					</span>
					<h1>{ __( 'Operations', 'batchpilot' ) }</h1>
					<p className="bp-page-subtitle">
						{ __(
							'Build a bulk operation in four steps: pick a target, add filters, choose an operation, preview, then execute.',
							'batchpilot'
						) }
					</p>
				</header>

				<Step
					number="1"
					title={ __( 'Target', 'batchpilot' ) }
					description={ __(
						'What kind of content are you operating on?',
						'batchpilot'
					) }
					done={ !! state.target }
				>
					<TargetPicker
						targets={ catalog.targets }
						selected={ state.target }
						onSelect={ ( slug ) =>
							dispatch( { type: 'SET_TARGET', target: slug } )
						}
					/>
				</Step>

				<Step
					number="2"
					title={ __( 'Filters', 'batchpilot' ) }
					description={
						state.target
							? __(
									'Narrow the matching set. All filters must match.',
									'batchpilot'
							  )
							: __(
									'Filter options unlock once you pick a target.',
									'batchpilot'
							  )
					}
					disabled={ ! state.target }
					done={ state.target && state.filters.length > 0 }
				>
					{ state.target ? (
						<FilterList
							filters={ state.filters }
							defs={
								( selectedTarget || { filters: [] } ).filters
							}
							dispatch={ dispatch }
						/>
					) : (
						<p className="bp-empty">
							{ __(
								'Pick a target above to add filters.',
								'batchpilot'
							) }
						</p>
					) }
				</Step>

				<Step
					number="3"
					title={ __( 'Operation', 'batchpilot' ) }
					description={
						state.target
							? __(
									'What should happen to the matched items?',
									'batchpilot'
							  )
							: __(
									'Choose Delete, Duplicate, or Bulk edit once a target is selected.',
									'batchpilot'
							  )
					}
					disabled={ ! state.target }
					done={ !! state.operation }
				>
					{ state.target ? (
						<>
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
								<div className="bp-step__subsection">
									<h3 className="bp-section-title">
										{ __( 'Parameters', 'batchpilot' ) }
									</h3>
									<OperationParamsForm
										schema={
											( selectedOperation || {} )
												.params_schema
										}
										value={ state.params }
										onChange={ ( params ) =>
											dispatch( {
												type: 'SET_PARAMS',
												params,
											} )
										}
									/>
								</div>
							) }
						</>
					) : (
						<p className="bp-empty">
							{ __(
								'Pick a target above to choose an operation.',
								'batchpilot'
							) }
						</p>
					) }
				</Step>

				<Step
					number="4"
					title={ __( 'Preview & execute', 'batchpilot' ) }
					description={
						state.operation
							? __(
									'Live-preview the match; execute when ready.',
									'batchpilot'
							  )
							: __(
									'Preview becomes available once a target and operation are selected.',
									'batchpilot'
							  )
					}
					disabled={ ! state.operation }
				>
					{ state.operation ? (
						<>
							<PreviewPanel
								preview={ preview }
								previewing={ previewing }
								previewError={ previewError }
							/>
							<div className="bp-execute-row">
								<ExecuteButton
									preview={ preview }
									operation={ state.operation }
									onExecute={ execute }
									executing={ state.executing }
									hasFilters={
										Object.keys(
											filtersToArgs( state.filters )
										).length > 0
									}
								/>
							</div>
							{ state.execution && (
								<ExecutionResult
									execution={ state.execution }
									historyUrl={ bootstrap.pages.history }
								/>
							) }
						</>
					) : (
						<p className="bp-empty">
							{ __(
								'Complete steps 1–3 to preview and execute.',
								'batchpilot'
							) }
						</p>
					) }
				</Step>
			</div>
		</BuilderContext.Provider>
	);
};

export default OperationsBuilder;
