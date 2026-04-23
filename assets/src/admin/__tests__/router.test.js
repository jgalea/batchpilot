/* eslint-env browser */
import { detectPage } from '../router';

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

const mountRoot = ( id ) => {
	const div = document.createElement( 'div' );
	div.id = id;
	document.body.appendChild( div );
};

describe( 'detectPage', () => {
	beforeEach( clearBody );

	it( 'returns dashboard page when dashboard root is present', () => {
		mountRoot( 'content-ops-dashboard-root' );
		const result = detectPage( document );
		expect( result.page ).toBe( 'dashboard' );
		expect( result.mount ).toBeInstanceOf( HTMLElement );
	} );

	it( 'returns operations page when operations root is present', () => {
		mountRoot( 'content-ops-operations-root' );
		expect( detectPage( document ).page ).toBe( 'operations' );
	} );

	it( 'returns history page when history root is present', () => {
		mountRoot( 'content-ops-history-root' );
		expect( detectPage( document ).page ).toBe( 'history' );
	} );

	it( 'returns settings page when settings root is present', () => {
		mountRoot( 'content-ops-settings-root' );
		expect( detectPage( document ).page ).toBe( 'settings' );
	} );

	it( 'returns null when no root is present', () => {
		expect( detectPage( document ) ).toBeNull();
	} );
} );
