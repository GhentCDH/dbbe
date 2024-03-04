const Encore = require('@symfony/webpack-encore');
const WebpackShellPluginNext = require('webpack-shell-plugin-next');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */

    // allow pug templates in vue components
    .enableVueLoader(() => {}, { runtimeCompilerBuild: true })

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

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // don't load chunks of code
    .disableSingleRuntimeChunk()

    // enable pug templates in vue
    .addLoader({
        test: /\.pug$/,
        loader: 'pug-plain-loader'
    })

    .enableBuildNotifications()
    // .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // allow sass/scss files to be processed
    .enableSassLoader()

    // Add stylesheets
    .addStyleEntry('screen', './assets/scss/screen.scss')

    // enable polling and check for changes every 250ms
    // polling is useful when running Encore inside a Virtual Machine
    .configureWatchOptions(function(watchOptions) {
        watchOptions.poll = 250;
    })

    .addPlugin(new WebpackShellPluginNext({
        onBuildEnd: {
            scripts: [
                './create_symlinks.sh',
                './copy_libraries.sh',
            ]
        }
    }))
;

module.exports = Encore.getWebpackConfig();
