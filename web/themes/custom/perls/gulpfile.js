'use strict';

// Configuration.
var config = {};

config.sass = {
  srcFiles: [
    './resources/scss/**/*.scss',
    './resources/scss/**/*.css'
  ],
  options: {
    outputStyle: 'expanded'
  },
  destDir: './resources/scss'
};

config.js = {
  srcFiles: [
    './resources/scripts/*.js'
  ],
  destDir: './resources/scripts'
};

config.distDir = {
  dir: './dist',
  publicCssDir: './css',
  publicJsDir: './js'
};

// Load Gulp and other tools.
var fs = require('fs');
var gulp = require('gulp');
var cp = require('child_process');
const sass = require('gulp-sass')(require('sass'));
var sassGlob = require('gulp-sass-glob');
var sourcemaps = require('gulp-sourcemaps');
var autoprefixer = require('gulp-autoprefixer');
var gulpStylelint = require('gulp-stylelint');
var cleanCSS = require('gulp-clean-css');
var uglify = require('gulp-uglify');
var rename = require("gulp-rename");

// Gulp tasks.

/**
 * Sets up watchers.
 */
gulp.task('watch', function () {
  gulp.watch('resources/scss/**/*.scss', gulp.series('sass-change', 'lint:scss'));
  gulp.watch('resources/scripts/*.js', gulp.series('copy-js'));
});

/**
 * Processes Sass files.
 */
gulp.task('sass', function () {
  return gulp.src(config.sass.srcFiles)
    .pipe(sassGlob())
    .pipe(sourcemaps.init())
    .pipe(sass(config.sass.options).on('error', sass.logError))
    .pipe(autoprefixer({ remove: false }))
    .pipe(cleanCSS())
    .pipe(rename({
      suffix: '.min'
    }))
    .pipe(sourcemaps.write('./'))
    .pipe(gulp.dest(config.distDir.publicCssDir));
});

/**
 * Copies CSS files to the public dir.
 */
gulp.task('copy-css', function () {
  return gulp.src(config.sass.destDir + '/**/*.css')
    .pipe(rename({
      suffix: '.min'
    }))
    .pipe(gulp.dest(config.distDir.publicCssDir));
});

/**
 * Copies JavaScript files to the public dir.
 */
gulp.task('copy-js', function () {
  return gulp.src(config.js.destDir + '/**/*.js')
    .pipe(uglify())
    .pipe(rename({
      suffix: '.min'
    }))
    .pipe(gulp.dest(config.distDir.publicJsDir));
});

/**
 * Lints Sass files.
 */
gulp.task('lint:scss', function lintCssTask() {
  return gulp.src(config.sass.srcFiles)
    .pipe(gulpStylelint({
      reporters: [
        { formatter: 'string', console: true }
      ]
    }));
});

/**
 * Task sequence to run when Sass files have changed.
 */
gulp.task('sass-change', gulp.series('sass', 'copy-css'));

/**
 * Task sequence to run that copies all assets to public.
 */
gulp.task('copy-assets', gulp.parallel('copy-css', 'copy-js'));

/**
 * Task sequence generate theme and Pattern Lab files.
 */
gulp.task('build-theme', gulp.series(gulp.parallel('sass-change', 'copy-js')));

/**
 * Gulp default task.
 */
gulp.task('default', gulp.series('build-theme', 'watch'));
