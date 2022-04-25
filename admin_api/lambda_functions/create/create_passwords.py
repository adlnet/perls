def lambda_handler(event, context):
  import json
  import boto3
  import string
  import random
  import re
  from decouple import config

  print("Starting password creation function")
  session = boto3.Session()
  lambda_client = session.client('lambda')
  secrets_client = session.client('secretsmanager')
  cf_client = session.client('cloudformation')
  s3_client = session.client('s3')
  tenant_info = event['body']
  tenant_data = json.loads(tenant_info)
  version = tenant_data['VERSION']
  tenant = tenant_data['TENANT'].lower()
  email = tenant_data['EMAIL']
  smtp_password = tenant_data['SMTPPASSWORD']
  base_stack = config('base_stack')
  s3_bucket = config('s3_bucket')
  tenant_reg = '^[a-zA-Z0-9]+$'
  email_reg = r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b'

  if re.match(tenant_reg, tenant) and len(tenant) <= 50:
    print("Valid tenant name")
  else:
    return { "statusCode": 400,
              "body": "Invalid tenant name" 
            }

  if re.match(email_reg, email) and len(email) <= 75:
    print("Valid email address")
  else:
    return { "statusCode": 400,
              "body": "Invalid email address" 
            }          

  ###check if version is available
  version_check = s3_client.list_objects_v2(Bucket=s3_bucket,Prefix=version)

  if version_check['KeyCount'] == 0 :
    return { "statusCode": 404,
          "body": "CMS Version not available" 
        } 
  else:
    print("Version " + version + " of the cms exists.")

    ###check if tenant exists
    try:
      tenant_response = cf_client.describe_stack_resources(StackName=tenant)
    except:
      print("Tenant does not exist.  Creating tenant " + tenant)
    else:
      return { "statusCode": 400,
          "body": "Tenant already exists." 
        } 

    ###check if base stack exists
    try:
      base_stack_response = cf_client.describe_stack_resources(StackName=base_stack)
    except:
      raise Exception("The base stack " + base_stack + " does not exist")

  db_password = ''.join(random.choices(string.ascii_uppercase + string.ascii_lowercase + string.digits, k=20))
  secrets_client.create_secret(Name=tenant + "_db_password",SecretString=db_password)

  user_password = ''.join(random.choices(string.ascii_uppercase + string.ascii_lowercase + string.digits, k=20))
  secrets_client.create_secret(Name=tenant + "_user_password", SecretString=user_password)

  lrs_key_password = ''.join(random.choices(string.ascii_uppercase + string.ascii_lowercase + string.digits, k=20))
  secrets_client.create_secret(Name=tenant + "_lrs_key_password",SecretString=lrs_key_password)

  cron_key = ''.join(random.choices(string.ascii_lowercase + string.digits, k=20))
  secrets_client.create_secret(Name=tenant + "_cron_key",SecretString=cron_key)

  secrets_client.create_secret(Name=tenant + "_smtp_password", SecretString=smtp_password)
  db_response = lambda_client.invoke(
      FunctionName='create_db',
      InvocationType='Event',
      LogType='Tail',
      Payload=tenant_info
  )
  print(db_response)

  lrs_response = lambda_client.invoke(
      FunctionName='create_lrs',
      InvocationType='Event',
      LogType='Tail',
      Payload=tenant_info
  )
  print(lrs_response)

  cf_response = lambda_client.invoke(
      FunctionName='create_stack',
      InvocationType='Event',
      LogType='Tail',
      Payload=json.dumps(event)
  )
  print(cf_response)
  return { 'statusCode': 200 }
