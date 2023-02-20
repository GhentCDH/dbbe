/*
 * Load all available gulp plugins.
 */
import gulp from 'gulp';
import gulpLoadPlugins from 'gulp-load-plugins';

const $ = gulpLoadPlugins({pattern:[ 'gulp-', 'gulp.*', 'del']});

/*
 * Task 'clean':
 * Remove all files in the directories where the generated files will be
 * located.
 */
gulp.task(
  'clean',
  $.del.bind(null, ['static/fonts/**', '!static/fonts', '!static/fonts/panno', '!static/fonts/panno/**', 'static/css', 'static/js'], {force:true})
);

/*
 * Task 'vendor':
 * Process vendor JavaScript and font files.
 */
gulp.task('vendor', () => {
  gulp.start('vendor-javascript');
  gulp.start('vendor-fonts');
});

/*
 * Task 'vendor-javascript':
 * (Compress and) copy vendor JavaScript files.
 */
gulp.task('vendor-javascript', () => {
  // Files that can be copied
  gulp.src(
    [
      'bower_components/bootstrap/dist/js/bootstrap.min.js',
      'bower_components/bootstrap-select/dist/js/bootstrap-select.min.js',
      'bower_components/bootstrap-select/dist/js/bootstrap-select.js.map',
      'bower_components/ekko-lightbox/dist/ekko-lightbox.min.js',
      'bower_components/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js',
      'bower_components/jquery/dist/jquery.min.js',
      'bower_components/jquery/dist/jquery.min.map',
      'bower_components/jquery-ui/ui/minified/jquery.ui.widget.min.js',
      'bower_components/jquery-migrate/jquery-migrate.min.js',
      'bower_components/jquery.tocify.js/src/javascripts/jquery.tocify.min.js',
      'bower_components/moment/min/moment.min.js',
      'bower_components/picturefill/dist/picturefill.min.js',
      'bower_components/smooth-scroll/dist/js/smooth-scroll.min.js',
      'bower_components/tablesorter/jquery.tablesorter.min.js',
      'bower_components/typeahead.js/dist/typeahead.bundle.min.js'
    ]
  )
    .pipe(gulp.dest('static/js/vendor'));

  // Files that need to be placed in a specific folder
  gulp.src('bower_components/moment/locale/nl.js')
    .pipe($.sourcemaps.init())
    .pipe($.uglify())
    .pipe($.rename({
      suffix: '.min'
    }))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('static/js/vendor/locale'));

  // Files that need compressing
  gulp.src([
    'bower_components/bootstrap-sass/assets/javascripts/bootstrap/collapse.js',
    'bower_components/bootstrap-sass/assets/javascripts/bootstrap/modal.js',
    'bower_components/bootstrap-sass/assets/javascripts/bootstrap/alert.js'
  ])
    .pipe($.sourcemaps.init())
    .pipe($.uglify())
    .pipe($.rename({
      suffix: '.min'
    }))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('static/js/vendor'));
});

/*
 * Task 'vendor-fonts':
 * Copy vendor font files.
 */
gulp.task('vendor-fonts', () => {
  // Bootstrap
  gulp.src([
    'bower_components/bootstrap/dist/fonts/*.*'
  ])
    .pipe(gulp.dest('static/fonts/bootstrap'));
  // Font Awesome
  gulp.src([
    'bower_components/font-awesome/fonts/*'
  ])
    .pipe(gulp.dest('static/fonts/font-awesome'));
});

/*
 * Task 'eslint':
 * Check own JavaScript code for potential errors.
 */
gulp.task('eslint', () => {
  return gulp.src([
    'gulpfile.babel.js',
    'js/*.js'
  ])
    .pipe($.eslint())
    .pipe($.eslint.format())
    .pipe($.eslint.failAfterError());
});

/*
 * Task 'modernizr':
 * Detect which Modernizr tests are needed and build a custom Modernizr version.
 */
gulp.task('modernizr', () => {
  return gulp.src('js/*.js')
    .pipe($.modernizr('modernizr-custom.js'))
      .pipe($.sourcemaps.init())
    .pipe($.uglify())
    .pipe($.rename({
      suffix: '.min'
    }))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('static/js/vendor'));
});

/*
 * Task 'uglify':
 * Compress own JavaScript files to a single minified file.
 */
gulp.task('uglify', ['eslint'], () => {
  // Process main.js last, so variables / functions declared in other files can
  // be used
  return gulp.src([
    'js/!(main)*.js',
    'js/main.js'
  ])
    .pipe($.sourcemaps.init())
    .pipe($.concat('main.js'))
    .pipe($.uglify())
    .pipe($.rename({
      suffix: '.min'
    }))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('static/js'));
});

/*
 * Task 'vendor-sass':
 * Generate sass files from vendor stylesheet files not in sass format.
 */
gulp.task('vendor-sass', () => {
  gulp.src('bower_components/ekko-lightbox/ekko-lightbox.less')
    .pipe($.lessToScss())
    .pipe(gulp.dest('bower_components/ekko-lightbox'));
});

/*
 * Task 'sass':
 * Process all sass files and generate a single CSS file.
 */
gulp.task('sass', ['vendor-sass'], () => {
  return gulp.src('sass/*.scss')
    .pipe($.plumber())
    .pipe($.sourcemaps.init())
    .pipe($.sass({outputStyle: 'compressed', precision: 8}).on('error', $.sass.logError))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('static/css'));
});

/*
 * Task 'watch':
 * Run the 'sass' task if one of the own sass files is modified.
 * Run the 'uglify' task if one of the own JavaScript files is modified.
 */
gulp.task('watch', () => {
  gulp.watch('sass/**/*.scss', ['sass']);
  gulp.watch('js/*.js', ['uglify']);
});

/*
 * Task 'build':
 * Build everything (JavaScript, fonts and CSS).
 */
gulp.task('build', ['clean'], () => {
  gulp.start('vendor');
  gulp.start('modernizr');
  gulp.start('uglify');
  gulp.start('sass');
});

/*
 * Task 'default':
 * Run the 'build' task if no specific task is given.
 */
gulp.task('default', () => {
  gulp.start('build');
});
