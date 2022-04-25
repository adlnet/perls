#!/bin/bash

TENANT=${TENANT,,}
source /var/www/${TENANT}/tenant.env
export BRAND=PERLS

TaskDefARN=$(aws ecs describe-services --services ${TENANT}-php-svc --cluster PERLSECSCluster  | jq '.services[0].deployments[0].taskDefinition' -r)
SOLRTaskDefARN=$(aws ecs describe-services --services ${TENANT}-solr-svc --cluster PERLSECSCluster | jq '.services[0].deployments[0].taskDefinition' -r)
TASKDEFVER=${TaskDefARN##*:}
TASKDEFVER_new=$(($TASKDEFVER+1))

drush config-split:export ignored_config -y
# This trigger a warning if the tenant_overrides isn't active.
drush config-split:export tenant_overrides -y
unlink private
unlink web/sites/default/files
echo "Updating code base"
aws s3 sync s3://perls-repo-pt/${VERSION}/code/ . --delete --quiet --exclude web/sites/default/settings.local.php --exclude tenant.env --exclude tenant.properties

ln -s /efs/${TENANT}/private/ private
ln -s /efs/${TENANT}/public/ web/sites/default/files

sudo chown -R $(whoami): /efs/${TENANT}/{private,public}

drush status
rm -rf vendor
composer install
drush updatedb --entity-updates --no-post-updates -y
drush cr
drush config-import -y
drush config-import -y
drush updatedb -y
drush cr

sudo chown -R 1000:1000 /efs/${TENANT}/{private,public}

echo "Updating ${TENANT} CloudFormation stack"
aws cloudformation update-stack --stack-name ${TENANT} --template-body file://scripts/perls_tenants/PERLS_tenant.yml --parameters \
ParameterKey=VERSION,ParameterValue=${VERSION} \
ParameterKey=TASKDEFVER,ParameterValue=${TASKDEFVER_new} \
ParameterKey=TENANT,UsePreviousValue=true \
ParameterKey=DBPASSWORD,UsePreviousValue=true \
ParameterKey=SMTPPASSWORD,UsePreviousValue=true \
ParameterKey=SMTPUSERNAME,UsePreviousValue=true \
ParameterKey=SMTPFROM,UsePreviousValue=true \
ParameterKey=SMTPPORT,UsePreviousValue=true \
ParameterKey=SMTPHOST,UsePreviousValue=true \
ParameterKey=SMTPPROTOCOL,ParameterValue=tls \
ParameterKey=PRIORITY,UsePreviousValue=true \
ParameterKey=LRSPASSWORD,UsePreviousValue=true \
ParameterKey=LRSUSERNAME,UsePreviousValue=true \
ParameterKey=LRSHOST,UsePreviousValue=true \
ParameterKey=SOLRTASKDEFVER,UsePreviousValue=true \
ParameterKey=SIMPLESAMLAUTHSOURCE,UsePreviousValue=true  \
ParameterKey=SIMPLESAMLCONFIGDIR,UsePreviousValue=true \
ParameterKey=StackName,UsePreviousValue=true

aws ecs wait services-stable --cluster PERLSECSCluster --services ${TENANT}-php-svc

