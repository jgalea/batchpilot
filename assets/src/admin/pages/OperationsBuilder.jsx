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
		className={ `co-step${ disabled ? ' co-step--disabled' : '' }${
			done ? ' co-step--done' : ''
		}` }
	>
		<div className="co-step__header">
			<span className="co-step__number">{ number }</span>
			<div className="co-step__titles">
				<h2 className="co-step__title">{ title }</h2>
				<p className="co-step__description">{ description }</p>
			</div>
		</div>
		<div className="co-step__body">{ children }</div>
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
			<div className="co-stack">
				<Notice status="error" role="alert">
					{ error.message }
				</Notice>
			</div>
		);
	}
	if ( ! catalog ) {
		return (
			<div className="co-stack">
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
			<div className="co-stack">
				<header className="co-page-header">
					<span className="co-eyebrow">
						{ __( 'Builder', 'content-ops' ) }
					</span>
					<h1>{ __( 'Operations', 'content-ops' ) }</h1>
					<p className="co-page-subtitle">
						{ __(
							'Build a bulk operation in four steps: pick a target, add filters, choose an operation, preview, then execute.',
							'content-ops'
						) }
					</p>
				</header>

				<Step
					number="1"
					title={ __( 'Target', 'content-ops' ) }
					description={ __(
						'What kind of content are you operating on?',
						'content-ops'
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
					title={ __( 'Filters', 'content-ops' ) }
					description={
						state.target
							? __(
									'Narrow the matching set. All filters must match.',
									'content-ops'
							  )
							: __(
									'Filter options unlock once you pick a target.',
									'content-ops'
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
						<p className="co-empty">
							{ __(
								'Pick a target above to add filters.',
								'content-ops'
							) }
						</p>
					) }
				</Step>

				<Step
					number="3"
					title={ __( 'Operation', 'content-ops' ) }
					description={
						state.target
							? __(
									'What should happen to the matched items?',
									'content-ops'
							  )
							: __(
									'Choose Delete, Duplicate, or Bulk edit once a target is selected.',
									'content-ops'
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
								<div className="co-step__subsection">
									<h3 className="co-section-title">
										{ __( 'Parameters', 'content-ops' ) }
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
						<p className="co-empty">
							{ __(
								'Pick a target above to choose an operation.',
								'content-ops'
							) }
						</p>
					) }
				</Step>

				<Step
					number="4"
					title={ __( 'Preview & execute', 'content-ops' ) }
					description={
						state.operation
							? __(
									'Live-preview the match; execute when ready.',
									'content-ops'
							  )
							: __(
									'Preview becomes available once a target and operation are selected.',
									'content-ops'
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
							<div className="co-execute-row">
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
						<p className="co-empty">
							{ __(
								'Complete steps 1–3 to preview and execute.',
								'content-ops'
							) }
						</p>
					) }
				</Step>
			</div>
		</BuilderContext.Provider>
	);
};

export default OperationsBuilder;
