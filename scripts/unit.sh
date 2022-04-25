#!/bin/bash

docker exec ${PROJECT_NAME}_php ./vendor/bin/phpunit -v -c ./phpunit.xml ./web/core/modules/node/tests/src/Unit
