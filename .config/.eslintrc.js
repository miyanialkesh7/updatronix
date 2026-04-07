module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	rules: {
		'import/no-extraneous-dependencies': 'off',
		'jsdoc/no-undefined-types': 'off',
		'import/no-unresolved': 'off',
	},
};
