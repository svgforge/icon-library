const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		rules: {
			'@wordpress/no-unsafe-wp-apis': [
				'error',
				{
					// ToggleGroupControl replaces the deprecated ButtonGroup,
					// but is still experimental in @wordpress/components@40.
					'@wordpress/components': [
						'__experimentalToggleGroupControl',
						'__experimentalToggleGroupControlOptionIcon',
					],
				},
			],
		},
	},
];
