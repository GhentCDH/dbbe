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
    .addEntry('contentsedit', './assets/js/main/contentsedit.js')
    .addEntry('feedback', './assets/js/main/feedback.js')
    .addEntry('locationsedit', './assets/js/main/locationsedit.js')
    .addEntry('manuscriptsearch', './assets/js/main/manuscriptsearch.js')
    .addEntry('manuscriptedit', './assets/js/main/manuscriptedit.js')
    .addEntry('officesedit', './assets/js/main/officesedit.js')
    .addEntry('occurrencesearch', './assets/js/main/occurrencesearch.js')
    .addEntry('occurrenceedit', './assets/js/main/occurrenceedit.js')
    .addEntry('originsedit', './assets/js/main/originsedit.js')
    .addEntry('personedit', './assets/js/main/personedit.js')
    .addEntry('personsearch', './assets/js/main/personsearch.js')
    .addEntry('regionsedit', './assets/js/main/regionsedit.js')
    .addEntry('rolesedit', './assets/js/main/rolesedit.js')
    .addEntry('statusesedit', './assets/js/main/statusesedit.js')
    .addEntry('bookedit', './assets/js/main/bookedit.js')
    .addEntry('usersedit', './assets/js/main/usersedit.js')
    .addEntry('main', './assets/js/main/main.js')
    .addEntry('lightbox', './assets/websites/bower_components/ekko-lightbox/dist/ekko-lightbox.min.js')

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
