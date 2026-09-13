/**
 * Unit tests for the attribute resolvers in attribute-utils.ts.
 *
 * @package
 */

import {
	resolveColorValue,
	resolveDimensionValue,
	type DimensionSizeMap,
} from './attribute-utils';

describe( 'resolveColorValue', () => {
	it( 'maps a preset slug to the theme color variable', () => {
		expect( resolveColorValue( 'vivid-red' ) ).toBe(
			'var(--wp--preset--color--vivid-red)'
		);
	} );

	it( 'maps a preset reference to the theme color variable', () => {
		expect( resolveColorValue( 'var:preset|color|vivid-purple' ) ).toBe(
			'var(--wp--preset--color--vivid-purple)'
		);
	} );

	it( 'passes raw CSS colors through unchanged', () => {
		expect( resolveColorValue( '#bada55' ) ).toBe( '#bada55' );
		expect( resolveColorValue( 'rgb(255 0 0)' ) ).toBe( 'rgb(255 0 0)' );
		expect( resolveColorValue( 'var(--wp--preset--color--x)' ) ).toBe(
			'var(--wp--preset--color--x)'
		);
	} );
} );

describe( 'resolveDimensionValue', () => {
	const presetSizes: DimensionSizeMap = {
		s: '24px',
		xl: '128px',
	};

	it( 'resolves a preset reference against the size map', () => {
		expect(
			resolveDimensionValue( 'var:preset|dimension|xl', presetSizes )
		).toBe( '128px' );
		expect(
			resolveDimensionValue( 'var:preset|dimension|s', presetSizes )
		).toBe( '24px' );
	} );

	it( 'returns an empty string for an unknown preset slug', () => {
		expect(
			resolveDimensionValue( 'var:preset|dimension|zz', presetSizes )
		).toBe( '' );
	} );

	it( 'passes raw CSS lengths through unchanged', () => {
		expect( resolveDimensionValue( '42px', presetSizes ) ).toBe( '42px' );
		expect( resolveDimensionValue( '2em', presetSizes ) ).toBe( '2em' );
		expect( resolveDimensionValue( '50%', presetSizes ) ).toBe( '50%' );
		expect( resolveDimensionValue( '', presetSizes ) ).toBe( '' );
	} );
} );
