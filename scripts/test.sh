#!/bin/bash
set -e

#
# Runs tests against the Drupal site and writes results to `tests/artifacts`.
# Must be run from git root.

set -a

print_usage() {
  echo "test.sh Run Behat tests for Perls"
  echo " "
  echo "test.sh [options]"
  echo " "
  echo "options:"
  echo "-h, --help                show brief help"
  echo "-a                        Run all tests core and recommendation engine."
}
run_all=false
while getopts 'a' flag; do
  case "${flag}" in
    a) run_all=true ;;
    *) print_usage
       exit 1 ;;
  esac
done

TAGS='@core_functionality'
if [[ "$run_all" == true ]]; then
    TAGS='@core_functionality,@recommendation_engine'
fi

#Make sure environmental variables are setup
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "No .env file found. Copying .env.example to .env"
        cp .env.example .env
    fi
fi

# Check that screenshots folder is existing.
if [ -d "artifacts/screenshots" ]; then
  rm -R artifacts
  mkdir -p artifacts/screenshots
else
  mkdir -p artifacts/screenshots
fi

# Load environmental variables
source .env


if [ $(docker inspect -f {{.State.Running}} ${PROJECT_NAME}_chrome 2>/dev/null) ]; then
  echo "Remove the old chrome container"
	docker stop ${PROJECT_NAME}_chrome
  docker rm ${PROJECT_NAME}_chrome
fi

echo "Starting tests..."
# Check to see if the app container is already running
ALREADY_RUNNING=$(docker inspect -f {{.State.Running}} ${PROJECT_NAME}_php 2>/dev/null)

if [ ! $ALREADY_RUNNING = "true" ]; then
	docker-compose up -d
fi

echo "Starting a Google Chrome container for running the Behat tests..."
# The Google Chrome container must have access to the file system in order to upload files.
docker run -d --network=${PROJECT_NAME}_network \
--cap-add=SYS_ADMIN \
--publish=9222 --name="${PROJECT_NAME}_chrome" \
justinribeiro/chrome-headless

ALREADY_RUNNING=$(docker inspect -f {{.State.Running}} ${PROJECT_NAME}_chrome 2>/dev/null)

if [ ! $ALREADY_RUNNING = "true" ]; then
	echo 'Chrome container has stopped.'
	exit 1
fi

echo "Starting UI tests..."
BEHAT_PARAMS="{\"extensions\":{\"Drupal\\\MinkExtension\":{\"sessions\":{\"javascript\":{\"chrome\":{\"api_url\":\"http://${PROJECT_NAME}_chrome:9222\"}},\"browserChrome\":{\"chrome\":{\"api_url\":\"http://${PROJECT_NAME}_chrome:9222\"}}}}}}"
echo $BEHAT_PARAMS
docker exec -e BEHAT_PARAMS=$BEHAT_PARAMS ${PROJECT_NAME}_php ./vendor/bin/behat --init
docker exec -e BEHAT_PARAMS=$BEHAT_PARAMS ${PROJECT_NAME}_php ./vendor/bin/behat \
--config=./behat.yml --colors --tags $TAGS

mkdir ./web/sites/simpletest
mkdir ./web/sites/simpletest/browser_output
chmod 777 ./web/sites/simpletest/browser_output

./scripts/unit.sh

echo "Shutting down Chrome..."

docker network disconnect -f ${PROJECT_NAME}_network ${PROJECT_NAME}_chrome
docker stop ${PROJECT_NAME}_chrome
docker rm ${PROJECT_NAME}_chrome -f

echo "Finished running tests! 🎉"
