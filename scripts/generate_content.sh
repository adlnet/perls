#!/usr/bin/env bash
set -e
drush genu 50;

echo "Finished generating content.";

drush sapi-i;