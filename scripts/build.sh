#!/bin/bash

set -e

backup=true
sync_local=''
force_build=false
code_sniff=false
export target=dev
build_arg=''

print_usage() {
  echo "build.sh Fully build site with docker."
  echo " "
  echo "build.sh [options]"
  echo " "
  echo "options:"
  echo "-h, --help                show brief help"
  echo "-n                        do not backup the database upon successful build"
  echo "-l, -f                    use local db_backup.sql file for database, the existing local db will be wiped out when docker restarts"
  echo "-b                        force rebuild app image (ex. for Dockerfile changes)"
  echo "-s                        run phpcs from vendor directory on this project"
}

source "${BASH_SOURCE%/*}/git_hooks/copy_hooks.sh"

while getopts 'nlfbs' flag; do
  case "${flag}" in
    l) sync_local="-f" ;;
    f) sync_local="-f" ;;
    n) backup=false ;;
    b) force_build=true ;;
    s) code_sniff=true ;;
    *) print_usage
       exit 1 ;;
  esac
done

#Make sure environmental variables are setup
if [[ ! -f .env ]]; then
    if [[ -f .env.example ]]; then
        echo "No .env file found. Copying .env.example to .env"
        cp .env.example .env
    fi
fi

#Make sure settings.local.php exists for new projects
if [[ ! -f web/sites/default/settings.local.php ]]; then
    if [[ -f web/sites/default/default.settings.local.php ]]; then
        echo "No settings.local.php file found. Copying default.settings.local.php to settings.local.php"
        cp web/sites/default/default.settings.local.php web/sites/default/settings.local.php
    fi
fi

# Load environmental variables
set -a
source .env

# Reset any previously created containers
echo "Cleaning up any previous containers..."
docker network disconnect -f ${PROJECT_NAME}_network chrome || true
docker-compose down -v || true

docker_compose_files='-f docker-compose.yml'

if [[ "$force_build" == true ]]; then
  build_arg='--build'
fi

echo "Starting docker using this config: $docker_compose_files"
docker-compose up -d $build_arg


# The drupal install might error if the docker containers are not fully ready.
# Giving the docker containers a few extra moments here seems to fix the issue.
sleep 3

# Do not allow a build with an out of data lock file
docker exec ${PROJECT_NAME}_php composer validate --no-check-all --no-check-publish && echo "pass" || exit 1

# Update dependencies
docker exec ${PROJECT_NAME}_php composer install -n && echo "pass" || exit 1

if [[ "$code_sniff" == true ]]; then
  # Run code sniffer
  echo "== Check code styles with phpcs..."
  # Run the sniffer a second time to generate a report only on fail.
  docker exec ${PROJECT_NAME}_php vendor/bin/phpcs --standard=Drupal --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md '--ignore=node_modules,bower_components,vendor,*.min.js,*.min.css' web/modules/custom web/themes/custom \
   || docker exec ${PROJECT_NAME}_php vendor/bin/phpcs --report=checkstyle --report-file=checkstyle.xml --standard=Drupal --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md '--ignore=node_modules,bower_components,vendor,*.min.js,*.min.css' web/modules/custom web/themes/custom
fi

# Set up proper settings file
chmod 755 web/sites/default

# Add default drupal core to solr server.
cores=$(docker exec -it ${PROJECT_NAME}_solr curl -s "http://localhost:8983/solr/admin/cores?action=STATUS" | { grep -c "instanceDir" || true; })

sleep 10

if [[ "${cores}" == 0 ]]; then
    echo "No solr cores found, creating a drupal core"
    docker exec ${PROJECT_NAME}_solr make create core="drupal" -f /usr/local/bin/actions.mk
fi

# Execute this script from inside docker container.
# This wipes the db, copies db from stage and imports config.
docker exec ${PROJECT_NAME}_php scripts/sync_site.sh $sync_local

# This is the earliest point that we have a stable DB to backup
if [[ "$backup" == true ]]; then
    docker exec ${PROJECT_NAME}_php scripts/backup_db.sh
fi

# Generate Keys
echo "== Generating Keys..."
if [[ ! -d private ]]; then
    mkdir private
fi
cd private
if [[ ! -f private.key ]]; then
    openssl genrsa -out private.key 2048
fi
if [[ ! -f public.key ]]; then
    openssl rsa -in private.key -pubout > public.key
fi
chmod 600 private.key && chmod 600 public.key
cd ..

echo "http://${PROJECT_NAME}.localhost:8000 username: admin@example.com password: password"
