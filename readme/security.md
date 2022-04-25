# Security

## Drupal
Drupal has been proven to be reliable and fight against critical internet vulnerabilities. Go here for more information about [Drupal Security](https://www.drupal.org/features/security).

In general the best way to stay protected is by keeping the system up to date. Drupal has checks on the [Status Report](./maintenance.md#View-System-Status-Report) to ensure you are up to date.

## REST API
The REST API is necessary for the mobile application to communicate with the web application. It uses oAuth2 to authenticate requests using the [Simple oAuth module](https://www.drupal.org/project/simple_oauth). To be secure, it is necessary the public and private keys this modules uses are not accessible via the web.

Users request OAuth tokens via the `/login` endpoint. These tokens have an expiration time, which can be set by the System Administrator. The tokens can be revoked manually by the System Administrator.
