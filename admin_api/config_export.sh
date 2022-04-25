#!/bin/bash
drush config-split:export ignored_config -y
# This trigger a warning if the tenant_overrides isn't active.
drush config-split:export tenant_overrides -y
