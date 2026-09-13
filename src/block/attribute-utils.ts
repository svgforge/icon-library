/**
 * Pure helpers resolving Gutenberg block attribute values to CSS values.
 *
 * These mirror the PHP resolvers in icon-library.php
 * (icon_library_resolve_color() / icon_library_resolve_dimension()).
 *
 * @package
 */

export type DimensionSizeMap = Record< string, string >;

/**
 * Resolves a block color value to a CSS color.
 *
 * Preset slugs (e.g. `vivid-red`) and `var:preset|color|<slug>` references map
 * to the theme CSS custom property; raw CSS values pass through unchanged.
 *
 * @param value Raw color value from block attributes.
 * @return The CSS color value.
 */
export function resolveColorValue( value: string ): string {
	const prefix = 'var:preset|color|';
	if ( value.startsWith( prefix ) ) {
		return `var(--wp--preset--color--${ value.slice( prefix.length ) })`;
	}
	if (
		value.startsWith( 'var(' ) ||
		value.startsWith( '#' ) ||
		value.startsWith( 'rgb' )
	) {
		return value;
	}
	return `var(--wp--preset--color--${ value })`;
}

/**
 * Resolves a block dimension value to a CSS length.
 *
 * `var:preset|dimension|<slug>` references resolve against the given preset
 * size map; raw CSS lengths pass through unchanged.
 *
 * @param value       Raw dimension value from block attributes.
 * @param presetSizes Map of dimension preset slug to its size (e.g. `128px`).
 * @return The CSS length, or an empty string for an unknown preset.
 */
export function resolveDimensionValue(
	value: string,
	presetSizes: DimensionSizeMap
): string {
	const prefix = 'var:preset|dimension|';
	if ( value.startsWith( prefix ) ) {
		return presetSizes[ value.slice( prefix.length ) ] ?? '';
	}
	return value;
}

/**
 * Resolves a block spacing value to a CSS length.
 *
 * `var:preset|spacing|<slug>` references map to the theme spacing CSS custom
 * property; raw CSS lengths pass through unchanged.
 *
 * @param value Raw spacing value from block attributes.
 * @return The CSS value (e.g. `var(--wp--preset--spacing--30)`).
 */
export function resolveSpacingValue( value: string ): string {
	const prefix = 'var:preset|spacing|';
	if ( value.startsWith( prefix ) ) {
		return `var(--wp--preset--spacing--${ value.slice( prefix.length ) })`;
	}
	return value;
}
