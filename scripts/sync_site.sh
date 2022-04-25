#!/usr/bin/env bash
set -e

use_local_db=false
use_backup_file=false

print_usage() {
  echo "sync_site.sh should run inside php docker container. Can sync remote repo. Brings in local config."
  echo " "
  echo "sync_site.sh [options]"
  echo " "
  echo "options:"
  echo "-h, --help                show brief help"
  echo "-l                        use local copy of the database instead of STAGE database"
  echo "-f                        use db_backup.sql file instead of existing local database"
}

while getopts 'nlf' flag; do
  case "${flag}" in
    l) use_local_db=true ;;
    f) use_backup_file=true ;;
    *) print_usage
       exit 1 ;;
  esac
done

##
# If you have trouble running locally, try from inside a container.
#
# docker exec PERLS_php scripts/sync_site.sh
##

sync_fail=false

if [[ "$use_local_db" == false ]] && [[ "$use_backup_file" == false ]]; then

    # PHP_DEV is set to 1 in the PERLS_php docker container
    if [ $PHP_DEV ]; then
        if [ ! -f ~/.ssh/perls_drush ]; then
            echo "== Copy ssh keys to .ssh user directory..."
            cp -r ./.ssh ~/ || true
        fi
    fi

    if [ -f ~/.ssh/perls_drush ]; then
        echo "== Setting proper permissions on SSH keys..."
        chmod 0600 ~/.ssh/perls_drush ~/.ssh/perls_drush.pub
    fi

    echo "== Checking connection to STAGE..."

    drush @stage status && echo "== Successfully connected to stage..." || sync_fail=true

    if [[ "$sync_fail" == true ]];
    then
        echo "== Failed to connect to STAGE..."
    else
        echo "== Drop and sync database from STAGE..."
        drush sql:drop -y

        # Attempt to sync db from stage. Allow recovery and continue with db_backup.sql
        drush sql:sync @stage @self -y && echo "== Successfully loaded STAGE database" || sync_fail=true

        if [[ "$sync_fail" == true ]]; then
            echo "== Failed to copy database from STAGE."
        fi
    fi
fi

file_fail=false

if [[ "$sync_fail" == true ]]  ||  [[ "$use_backup_file" == true  ]]; then
    if [[ -f db_backup.sql ]]; then
        echo "== Importing database from db_backup.sql..."
        drush sql-cli < db_backup.sql && echo "== Successfully imported db_backup.sql..." || file_fail=true
    else
        file_fail=true
        echo "== File db_backup.sql not found..."
    fi

    if [[ "$file_fail" == true ]]; then
        echo "== Failed to load database from db_backup.sql, attempting to build the site from scratch..."
        drush site-install --existing-config
    fi
fi

if [[ "$use_local_db" == true ]] || [[ "$file_fail" == true ]]; then
    echo "== Using existing local database..."
fi

echo "== Update database with any code changes..."
drush cr
drush updatedb --no-post-updates -y
drush config-import -y
drush config-import -y
drush updatedb -y
drush sqlq "UPDATE users_field_data SET name='admin', mail='admin@example.com' WHERE uid=1;"
drush cr
drush upwd admin password
drush user:role:add sysadmin admin
drush user:unblock admin
drush wd all -y

# Set up an oAuth consumer for API calls
# This client ID/secret should only be used in development environments...since it's not very secret.
echo "== Creating oAuth consumer for development..."
drush perls:createConsumer "0bba92e5-68ea-4e9b-8ab0-b43b2022330c" 'not-very-secret' --label="Mobile App-Development" --no-third_party --roles=rest_api_user --redirect="https://example.com/auth"

echo "== Rebuild search index from local solr..."
# This might fail outside of a docker container
drush search-api:clear
drush search-api:index
