const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

// A YouTube / Vimeo player listens for its iframe's messages only while it has
// one, and stops when destroyed (the lightbox's players, each time it closes).
test( 'an embedded player leaves no message listener behind', async ( { page } ) => {
	// No real player is needed: its address is never loaded.
	await page.route( /youtube|vimeo/, route => route.abort() );
	await page.goto( fixture + '?case=slide' );
	const counts = await page.evaluate( () => {
		let listening = 0;
		const add = window.addEventListener;
		const remove = window.removeEventListener;
		window.addEventListener = function ( type ) {
			listening += type === 'message' ? 1 : 0;
			return add.apply( this, arguments );
		};
		window.removeEventListener = function ( type ) {
			listening -= type === 'message' ? 1 : 0;
			return remove.apply( this, arguments );
		};
		const result = [];
		for ( let i = 0; i < 5; i++ ) {
			const box = document.body.appendChild( document.createElement( 'div' ) );
			const player = window.wpSwiperShared.embedPlayer( box, { provider: 'youtube', id: 'abc' }, { muted: true, loop: false, controls: true } );
			result.push( listening );
			player.play();
			result.push( listening );
			player.pause();
			player.destroy();
			box.remove();
			result.push( listening );
		}
		window.addEventListener = add;
		window.removeEventListener = remove;
		return result;
	} );
	// Made: none; playing: one; destroyed: none, five times over.
	expect( counts ).toEqual( [ 0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0 ] );
} );
