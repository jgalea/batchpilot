import { getBootstrap } from '../bootstrap';

describe( 'getBootstrap', () => {
	afterEach( () => {
		delete global.window.batchPilotAdmin;
	} );

	it( 'returns window.batchPilotAdmin when present', () => {
		global.window.batchPilotAdmin = {
			namespace: 'batchpilot/v1',
			restUrl: 'http://example.test/wp-json/batchpilot/v1/',
			nonce: 'abc',
			capabilities: { manage_options: true, batchpilot_delete: true },
			pages: {},
		};
		expect( getBootstrap().nonce ).toBe( 'abc' );
	} );

	it( 'throws when bootstrap missing', () => {
		expect( () => getBootstrap() ).toThrow( /batchPilotAdmin/ );
	} );
} );
