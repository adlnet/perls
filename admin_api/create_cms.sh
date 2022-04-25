#!/bin/bash
while [[ $# -gt 0 ]]; do
  case $1 in
    -u|--cms_username)
      CMS_USERNAME="$2"
      shift
      shift
      ;;
    -e|--email)
      EMAIL="$2"
      shift
      shift
      ;;
    -n|--full_name)
      FULL_NAME="$2"
      shift
      shift
      ;;
    -c|--cron_key)
      CRON_KEY="$2"
      shift
      shift
      ;;
    -*|--*)
      echo "Unknown option $1"
      echo "Necessary options:"
      echo "-u|--cms_username"
      echo "-e|--email"
      echo "-n|--full_name"
      echo "-c|--cron_key"
      exit 1
      ;;
  esac
done

if [ -z "$CMS_USERNAME" ]; then echo "User is empty; -u must be specified."; exit -1; fi
if [ -z "$EMAIL" ]; then echo "Email is empty; -e must be specified."; exit -1; fi
if [ -z "$FULL_NAME" ]; then echo "Full name is empty; -n must be specified."; exit -1; fi
if [ -z "$CRON_KEY" ]; then echo "Cron key is empty; -c must be specified."; exit -1; fi

drush status
drush deploy
drush user:create ${CMS_USERNAME} --mail="${EMAIL}"
drush user-add-role "perls_system_admin" ${CMS_USERNAME}
drush perls:update-user-field ${EMAIL} --field=field_name --value="${FULL_NAME}"
drush state-set system.cron_key $CRON_KEY
drush uli --mail=${EMAIL}
