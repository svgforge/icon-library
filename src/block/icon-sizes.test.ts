/**
 * Unit tests for the Size panel helpers in icon-sizes.ts.
 *
 * @package
 */

import {
	isDimensionPresetActive,
	normalizeLength,
	parseLength,
	toDimensionPresetEntries,
} from './icon-sizes';

describe( 'parseLength', () => {
	it( 'returns an empty number with the fallback unit for an empty value', () => {
		expect( parseLength( '' ) ).toEqual( { num: '', unit: 'px' } );
	} );

	it( 'splits number and unit', () => {
		expect( parseLength( '64px' ) ).toEqual( { num: '64', unit: 'px' } );
		expect( parseLength( '1.5em' ) ).toEqual( {
			num: '1.5',
			unit: 'em',
		} );
		expect( parseLength( '50%' ) ).toEqual( { num: '50', unit: '%' } );
	} );

	it( 'assumes the fallback unit when no unit is present', () => {
		expect( parseLength( '32' ) ).toEqual( { num: '32', unit: 'px' } );
		expect( parseLength( '2rem', 'rem' ) ).toEqual( {
			num: '2',
			unit: 'rem',
		} );
	} );

	it( 'returns an empty number for unparseable input', () => {
		expect( parseLength( 'large' ) ).toEqual( { num: '', unit: 'px' } );
		expect( parseLength( '32px ' ) ).toEqual( { num: '', unit: 'px' } );
	} );
} );

describe( 'normalizeLength', () => {
	it( 'normalizes to a comparable number+unit string', () => {
		expect( normalizeLength( '32px' ) ).toBe( '32px' );
		expect( normalizeLength( '32' ) ).toBe( '32px' );
		expect( normalizeLength( '1.5em' ) ).toBe( '1.5em' );
	} );

	it( 'maps empty or unparseable values to 0px', () => {
		expect( normalizeLength( '' ) ).toBe( '0px' );
		expect( normalizeLength( 'nope' ) ).toBe( '0px' );
	} );
} );

describe( 'toDimensionPresetEntries', () => {
	it( 'returns an empty list for non-array and empty input', () => {
		expect( toDimensionPresetEntries( null ) ).toEqual( [] );
		expect( toDimensionPresetEntries( '64px' ) ).toEqual( [] );
		expect( toDimensionPresetEntries( [] ) ).toEqual( [] );
	} );

	it( 'keeps entries with a string size', () => {
		const presets = toDimensionPresetEntries( [
			{ name: 'Small', slug: 's', size: '32px' },
			{ name: 'Large', slug: 'l', size: '64px' },
		] );
		expect( presets ).toHaveLength( 2 );
		expect( presets[ 0 ] ).toEqual( {
			name: 'Small',
			slug: 's',
			size: '32px',
		} );
	} );

	it( 'keeps entries without name or slug', () => {
		const presets = toDimensionPresetEntries( [ { size: '64px' } ] );
		expect( presets ).toHaveLength( 1 );
		expect( presets[ 0 ].name ).toBeUndefined();
	} );

	it( 'drops entries with a missing or non-string size and junk', () => {
		const presets = toDimensionPresetEntries( [
			{ slug: 'x' },
			{ size: 64 },
			'junk',
			null,
		] );
		expect( presets ).toEqual( [] );
	} );
} );

describe( 'isDimensionPresetActive', () => {
	it( 'is true when width and height equal the preset size', () => {
		expect( isDimensionPresetActive( '64px', '64px', '64px' ) ).toBe(
			true
		);
	} );

	it( 'normalizes the unit before comparing', () => {
		expect( isDimensionPresetActive( '64', '64', '64px' ) ).toBe( true );
	} );

	it( 'is false when the sizes differ', () => {
		expect( isDimensionPresetActive( '64px', '32px', '64px' ) ).toBe(
			false
		);
		expect( isDimensionPresetActive( '32px', '32px', '64px' ) ).toBe(
			false
		);
	} );

	it( 'is false when only one side is set or both are empty', () => {
		expect( isDimensionPresetActive( '64px', '', '64px' ) ).toBe( false );
		expect( isDimensionPresetActive( '', '64px', '64px' ) ).toBe( false );
		expect( isDimensionPresetActive( '', '', '64px' ) ).toBe( false );
	} );

	it( 'is false for different units', () => {
		expect( isDimensionPresetActive( '16px', '16px', '1em' ) ).toBe(
			false
		);
	} );
} );
