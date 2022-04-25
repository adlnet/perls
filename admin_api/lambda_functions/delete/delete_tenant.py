def lambda_handler(event, context):
  import shutil
  import requests
  import boto3
  import json
  from decouple import config
  import mysql.connector

  session = boto3.Session()
  cf_client = session.client('cloudformation')
  secrets_client = session.client('secretsmanager')
  rds_secret = secrets_client.get_secret_value(SecretId='rds_admin_login')
  rds_info = json.loads(rds_secret['SecretString'])
  rds_user = rds_info['username']
  rds_password = rds_info['password']
  rds_host = rds_info['host']
  lrs_api_key = config('lrs_api_key')
  lrs_url = config('lrs_url')
  path_parameters = event['pathParameters']
  tenant = path_parameters['tenant'].lower()

  get_lrs = requests.get(lrs_url + 'lrs?limit=0', headers = {"Content-Type": "application/json", "x-veracity-api-key": lrs_api_key })
  lrs_info = get_lrs.json()
  for lrs in lrs_info:
    if lrs['lrsName'] == tenant:
      lrs_id = lrs['_id']
      requests.delete(lrs_url + 'lrs/' + lrs_id, headers = {"Content-Type": "application/json", "x-veracity-api-key": lrs_api_key })

  stack = cf_client.describe_stacks(StackName=tenant)

  secrets_client.delete_secret(SecretId=tenant + "_db_password")
  secrets_client.delete_secret(SecretId=tenant + "_user_password")
  secrets_client.delete_secret(SecretId=tenant + "_lrs_key_password")
  secrets_client.delete_secret(SecretId=tenant + "_cron_key")
  secrets_client.delete_secret(SecretId=tenant + "_smtp_password")

  # Remove files in EFS
  if(not (tenant and not tenant.isspace())):
    print('Tenant is empty. Please remove files from EFS.')
  else:
    dest = "/mnt/efs/" + tenant
    shutil.rmtree(dest)

  parameters = stack['Stacks'][0]['Parameters']
  for parameter in parameters:
    if 'BRAND' in parameter['ParameterKey']:
        brand = parameter['ParameterValue']
        db_name = tenant + "_" + brand

  cf_client.delete_stack(StackName=tenant)

  mydb = mysql.connector.connect(
    host=rds_host,
    user=rds_user,
    password=rds_password
  )
  mycursor = mydb.cursor()
  mycursor.execute("DROP DATABASE {}".format(db_name))
  mycursor.execute("DROP USER {}@'10.0.0.0/255.255.0.0'".format(tenant))
  mycursor.close()
  mydb.close()

  return { "statusCode": 200 }
