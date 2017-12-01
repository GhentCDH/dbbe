import gulp from 'gulp';
import gulpLoadPlugins from 'gulp-load-plugins';
import * as vfs from 'vinyl-fs';

const $ = gulpLoadPlugins({pattern:[ 'gulp-', 'gulp.*', 'del']});

gulp.task(
  'clean',
  $.del.bind(null, ['../../web/assets/**'], {force:true})
);

gulp.task('eslint', () => {
  return gulp.src([
    'websites/js/*.js',
    'gulpfile.babel.js',
    'js/*.js'
  ])
    .pipe($.eslint())
    .pipe($.eslint.format())
    .pipe($.eslint.failAfterError());
});

gulp.task('vendor-javascript', () => {
  vfs.src([
    'websites/static/js/vendor/**/*',
    '!websites/static/js/vendor/modernizr-custom.min.js',
    '!websites/static/js/vendor/modernizr-custom.min.js.map',
    '!websites/static/js/vendor/locale'
  ])
    .pipe(vfs.symlink('../../web/assets/js/vendor', {relative: true}));
  vfs.src(
      [
        'websites/static/js/vendor/locale/*'
      ]
  )
    .pipe(vfs.symlink('../../web/assets/js/vendor/locale', {relative: true}));
});

// Detect which Modernizr tests are needed and build a custom Modernizr version.
gulp.task('modernizr', () => {
  return gulp.src(['websites/js/*.js', 'js/*.js'])
    .pipe($.modernizr('modernizr-custom.js'))
      .pipe($.sourcemaps.init())
    .pipe($.uglify())
    .pipe($.rename({
      suffix: '.min'
    }))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('../../web/assets/js/vendor'));
});

gulp.task('uglify', ['eslint'], () => {
  return gulp.src([
    'websites/js/!(main)*.js',
    'websites/js/main.js',
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
    .pipe(gulp.dest('../../web/assets/js'));
});

/*
 * Task 'vendor-sass':
 * Generate sass files from vendor stylesheet files not in sass format.
 */
gulp.task('vendor-sass', () => {
  gulp.src('websites/bower_components/ekko-lightbox/ekko-lightbox.less')
    .pipe($.lessToScss())
    .pipe(gulp.dest('websites/bower_components/ekko-lightbox'));
});

gulp.task('sass', ['vendor-sass'], () => {
  return gulp.src('sass/*.scss')
    .pipe($.plumber())
    .pipe($.sourcemaps.init())
    .pipe($.sass({outputStyle: 'compressed', precision: 8}).on('error', $.sass.logError))
    .pipe($.sourcemaps.write('.'))
    .pipe(gulp.dest('../../web/assets/css'));
});

gulp.task('fonts', () => {
  vfs.src('websites/static/fonts/bootstrap/*')
  .pipe(vfs.symlink('../../web/assets/fonts/bootstrap', {relative: true}));
  vfs.src('websites/static/fonts/font-awesome/*')
  .pipe(vfs.symlink('../../web/assets/fonts/font-awesome', {relative: true}));
  vfs.src('websites/static/fonts/panno/*')
  .pipe(vfs.symlink('../../web/assets/fonts/panno', {relative: true}));
});

gulp.task('icons', () => {
  vfs.src('websites/static/icons/*')
  .pipe(vfs.symlink('../../web/assets/icons', {relative: true}));

  vfs.src('icons/*')
  .pipe(vfs.symlink('../../web/assets/icons', {relative: true}));
});

gulp.task('images', () => {
  vfs.src('websites/static/images/*')
  .pipe(vfs.symlink('../../web/assets/images', {relative: true}));

  vfs.src('images/*')
  .pipe(vfs.symlink('../../web/assets/images', {relative: true}));
});

gulp.task('watch', () => {
  gulp.watch('sass/**/*.scss', ['sass']);
  gulp.watch('js/*.js', ['uglify']);
});

gulp.task('build', ['clean'], () => {
  gulp.start('vendor-javascript');
  gulp.start('modernizr');
  gulp.start('uglify');
  gulp.start('sass');
  gulp.start('fonts');
  gulp.start('icons');
  gulp.start('images');
});

gulp.task('default', () => {
  gulp.start('build');
});
