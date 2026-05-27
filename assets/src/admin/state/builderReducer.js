export const initialState = {
	target: null,
	operation: null,
	filters: [],
	params: {},
	preview: null,
	previewing: false,
	previewError: null,
	execution: null,
	executing: false,
};

const newId = () => Math.random().toString( 36 ).slice( 2, 10 );

export const reducer = ( state, action ) => {
	switch ( action.type ) {
		case 'SET_TARGET':
			return { ...initialState, target: action.target };
		case 'SET_OPERATION':
			return {
				...state,
				operation: action.operation,
				params: {},
				preview: null,
			};
		case 'ADD_FILTER':
			return {
				...state,
				filters: [
					...state.filters,
					{
						id: newId(),
						key: action.key || null,
						value: null,
					},
				],
				preview: null,
			};
		case 'UPDATE_FILTER':
			return {
				...state,
				filters: state.filters.map( ( f ) =>
					f.id === action.id ? { ...f, ...action.patch } : f
				),
				preview: null,
			};
		case 'REMOVE_FILTER':
			return {
				...state,
				filters: state.filters.filter( ( f ) => f.id !== action.id ),
				preview: null,
			};
		case 'SET_FILTERS':
			return { ...state, filters: action.filters, preview: null };
		case 'SET_PARAMS':
			return { ...state, params: action.params, preview: null };
		case 'SET_PREVIEWING':
			return {
				...state,
				previewing: action.value,
				previewError: action.value ? null : state.previewError,
			};
		case 'SET_PREVIEW':
			return {
				...state,
				preview: action.preview,
				previewing: false,
				previewError: null,
			};
		case 'SET_PREVIEW_ERROR':
			return {
				...state,
				previewError: action.error,
				previewing: false,
			};
		case 'SET_EXECUTING':
			return { ...state, executing: action.value };
		case 'SET_EXECUTION':
			return {
				...state,
				execution: action.execution,
				executing: false,
			};
		case 'RESET':
			return { ...initialState };
		default:
			return state;
	}
};
