var Encore = require('@symfony/webpack-encore');
var WebpackShellPlugin = require('webpack-shell-plugin');

Encore
    // the project directory where all compiled assets will be stored
    .setOutputPath('web/build/')

    // the public path used by the web server to access the previous directory
    .setPublicPath('/build')

    // Add javascripts
    .addEntry('manuscriptSearchApp', './assets/js/manuscriptSearchApp.js')

    // allow sass/scss files to be processed
    .enableSassLoader()

    // Add stylesheets
    .addStyleEntry('screen', './assets/scss/screen.scss')

    // provide source maps for dev environment
    .enableSourceMaps(!Encore.isProduction())

    // empty the outputPath dir before each build
    .cleanupOutputBeforeBuild()

    // enable vue-loader
    .enableVueLoader()
;

// further config tweaking
const config = Encore.getWebpackConfig();

// Create symlinks using shell plugin
config.plugins.push(new WebpackShellPlugin({
    onBuildEnd: [
        './create_symlinks.sh'
    ]
}));

// Make sure watch works
// https://github.com/symfony/webpack-encore/issues/191
// Use polling instead of inotify
config.watchOptions = {
    poll: true,
};

// Export the final configuration
module.exports = config;
