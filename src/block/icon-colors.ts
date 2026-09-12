/**
 * Pure helpers for detecting whether a sprite symbol carries its own colors.
 *
 * Symbols with two or more real, distinct colors are treated as "colored": the
 * block editor hides the recolor controls for them.
 *
 * @package
 */

export type SpriteSymbol = {
	id: string;
	viewBox: string | null;
	colored: boolean;
};

const COLOR_SPECIAL_VALUES = new Set( [
	'none',
	'currentcolor',
	'inherit',
	'transparent',
	'#000',
	'#000000',
	'#fff',
	'#ffffff',
] );

const COLOR_VALUE_PATTERN =
	/^(#[0-9a-f]{3,8}\b|rgb\(|rgba\(|hsl\(|hsla\(|color\(|lab\(|oklch\()/i;

const collectsExplicitColors = (
	el: SVGElement,
	seen: Set< string >
): void => {
	const add = ( value: string ): void => {
		const v = value.trim().toLowerCase();
		if ( ! v || COLOR_SPECIAL_VALUES.has( v ) || v.startsWith( 'var(' ) ) {
			return;
		}
		if ( COLOR_VALUE_PATTERN.test( v ) ) {
			seen.add( v );
		}
	};

	[ 'fill', 'stroke', 'stop-color', 'color' ].forEach( ( attr ) => {
		const value = el.getAttribute( attr );
		if ( value ) {
			add( value );
		}
	} );

	const styleAttr = el.getAttribute( 'style' );
	if ( styleAttr ) {
		styleAttr.split( ';' ).forEach( ( decl ) => {
			const match = decl.match(
				/^\s*(fill|stroke|stop-color)\s*:(.+)$/i
			);
			if ( match ) {
				add( match[ 2 ] );
			}
		} );
	}
};

/**
 * Counts the distinct, explicit colors a symbol declares.
 *
 * Reads fill/stroke/stop-color/color attributes and matching inline style
 * declarations on the symbol element and all of its descendants. Special
 * values (none, currentColor, inherit, transparent, pure black/white) and
 * var() references are ignored.
 *
 * @param symbol The <symbol> element.
 * @return Number of distinct colors.
 */
export function symbolColorCount( symbol: SVGElement ): number {
	const seen = new Set< string >();
	Array.from( symbol.querySelectorAll( '*' ) ).forEach( ( el ) =>
		collectsExplicitColors( el as SVGElement, seen )
	);
	collectsExplicitColors( symbol, seen );
	return seen.size;
}

/**
 * Whether a symbol should be treated as colored (>= 2 distinct colors).
 *
 * @param count Precomputed symbolColorCount() result.
 * @return True when the symbol packs in its own colors.
 */
export function isColoredSymbol( count: number ): boolean {
	return count >= 2;
}

/**
 * Parses an SVG sprite document into its colored symbols.
 *
 * @param svgText Raw sprite file content.
 * @return The symbol entries that have an id.
 */
export const parseSymbols = ( svgText: string ): SpriteSymbol[] => {
	const parser = new window.DOMParser();
	const doc = parser.parseFromString( svgText, 'image/svg+xml' );
	const symbols = Array.from( doc.getElementsByTagName( 'symbol' ) );
	return symbols
		.map( ( s ) => ( {
			id: s.getAttribute( 'id' ),
			viewBox: s.getAttribute( 'viewBox' ),
			colored: isColoredSymbol( symbolColorCount( s as SVGElement ) ),
		} ) )
		.filter( ( s ): s is SpriteSymbol => !! s.id );
};
