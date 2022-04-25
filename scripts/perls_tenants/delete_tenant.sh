#!/bin/bash
#This file has been copied to the /home/centos/perls directory of the centos user on the utility server
TENANT=$1
read -p "Type 'delete' if you are sure you want to delete tenant $TENANT: " CONFIRM
if [ "$CONFIRM" = "delete" ]; then
  echo "Deleting tenant $TENANT"
  TENANT=${TENANT,,}
  RDS_HOST=$(aws cloudformation describe-stacks  --stack-name PERLS | jq '.Stacks[].Outputs[] | select(.OutputKey=="PERLSRDS") | .OutputValue' -r)
  RDS_PWD=$(aws cloudformation describe-stacks  --stack-name PERLS | jq '.Stacks[].Parameters[] | select(.ParameterKey=="RDSMasterPassword") | .ParameterValue' -r)
  aws cloudformation delete-stack --stack-name ${TENANT}
  mysql -h ${RDS_HOST} -u 'perls_user' -p${RDS_PWD} -e "DROP DATABASE ${TENANT}_PERLS;"
  sudo rm -rf /efs/${TENANT}
  rm -rf /var/www/${TENANT}
else
  exit 0
fi
