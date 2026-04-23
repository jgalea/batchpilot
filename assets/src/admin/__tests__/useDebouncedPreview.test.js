import { renderHook, act, waitFor } from '@testing-library/react';
import { useDebouncedPreview } from '../hooks/useDebouncedPreview';

jest.useFakeTimers();

const filtersToArgs = ( filters ) =>
	Object.fromEntries(
		filters.filter( ( f ) => f.key ).map( ( f ) => [ f.key, f.value ] )
	);

describe( 'useDebouncedPreview', () => {
	afterEach( () => jest.clearAllTimers() );

	it( 'debounces 300ms and calls api.preview with filters object', async () => {
		const api = {
			preview: jest.fn().mockResolvedValue( {
				count: 2,
				sample_ids: [ 1, 2 ],
				preview_token: 't',
				warnings: [],
				display_rows: [],
			} ),
		};
		const { result, rerender } = renderHook(
			( { state } ) => useDebouncedPreview( api, state, filtersToArgs ),
			{
				initialProps: {
					state: {
						target: 'post',
						operation: 'delete',
						filters: [],
						params: {},
					},
				},
			}
		);

		rerender( {
			state: {
				target: 'post',
				operation: 'delete',
				filters: [ { id: 'a', key: 'status', value: 'draft' } ],
				params: {},
			},
		} );
		expect( api.preview ).not.toHaveBeenCalled();

		act( () => jest.advanceTimersByTime( 299 ) );
		expect( api.preview ).not.toHaveBeenCalled();

		act( () => jest.advanceTimersByTime( 10 ) );
		await waitFor( () => expect( api.preview ).toHaveBeenCalledTimes( 1 ) );
		expect( api.preview ).toHaveBeenCalledWith(
			{
				target: 'post',
				operation: 'delete',
				filters: { status: 'draft' },
				params: {},
			},
			expect.any( AbortSignal )
		);
		expect( result.current.preview.count ).toBe( 2 );
	} );

	it( 'does nothing until target + operation are both set', () => {
		const api = { preview: jest.fn() };
		renderHook( () =>
			useDebouncedPreview(
				api,
				{
					target: null,
					operation: null,
					filters: [],
					params: {},
				},
				filtersToArgs
			)
		);
		act( () => jest.advanceTimersByTime( 500 ) );
		expect( api.preview ).not.toHaveBeenCalled();
	} );
} );
