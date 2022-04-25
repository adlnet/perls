#!/bin/bash
set -e

backup=true
use_local_db=''
use_backup_file=''

print_usage() {
  echo "refresh.sh Refresh Drupal with composer install and attempt to copy STAGE database. Do not restart docker containers."
  echo " "
  echo "refresh.sh [options]"
  echo " "
  echo "options:"
  echo "-h, --help                show brief help"
  echo "-n                        do not backup the database"
  echo "-l                        use local copy of the database instead of STAGE database"
  echo "-f                        use db_backup.sql file instead of existing local database"
}

while getopts 'nlf' flag; do
  case "${flag}" in
    n) backup=false ;;
    l) use_local_db="-l" ;;
    f) use_backup_file="-f" ;;
    *) print_usage
       exit 1 ;;
  esac
done

# Load environmental variables
set -a
source .env

# Do a backup before trying to sync stage DB
if [[ "$backup" == true ]] && [[ "$use_backup_file" == '' ]]; then
    docker exec ${PROJECT_NAME}_php scripts/backup_db.sh
fi

docker exec ${PROJECT_NAME}_php composer install -n

# Execute this script from inside docker container.
# This wipes the db, copies db from stage and imports config.
docker exec ${PROJECT_NAME}_php scripts/sync_site.sh $use_local_db $use_backup_file

# If our Database sync was successful we update our backup file.
if [[ "$backup" == true ]]; then
    echo "== Refreshing db_backup.sql with updated database..."
    docker exec ${PROJECT_NAME}_php scripts/backup_db.sh
fi