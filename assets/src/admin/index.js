import { createElement, render } from '@wordpress/element';

const mount = () => {
	const root = document.getElementById( 'content-ops-admin-root' );
	if ( ! root ) {
		return;
	}
	render( createElement( 'div', null, 'Content Ops admin scaffold loaded.' ), root );
};

if ( document.readyState !== 'loading' ) {
	mount();
} else {
	document.addEventListener( 'DOMContentLoaded', mount );
}
