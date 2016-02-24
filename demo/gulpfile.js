// Include gulp
var gulp = require('gulp');

// Include plugins
var plugins = require("gulp-load-plugins")({
    pattern: ['gulp-*', 'gulp.*', 'main-bower-files'],
    replaceString: /\bgulp[\-.]/
});

// Define default destination folder
var dest = 'build/';

var clientApp = 'app/';

var config = {
    js: [
        clientApp + '**/*.module.js',
        clientApp + '**/*.js'
    ],
    jsOrder: [
        '**/app.module.js',
        '**/*.module.js',
        '**/*.js'
    ]
};

gulp.task('js', function() {

    gulp.src(plugins.mainBowerFiles().concat(config.js))
        .pipe(plugins.filter('*.js'))
        .pipe(plugins.order(config.jsOrder))
        .pipe(plugins.concat('main.js'))
        //.pipe(plugins.uglify().on('error', plugins.util.log))
        .pipe(gulp.dest(dest + 'js'));

});

gulp.task('css', function() {

    var cssFiles = [clientApp + 'styles/*'];

    gulp.src(plugins.mainBowerFiles().concat(cssFiles))
        .pipe(plugins.filter('*.css'))
        .pipe(plugins.concat('main.css'))
        //.pipe(plugins.uglify().on('error', plugins.util.log))
        .pipe(gulp.dest(dest + 'css'));

});

gulp.task('default', ['js', 'css']);
