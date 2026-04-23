import { initialState, reducer } from '../state/builderReducer';

describe( 'builderReducer', () => {
	it( 'sets the target and clears filters + operation + preview', () => {
		const prev = {
			...initialState,
			target: 'post',
			operation: 'delete',
			filters: [ { id: 'a', key: 'status', value: 'draft' } ],
			preview: { count: 5 },
		};
		const next = reducer( prev, { type: 'SET_TARGET', target: 'page' } );
		expect( next.target ).toBe( 'page' );
		expect( next.filters ).toEqual( [] );
		expect( next.operation ).toBeNull();
		expect( next.preview ).toBeNull();
	} );

	it( 'adds a filter row with a generated id', () => {
		const state = reducer( initialState, { type: 'ADD_FILTER' } );
		expect( state.filters.length ).toBe( 1 );
		expect( state.filters[ 0 ].id ).toBeTruthy();
	} );

	it( 'updates a filter row and invalidates preview', () => {
		const state = {
			...initialState,
			filters: [ { id: 'x', key: null, value: null } ],
			preview: { count: 10, preview_token: 't' },
		};
		const next = reducer( state, {
			type: 'UPDATE_FILTER',
			id: 'x',
			patch: { key: 'status', value: 'draft' },
		} );
		expect( next.filters[ 0 ] ).toEqual( {
			id: 'x',
			key: 'status',
			value: 'draft',
		} );
		expect( next.preview ).toBeNull();
	} );

	it( 'sets preview result', () => {
		const next = reducer( initialState, {
			type: 'SET_PREVIEW',
			preview: {
				count: 3,
				preview_token: 'tok',
				sample_ids: [],
				display_rows: [],
				warnings: [],
			},
		} );
		expect( next.preview.count ).toBe( 3 );
	} );

	it( 'removes a filter', () => {
		const state = {
			...initialState,
			filters: [ { id: 'a' }, { id: 'b' } ],
		};
		expect(
			reducer( state, { type: 'REMOVE_FILTER', id: 'a' } ).filters
		).toEqual( [ { id: 'b' } ] );
	} );

	it( 'SET_FILTERS replaces the whole filter array', () => {
		const next = reducer( initialState, {
			type: 'SET_FILTERS',
			filters: [ { id: 'z', key: 'status', value: 'draft' } ],
		} );
		expect( next.filters ).toHaveLength( 1 );
		expect( next.preview ).toBeNull();
	} );
} );
