var Encore = require('@symfony/webpack-encore');
var WebpackShellPlugin = require('webpack-shell-plugin');

Encore
    // the project directory where all compiled assets will be stored
    .setOutputPath('web/build/')

    // enable asset versioning, so browser caches don't need to be cleared
    .enableVersioning()

    // the public path used by the web server to access the previous directory
    .setPublicPath('/build')

    // allow pug templates in vue components
    .enableVueLoader()

    // Add javascripts
    .autoProvidejQuery()
    .addEntry('acknowledgementsedit', './assets/js/main/acknowledgementsedit.js')
    .addEntry('articleedit', './assets/js/main/articleedit.js')
    .addEntry('bibliographysearch', './assets/js/main/bibliographysearch.js')
    .addEntry('bibvariaedit', './assets/js/main/bibvariaedit.js')
    .addEntry('blogedit', './assets/js/main/blogedit.js')
    .addEntry('blogpostedit', './assets/js/main/blogpostedit.js')
    .addEntry('bookedit', './assets/js/main/bookedit.js')
    .addEntry('bookchapteredit', './assets/js/main/bookchapteredit.js')
    .addEntry('bookclustersedit', './assets/js/main/bookclustersedit.js')
    .addEntry('bookseriessedit', './assets/js/main/bookseriessedit.js')
    .addEntry('contentsedit', './assets/js/main/contentsedit.js')
    .addEntry('feedback', './assets/js/main/feedback.js')
    .addEntry('genresedit', './assets/js/main/genresedit.js')
    .addEntry('journalsedit', './assets/js/main/journalsedit.js')
    .addEntry('journalissuesedit', './assets/js/main/journalissuesedit.js')
    .addEntry('keywordsedit', './assets/js/main/keywordsedit.js')
    .addEntry('lightbox', './assets/websites/bower_components/ekko-lightbox/dist/ekko-lightbox.min.js')
    .addEntry('locationsedit', './assets/js/main/locationsedit.js')
    .addEntry('main', './assets/js/main/main.js')
    .addEntry('managementsedit', './assets/js/main/managementsedit.js')
    .addEntry('manuscriptedit', './assets/js/main/manuscriptedit.js')
    .addEntry('manuscriptsearch', './assets/js/main/manuscriptsearch.js')
    .addEntry('metresedit', './assets/js/main/metresedit.js')
    .addEntry('newseventedit', './assets/js/main/newseventedit.js')
    .addEntry('occurrenceedit', './assets/js/main/occurrenceedit.js')
    .addEntry('occurrencesearch', './assets/js/main/occurrencesearch.js')
    .addEntry('officesedit', './assets/js/main/officesedit.js')
    .addEntry('onlinesourceedit', './assets/js/main/onlinesourceedit.js')
    .addEntry('originsedit', './assets/js/main/originsedit.js')
    .addEntry('pageedit', './assets/js/main/pageedit.js')
    .addEntry('personedit', './assets/js/main/personedit.js')
    .addEntry('personsearch', './assets/js/main/personsearch.js')
    .addEntry('phdedit', './assets/js/main/phdedit.js')
    .addEntry('regionsedit', './assets/js/main/regionsedit.js')
    .addEntry('rolesedit', './assets/js/main/rolesedit.js')
    .addEntry('selfdesignationsedit', './assets/js/main/selfdesignationsedit.js')
    .addEntry('statusesedit', './assets/js/main/statusesedit.js')
    .addEntry('typeedit', './assets/js/main/typeedit.js')
    .addEntry('typesearch', './assets/js/main/typesearch.js')
    .addEntry('usersedit', './assets/js/main/usersedit.js')

    // allow sass/scss files to be processed
    .enableSassLoader()

    // Add stylesheets
    .addStyleEntry('screen', './assets/scss/screen.scss')

    // provide source maps for dev environment
    .enableSourceMaps(!Encore.isProduction())

    // don't load chunks of code
    .disableSingleRuntimeChunk()

    // empty the outputPath dir before each build
    .cleanupOutputBeforeBuild()

    // enable pug templates in vue
    .addLoader({
        test: /\.pug$/,
        loader: 'pug-plain-loader'
    })
;

// further config tweaking
const config = Encore.getWebpackConfig();

// Create symlinks using shell plugin
config.plugins.push(new WebpackShellPlugin({
    onBuildEnd: [
        './create_symlinks.sh',
        './copy_libraries.sh',
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
