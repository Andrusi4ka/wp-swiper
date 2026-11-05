// Browser tests of WP Swiper (see README.md): the pages of fixture.php on
// the local site, in the installed Chrome, at a desktop and a phone size.
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	testMatch: '*.spec.js',
	// The admin tests' session, made once before they run (admin-login.php).
	globalSetup: require.resolve( './global-setup.js' ),
	snapshotPathTemplate: '{testDir}/snapshots/{projectName}/{arg}{ext}',
	timeout: 30000,
	fullyParallel: true,
	workers: 4,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_SWIPER_SITE || 'https://creativity.local',
		channel: 'chrome',
		ignoreHTTPSErrors: true,
		reducedMotion: 'reduce',
	},
	projects: [
		{ name: 'desktop', use: { viewport: { width: 1280, height: 800 } } },
		{ name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
	],
} );
