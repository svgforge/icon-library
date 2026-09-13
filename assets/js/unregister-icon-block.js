/**
 * Deregisters the core Icon block inside the block editor.
 *
 * WordPress registers the core block types client-side (including core/icon)
 * independently of the server-side block registry. Blocks that are already
 * saved in content would therefore keep working in the editor even after
 * the server-side unregister_block_type() call. Removing the block type here
 * makes those blocks unavailable in the editor too.
 *
 * @param {Object} wp
 * @package
 */
( function ( wp ) {
	if ( ! window.wp || ! wp.domReady || ! wp.blocks ) {
		return;
	}

	wp.domReady( function () {
		if ( wp.blocks.getBlockType( 'core/icon' ) ) {
			wp.blocks.unregisterBlockType( 'core/icon' );
		}
	} );
} )( window.wp );
