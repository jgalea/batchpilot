const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		path.join( __dirname, 'tests/js/setup-jest-dom.js' ),
	],
};
