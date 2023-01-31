const Path = require('path');
const { JavascriptWebpackConfig } = require('@silverstripe/webpack-config');

const ENV = process.env.NODE_ENV;
const PATHS = {
  ROOT: Path.resolve(),
  SRC: Path.resolve('client/src'),
  DIST: Path.resolve('client/dist'),
};

const config = [
  // Main JS bundle
  new JavascriptWebpackConfig('js', PATHS, 'silverstripe/documentconverter')
    .setEntry({
        DocumentConversionField: `${PATHS.SRC}/js/DocumentConversionField.js`,
    })
    .getConfig(),
];

module.exports = config;
