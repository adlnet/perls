#!/bin/bash

set -e

echo "== Backing up local database to db_backup.sql..."
echo "   Database backup can be skipped with the -n flag"
drush sql:dump > db_backup.sql