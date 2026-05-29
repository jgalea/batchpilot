import apiFetch from '@wordpress/api-fetch';
import { createApi, normalizeError } from '../api';

jest.mock( '@wordpress/api-fetch' );

describe( 'api', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'fetches catalog via GET /catalog', async () => {
		apiFetch.mockResolvedValue( {
			targets: [],
			operations: [],
			presets: [],
		} );
		const api = createApi();
		const result = await api.fetchCatalog();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/batchpilot/v1/catalog',
				method: 'GET',
			} )
		);
		expect( result.targets ).toEqual( [] );
	} );

	it( 'sends preview body and forwards signal for aborting', async () => {
		apiFetch.mockResolvedValue( {
			count: 3,
			sample_ids: [ 1, 2, 3 ],
			preview_token: 'tok',
			warnings: [],
			display_rows: [],
		} );
		const api = createApi();
		const controller = new AbortController();
		await api.preview(
			{
				target: 'post',
				operation: 'delete',
				filters: { status: [ 'draft' ] },
				params: {},
			},
			controller.signal
		);

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/batchpilot/v1/preview',
				method: 'POST',
				signal: controller.signal,
				data: {
					target: 'post',
					operation: 'delete',
					filters: { status: [ 'draft' ] },
					params: {},
				},
			} )
		);
	} );

	it( 'normalizes errors with a code, message, and context', () => {
		const err = normalizeError( {
			code: 'bp.preview.stale_token',
			message: 'Preview token invalid or expired.',
			data: { status: 409 },
		} );
		expect( err ).toEqual( {
			code: 'bp.preview.stale_token',
			message: 'Preview token invalid or expired.',
			status: 409,
			context: {},
		} );
	} );

	it( 'handles DOMException AbortError as aborted=true', () => {
		const abortErr = new DOMException( 'aborted', 'AbortError' );
		expect( normalizeError( abortErr ).aborted ).toBe( true );
	} );
} );
