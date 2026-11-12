// Before the tests: the tests' photos and video added to the media library
// once (media.php, fixture.php?media=1: not by tests at once, each adding
// its own copies), and the admin tests' session made once
// (admin-login.php), so the tests that run at once all use it (none logs
// the others out).
const { request } = require( '@playwright/test' );
const path = require( 'path' );

module.exports = async ( config ) => {
	const base = config.projects[ 0 ].use.baseURL;
	const context = await request.newContext( { baseURL: base, ignoreHTTPSErrors: true } );
	const helper = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/admin-login.php';
	const media = await context.get( helper.replace( 'admin-login.php', 'fixture.php' ) + '?media=1', { timeout: 120000 } );
	if ( ! media.ok() ) {
		throw new Error( 'fixture.php?media=1: ' + media.status() );
	}
	const response = await context.get( helper + '?login=1' );
	if ( ! response.ok() ) {
		throw new Error( 'admin-login.php: ' + response.status() );
	}
	await context.dispose();
};
