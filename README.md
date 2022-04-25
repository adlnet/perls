# PERLS Web Application

The PERLS Web application contains the Learner web app, CMS, and a REST interface to drive data to and from the mobile application. The system is built on Drupal 9 and uses Docker containers to run services for easy configuration and deployment.

Ensure all the requirements are met and follow the install instructions to get started.

## Installation

There are a few ways to deploy PERLS, but the simplest way would be to just use Docker and configure a non-root user.

### Local Deployment TL;DR

Assuming you are on an Ubuntu 20+ machine with Docker and Docker-Compose installed:

- Download the [Starter Content](https://github.com/adlnet/perls/releases/download/v3.0.0/starter-content.zip)
- `git clone https://github.com/adlnet/perls`
- `cd perls`
- From the Starter Content, add the `files` folder and `db_backup.sql` to the project root
- `cp .env.example .env` and configure your `.env` file (see below)
- `./scripts/build.sh -l -n`
- `docker exec PERLS_php ./scripts/styles.sh`

Once everything's up, the default login is just:
```
username: admin@example.com
password: password
```

and it should be accessible at `perls.localhost:8000`.


## Development Environment

Standing up a development environment is a bit more involved and requires things to be installed locally on your machine.

### Requirements

* [Docker](https://www.docker.com/)
* [Composer](https://getcomposer.org)
* [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer#installation)
* Port 8000 (development) or port 80 (production) and 3306 must be available

### Building the Development Environment
The fastest way to set up your development environment is to have a starter database (i.e. synced from a staging environment).

After completion, you can reach your local environment at:
http://perls.localhost:8000

#### Option A: With a Starter Database (db_backup.sql)
Your working directory must contain a `db_backup.sql` file.

``` bash
./scripts/build.sh -l -n
```

#### Option B: Without a Starter Database
You can still run the build script without a starter database. In this instance, Drupal will attempt to build the site from scratch and then save the database to `db_backup.sql` (to save time on future builds). **This will take a long time.**

``` bash
./scripts/build.sh -l
```

#### Option C: From a Staging Environment
You can also define a `stage` drush site alias and build your local development environment from an existing remote environment.

See the drush documentation on [creating a site alias](https://www.drush.org/latest/site-aliases/). With the site alias defined, you can execute:

``` bash
./scripts/build.sh
```

#### Tip
If your staging environment requires an SSH key, you can place that in `.ssh`.

## Environment Variables
The development environment is built by default when using the given `.env` and build script.
If these have been modified, ensure the environment variable `PROJECT_ENV` is set to `dev`.
This allows certain modules and configurations to be installed which improve quality of life when developing.

* `PROJECT_ENV`
* `DB_HOST` - Database host name. Default value is `mariadb`
* `DB_NAME` - Database name. Default value: `drupal_perls`.
* `DB_PASSWORD` - Database password. Default value is `drupal`.
* `DB_HOST_PORT` - Database port. Default value is `3306`.
* `DB_USER` - Databse user. Default value is `drupal`.
* `DB_DRIVER` - Database user. Default value is `mysql`.
* `SMTP_HOST` - SMTP server host name. Default value is `mailhog`.
* `SMTP_PORT` - SMTP server port. Default value is `1025`.
* `SMTP_FROM` - Sender email address. Default value is `perls@dev.local`.

There are some service what you can configure from .env file.
* `LRS_HOST` - Host name of the LRS server
* `LRS_USERNAME` - Account name
* `LRS_PASSWORD` - Password of LRS account
* `FIREBASE_SERVER_KEY` - Server key for firebase service, which sends out push notifications.
* `FIREBASE_SENDER_ID` - Served ID of firebase.
* `UNSPLASH_APP_NAME` - Account name of unsplash free image service.
* `UNSPLASH_ACCESS_KEY` - Access key of unsplash service.

SSO
* `SIMPLESAML_AUTH_SOURCE` - This key will store the authentication source config
* `SIMPLESAML_CONFIG_DIR` - Where uploaded IdP metadata is stored
* `SIMPLESAML_SSO_SP_METADATA_FILE` - Mame of the Service Provider metadata file downloaded from the web interface


## Debuggging
### Rebuilding styles

``` bash
docker exec PERLS_php ./scripts/styles.sh
```

### Using xdebug
Xdebug can be enabled by setting the `ENABLE_XDEBUG` environment variable to `true` on the PHP container in `docker-compose.yml` and restarting the stack.

### Troubleshooting

See [Troubleshooting](./readme/troubleshooting.md)

## Testing

This project has behat feature tests. They are best run from inside the PHP docker container.
This can be achieved by running:

``` bash
./scripts/test.sh
```

## Guidelines

### Coding standards

Code should be readible, portable, and standardize. Since the web application is based on Drupal,
all custom code should be placed in the appropriate locations (either theme/custom or modules/custom).
Changes should not be made to Drupal Core so other releases of Drupal can be easily updated.

The code should follow [Drupal's coding standards](https://www.drupal.org/node/318) which can be enforced with
PHPCodeSniffer.

A good place to start writing a custom module in order to change/update functionality of Drupal,
follow the Drupal documentation [Creating custom modules](https://www.drupal.org/docs/creating-custom-modules).
It contains a code skeleton, naming standards, and other information to get started.

When developing, follow the [contribution guidelines](#contribution-guidelines) and
[Drupal's guidelines](https://www.drupal.org/docs/develop).

### Maintenance

See [Maintenance](./readme/maintenance.md)

### Security

See [Security](./readme/security.md)

-------
Created by Float.
For more information, contact them at <info@gowithfloat.com>
