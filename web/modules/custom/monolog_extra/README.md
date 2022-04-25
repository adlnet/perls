INTRODUCTION
------------
This module extends the logging output of Monolog modules LineFormatter.
This reduces memory usage by shortening the backtrace information.

REQUIREMENTS
------------
- [Monolog module](https://www.drupal.org/project/monolog)

INSTALLATION
------------
* Enable and configure Monolog module.
* Enable the _Monolog Extra_ module from "/admin/modules"

### CONFIGURATION:

Module configurations could be are done from"/admin/config/development/logging/monolog-extra".

* Install the module
* Open the site specific services.yml (Eg: monolog.services.yml).
* Add/Change the formatter under parameters block as shown below in the example.

For Example

```
parameters:
  monolog.channel_handlers:
    default: ['drupal.dblog']
  monolog.processors: ['message_placeholder', 'current_user', 'request_uri']

```
**The above configurations will be replaced by the following**

```
parameters:
  monolog.channel_handlers:
    default:
      handlers: ['drupal.dblog']
      formatter: 'minimal_line'
  monolog.processors: ['message_placeholder', 'current_user', 'request_uri']
```
