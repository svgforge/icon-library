/**
 * Unit tests for the dimension preset helpers in icon-sizes.ts.
 *
 * @package
 */

import { toDimensionPresetEntries } from './icon-sizes';

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

	it( 'flattens the origin-keyed object form delivered by useSetting', () => {
		const presets = toDimensionPresetEntries( {
			default: [ { name: 'Default', slug: 'd', size: '16px' } ],
			theme: [
				{ name: 'L', slug: 'l', size: '64px' },
				{ name: 'S', slug: 's', size: '24px' },
			],
			custom: [ { name: 'My size', slug: 'my', size: '90px' } ],
		} );
		expect( presets.map( ( p ) => p.size ) ).toEqual( [
			'90px',
			'64px',
			'24px',
			'16px',
		] );
	} );

	it( 'flattens the origin-keyed object and drops invalid entries', () => {
		const presets = toDimensionPresetEntries( {
			default: [ { slug: 'broken' } ],
			theme: [ { name: 'L', slug: 'l', size: '64px' } ],
			custom: [],
		} );
		expect( presets ).toEqual( [ { name: 'L', slug: 'l', size: '64px' } ] );
	} );

	it( 'returns an empty list for empty origin-keyed objects', () => {
		expect(
			toDimensionPresetEntries( { default: [], theme: [], custom: [] } )
		).toEqual( [] );
	} );
} );
