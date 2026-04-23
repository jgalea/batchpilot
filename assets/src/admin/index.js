import { createElement, render } from '@wordpress/element';
import { detectPage } from './router';
import Dashboard from './pages/Dashboard';
import OperationsBuilder from './pages/OperationsBuilder';
import History from './pages/History';
import Settings from './pages/Settings';
import './styles.scss';

const PAGES = {
	dashboard: Dashboard,
	operations: OperationsBuilder,
	history: History,
	settings: Settings,
};

const mount = () => {
	const hit = detectPage( document );
	if ( ! hit ) {
		return;
	}
	const Component = PAGES[ hit.page ];
	if ( ! Component ) {
		return;
	}
	render( createElement( Component ), hit.mount );
};

if ( document.readyState !== 'loading' ) {
	mount();
} else {
	document.addEventListener( 'DOMContentLoaded', mount );
}
