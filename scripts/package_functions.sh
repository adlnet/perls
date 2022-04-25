#!/bin/bash
if [ !  -d "venv/lib/python3.8/site-packages/" ]
then
  python3 -m venv venv
  source venv/bin/activate
  pip3 install  -r admin_api/lambda_functions/requirements.txt
fi
mkdir functions
cd venv/lib/python3.8/site-packages/
zip -r ../../../../functions/lambda_package.zip .
cd ../../../../admin_api/lambda_functions/create/
cp ../../functions/lambda_package.zip ../../functions/admin_container.zip
cp ../../functions/lambda_package.zip ../../functions/create_db.zip
cp ../../functions/lambda_package.zip ../../functions/create_passwords.zip
cp ../../functions/lambda_package.zip ../../functions/create_stack.zip
cp ../../functions/lambda_package.zip ../../functions/create_lrs.zip
cp ../../functions/lambda_package.zip ../../functions/create_solr_fs.zip
cp ../../functions/lambda_package.zip ../../functions/admin_container.zip
cp ../../functions/lambda_package.zip ../../functions/update_stack.zip
cp ../../functions/lambda_package.zip ../../functions/update.zip
cp ../../functions/lambda_package.zip ../../functions/config_export.zip
cp ../../functions/lambda_package.zip ../../functions/invoke_delete.zip
cp ../../functions/lambda_package.zip ../../functions/delete_tenant.zip
cp ../../functions/lambda_package.zip ../../functions/tenant_status.zip
cp ../../functions/lambda_package.zip ../../functions/tenant_list.zip
cp ../../functions/lambda_package.zip ../../functions/cron.zip
cp ../../functions/lambda_package.zip ../../functions/get_versions.zip
zip -g ../../functions/create_db.zip create_db.py
zip -g ../../functions/create_passwords.zip create_passwords.py
zip -g ../../functions/create_stack.zip create_stack.py
zip -g ../../functions/create_lrs.zip create_lrs.py
zip -g ../../functions/create_solr_fs.zip create_solr_fs.py
cd ../
zip -g ../functions/admin_container.zip admin_container.py
cd update
zip -g ../../functions/update_stack.zip update_stack.py
zip -g ../../functions/update.zip update.py
zip -g ../../functions/config_export.zip config_export.py
cd ../delete
zip -g ../../functions/delete_tenant.zip delete_tenant.py
cd ../status
zip -g ../../functions/tenant_status.zip tenant_status.py
zip -g ../../functions/tenant_list.zip tenant_list.py
zip -g ../../functions/get_versions.zip get_versions.py
cd ../../
aws s3 cp functions s3://$1/functions --recursive --profile $2
