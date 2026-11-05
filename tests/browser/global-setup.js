// Before the tests: the admin tests' session made once (admin-login.php),
// so the tests that run at once all use it (none logs the others out).
const { request } = require( '@playwright/test' );
const path = require( 'path' );

module.exports = async ( config ) => {
	const base = config.projects[ 0 ].use.baseURL;
	const context = await request.newContext( { baseURL: base, ignoreHTTPSErrors: true } );
	const helper = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/admin-login.php';
	const response = await context.get( helper + '?login=1' );
	if ( ! response.ok() ) {
		throw new Error( 'admin-login.php: ' + response.status() );
	}
	await context.dispose();
};
