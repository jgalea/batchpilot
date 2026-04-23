import { getBootstrap } from '../bootstrap';

describe( 'getBootstrap', () => {
	afterEach( () => {
		delete global.window.contentOpsAdmin;
	} );

	it( 'returns window.contentOpsAdmin when present', () => {
		global.window.contentOpsAdmin = {
			namespace: 'content-ops/v1',
			restUrl: 'http://example.test/wp-json/content-ops/v1/',
			nonce: 'abc',
			capabilities: { manage_options: true, content_ops_delete: true },
			pages: {},
		};
		expect( getBootstrap().nonce ).toBe( 'abc' );
	} );

	it( 'throws when bootstrap missing', () => {
		expect( () => getBootstrap() ).toThrow( /contentOpsAdmin/ );
	} );
} );
