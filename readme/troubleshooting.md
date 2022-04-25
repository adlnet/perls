# Troubleshooting

## Build script fails

If the `build.sh` script fails with a database message, running it a second time may work.

## Drupal Warning - Permissions

There may be permissions issues running on MacOS; there are slightly different permissions
between the Linux Docker image and MacOS. This can occur after clearing the cache.
If there are permission errors, running
`chmod 777 web/sites/default && mkdir -p web/sites/default/files/php/twig` might help.

## Drush from host

For drush to work from the host it needs access to the code base and a
database connection. The code base is already accessable as long as we
are somewhere inside the web/ folder. For drush to see our docker container
datatbase, we need to add a line to our host file:

If your .env DB_HOST is mariadb, then your host file line would need to look like this:

On Mac, edit /private/etc/hosts with elevated privileges.

127.0.0.1       mariadb

*mariadb matches the docker-compose container for the project database.
Changing DB_HOST in the .env file would require the docker-compose.yml
file to be updated as well. This is not advised.

If you are having problems running drush locally, you may need to export .env variables.
    set -a
    source .env
