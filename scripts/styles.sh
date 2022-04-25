#!/usr/bin/env bash
set -e

# Todo -- handle npm install fail and gulp build theme fail.
echo "== Generating styles-sheets..."
echo "== Npm install && gulp build-theme"
cd web/themes/custom/perls
npm rebuild node-sass && npm install && ./node_modules/.bin/gulp build-theme
echo "== Cache rebuild"
drush cache:rebuild
