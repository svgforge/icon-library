/**
 * Pure helpers for the SVG Fragment block Size panel.
 *
 * The Size panel supports theme.json `dimensions.dimensionSizes` presets (the
 * same mechanism as core font sizes) plus independent custom width/height.
 * Every preset applies as a square size (width and height).
 *
 * @package
 */

export type Length = {
	num: string;
	unit: string;
};

export type DimensionPreset = {
	name?: string;
	slug?: string;
	size: string;
};

export const UNIT_OPTIONS: Array< { value: string; label: string } > = [
	{ value: 'px', label: 'px' },
	{ value: 'em', label: 'em' },
	{ value: 'rem', label: 'rem' },
	{ value: '%', label: '%' },
];

/**
 * Splits a CSS length string like "32px" into number and unit.
 *
 * @param value        Raw length value, may be empty.
 * @param fallbackUnit Unit to assume when no unit is given.
 * @return The number ('' when unparseable/empty) and the unit.
 */
export function parseLength( value: string, fallbackUnit = 'px' ): Length {
	if ( ! value ) {
		return { num: '', unit: fallbackUnit };
	}
	const match = String( value ).match( /^(\d+(?:\.\d+)?)(px|em|rem|%)?$/ );
	if ( ! match ) {
		return { num: '', unit: fallbackUnit };
	}
	return { num: match[ 1 ], unit: match[ 2 ] || fallbackUnit };
}

/**
 * Normalizes a length to a comparable "number+unit" string (px fallback).
 *
 * @param value Raw length value.
 * @return Normalized form, e.g. "32px", usable for direct equality checks.
 */
export function normalizeLength( value: string ): string {
	const { num, unit } = parseLength( value, 'px' );

	return ( num || '0' ) + unit;
}

/**
 * Extracts the usable dimension presets from a useSetting() value.
 *
 * Filters out anything that is not a preset object with a string size.
 *
 * @param value Raw `dimensions.dimensionSizes` setting value.
 * @return The usable presets.
 */
export function toDimensionPresetEntries( value: unknown ): DimensionPreset[] {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	return value.filter(
		( entry ): entry is DimensionPreset =>
			!! entry &&
			typeof entry === 'object' &&
			typeof ( entry as { size?: unknown } ).size === 'string'
	);
}

/**
 * Whether the current width/height equal a given square preset size.
 *
 * @param width  Current width attribute.
 * @param height Current height attribute.
 * @param size   Preset size, e.g. "64px".
 * @return True when width and height match the preset.
 */
export function isDimensionPresetActive(
	width: string,
	height: string,
	size: string
): boolean {
	return (
		width !== '' &&
		width === height &&
		normalizeLength( width ) === normalizeLength( size )
	);
}
