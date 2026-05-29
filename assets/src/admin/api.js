import apiFetch from '@wordpress/api-fetch';

export const normalizeError = ( err ) => {
	if ( err && err.name === 'AbortError' ) {
		return {
			code: 'bp.client.aborted',
			message: 'Request aborted.',
			status: 0,
			context: {},
			aborted: true,
		};
	}
	if ( err && err.code ) {
		return {
			code: err.code,
			message: err.message || '',
			status: ( err.data && err.data.status ) || 0,
			context: err.data && err.data.context ? err.data.context : {},
		};
	}
	return {
		code: 'bp.client.unknown',
		message: ( err && err.message ) || String( err ),
		status: 0,
		context: {},
	};
};

export const createApi = ( fetchFn = apiFetch ) => {
	const call = ( path, method, data, signal ) =>
		fetchFn( {
			path: `/batchpilot/v1${ path }`,
			method,
			...( data !== undefined ? { data } : {} ),
			...( signal ? { signal } : {} ),
		} );

	return {
		fetchCatalog: ( signal ) =>
			call( '/catalog', 'GET', undefined, signal ),
		preview: ( body, signal ) => call( '/preview', 'POST', body, signal ),
		execute: ( body, signal ) => call( '/execute', 'POST', body, signal ),
		listOperations: ( { limit = 20, offset = 0 } = {}, signal ) =>
			call(
				`/operations?limit=${ limit }&offset=${ offset }`,
				'GET',
				undefined,
				signal
			),
		getOperation: ( id, signal ) =>
			call( `/operations/${ id }`, 'GET', undefined, signal ),
		undoOperation: ( id, signal ) =>
			call( `/operations/${ id }/undo`, 'POST', undefined, signal ),
		fetchDoctor: ( signal ) => call( '/doctor', 'GET', undefined, signal ),
		getSettings: ( signal ) =>
			call( '/settings', 'GET', undefined, signal ),
		saveSettings: ( body, signal ) =>
			call( '/settings', 'POST', body, signal ),
	};
};

export const defaultApi = createApi();
