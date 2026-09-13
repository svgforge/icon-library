/**
 * Unit tests for the color helpers in icon-colors.ts.
 *
 * @package
 */

import { isColoredSymbol, parseSymbols, symbolColorCount } from './icon-colors';

const sprite = ( inner: string ): string => `<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24">
	${ inner }
</svg>`;

const symbolOf = ( inner: string ): SVGElement => {
	const doc = new window.DOMParser().parseFromString(
		sprite( inner ),
		'image/svg+xml'
	);
	return doc.querySelector( 'symbol' ) as SVGElement;
};

describe( 'symbolColorCount', () => {
	it( 'counts distinct fill/stroke colors on elements', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24"><path fill="#d3d7cf"/><path stroke="#204a87"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 2 );
	} );

	it( 'does not count special values (none, currentColor, transparent, pure white/black)', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24"><path fill="none"/><path fill="currentColor"/><path fill="transparent"/><path fill="#ffffff"/><path fill="#000000"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 0 );
	} );

	it( 'counts colors from inline style declarations', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24"><path style="fill:#729fcf;stroke:#204a87"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 2 );
	} );

	it( 'ignores var() references and the symbols own attributes', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24" fill="var(--wp--preset--color--x)"><path fill="#888a85"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 1 );
	} );

	it( 'treats equal colors as one color', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24"><path fill="#d3d7cf"/><circle fill="#D3D7CF" r="2" cx="1" cy="1"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 1 );
	} );

	it( 'counts stop-color attributes in gradients', () => {
		const symbol = symbolOf(
			'<symbol id="ic" viewBox="0 0 24 24"><defs><linearGradient id="g"><stop stop-color="#729fcf"/><stop stop-color="#204a87"/></linearGradient></defs><rect fill="url(#g)"/></symbol>'
		);
		expect( symbolColorCount( symbol ) ).toBe( 2 );
	} );
} );

describe( 'isColoredSymbol', () => {
	it( 'requires at least two distinct colors', () => {
		expect( isColoredSymbol( 1 ) ).toBe( false );
		expect( isColoredSymbol( 2 ) ).toBe( true );
		expect( isColoredSymbol( 0 ) ).toBe( false );
	} );
} );

describe( 'parseSymbols', () => {
	it( 'extracts id, viewBox and colored state from a sprite', () => {
		const symbols = parseSymbols(
			sprite(
				'<symbol id="fill" viewBox="0 0 24 24"><path fill="#889"/></symbol><symbol id="icon" viewBox="0 0 32 32"><path fill="#d3d7cf"/><path fill="#204a87"/></symbol>'
			)
		);
		expect( symbols ).toHaveLength( 2 );
		expect( symbols[ 0 ] ).toEqual( {
			id: 'fill',
			viewBox: '0 0 24 24',
			colored: false,
		} );
		expect( symbols[ 1 ] ).toEqual( {
			id: 'icon',
			viewBox: '0 0 32 32',
			colored: true,
		} );
	} );

	it( 'drops symbols without an id', () => {
		const symbols = parseSymbols(
			sprite(
				'<symbol viewBox="0 0 24 24"><path fill="#888a85"/></symbol><symbol id="ok" viewBox="0 0 24 24"><path fill="#888a85"/></symbol>'
			)
		);
		expect( symbols.map( ( s ) => s.id ) ).toEqual( [ 'ok' ] );
	} );
} );
