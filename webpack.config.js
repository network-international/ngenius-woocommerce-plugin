const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require("path");

module.exports = {
	...defaultConfig,
	entry: {
		'index': '/resources/js/frontend/index.js',
	},
	output: {
		path: path.resolve(__dirname, 'ngenius/assets/js'),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin(),
	],
};
