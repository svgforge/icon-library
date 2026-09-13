/**
 * Pure helpers for resolving theme.json `dimensions.dimensionSizes` presets.
 *
 * The size UX itself is the standard Gutenberg Dimensions panel
 * (`supports.dimensions.width`); only the preset lookup for the editor
 * preview and the frontend render lives here.
 *
 * @package
 */

export type DimensionPreset = {
	name?: string;
	slug?: string;
	size: string;
};

/**
 * Extracts the usable dimension presets from a useSetting() value.
 *
 * Filters out anything that is not a preset object with a string size.
 *
 * @param value Raw `dimensions.dimensionSizes` setting value.
 * @return The usable presets.
 */
export function toDimensionPresetEntries( value: unknown ): DimensionPreset[] {
	const isValid = ( entry: unknown ): entry is DimensionPreset =>
		!! entry &&
		typeof entry === 'object' &&
		typeof ( entry as { size?: unknown } ).size === 'string';

	if ( Array.isArray( value ) ) {
		return value.filter( isValid );
	}

	// The block editor delivers dimensionSizes as an origin-keyed object
	// ({ default: [], theme: [], custom: [] }), like core spacing/font sizes.
	if ( value !== null && typeof value === 'object' ) {
		// Core merges custom, theme, default in that order; keep the same order.
		return ( [ 'custom', 'theme', 'default' ] as const )
			.map(
				( origin ) => ( value as Record< string, unknown > )[ origin ]
			)
			.flatMap( ( entries ) =>
				Array.isArray( entries ) ? entries.filter( isValid ) : []
			);
	}

	return [];
}
