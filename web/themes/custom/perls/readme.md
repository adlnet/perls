# Perls Theme

The below commands should be everything you need to get
setup, whether you're just viewing the latest theme files or actively
doing theme development.

## Installation

### Prerequisites:

- [Node](https://nodejs.org/en/) (8+)
- [npm](https://nodejs.org/) (5+)

### Installing development dependencies

In the Perls theme root directory run:

```sh
npm run build
```

This will create the `node_modules` directory and download all dev dependencies,
compile all Perls theme SCSS into CSS and JS files.


## Generating theme asset files

To generate the latest theme assets, run this command:

```sh
gulp build-theme
```

This will generate all CSS/JS files, compile a new Pattern Lab `public` site,
and move the generated CSS/JS into the new `public` site. You might use this
after you pull in some theme changes and want to view them on
your local environment.

## Developing within the theme

When working with the theme, there's a few development options available.
These commands must be run from the root of the PERLS theme directory.

### Watch for file changes

This will start up a local server via [Browsersync](https://browsersync.io/).
It proxies the local domain `perls.localhost:8000`.

```sh
gulp
```

### Generating theme-specific CSS files

```sh
gulp sass
```

### Rebuild Pattern Lab

```sh
gulp patterns-change
```

_For a larger list of `gulp` commands, see the [gulp file](gulpfile.js)_

### Developing

Place all template files into the `templates` directory and all SCSS
stylesheets into the `resources/scss` directory. When adding new SCSS
stylesheets,remember to `@import` them in `scss/styles.scss`. This will act
as a registry of all stylesheets and allow for control over their load order.
