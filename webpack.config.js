var Encore = require('@symfony/webpack-encore');
var WebpackShellPlugin = require('webpack-shell-plugin');

Encore
    // the project directory where all compiled assets will be stored
    .setOutputPath('web/build/')

    // the public path used by the web server to access the previous directory
    .setPublicPath('/build')

    // allow pug templates in vue components
    .enableVueLoader()

    // Add javascripts
    .autoProvidejQuery()
    .addEntry('feedback', './assets/js/main/feedback.js')
    .addEntry('locationsedit', './assets/js/main/locationsedit.js')
    .addEntry('manuscriptsearch', './assets/js/main/manuscriptsearch.js')
    .addEntry('manuscriptedit', './assets/js/main/manuscriptedit.js')
    .addEntry('originsedit', './assets/js/main/originsedit.js')
    .addEntry('regionsedit', './assets/js/main/regionsedit.js')
    .addEntry('users', './assets/js/main/users.js')
    .addEntry('main', './assets/js/main/main.js')

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
