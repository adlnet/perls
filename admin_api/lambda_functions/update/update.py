def lambda_handler(event, context):
  import boto3
  import json
  from decouple import config

  tenant_info = json.loads(event['body'])
  session = boto3.Session()
  s3_client = session.client('s3')
  s3_bucket = config('s3_bucket')
  version = tenant_info['VERSION']
  cf_client = session.client('cloudformation')
  lambda_client = session.client('lambda')
  pathParameters = event['pathParameters']
  tenant = pathParameters['tenant'].lower()

  ###check if version is available
  version_check = s3_client.list_objects_v2(Bucket=s3_bucket,Prefix=version)

  if version_check['KeyCount'] == 0 :
    return { "statusCode": 400,
        "body": "Version does not exist" 
        }
  else:
    print("Version " + version + " of the cms exists.")

  ###check if tenant exists
  try:
    tenant_response = cf_client.describe_stack_resources(StackName=tenant)
    print("Tenant exists.  Updating tenant " + tenant + " to version " + version )
  except:
    return { "statusCode": 404,
            "body": "Tenant does not exist" 
            }
  us_response = lambda_client.invoke(
      FunctionName='update_stack',
      InvocationType='Event',
      LogType='Tail',
      Payload=json.dumps(event)
    )
  print(us_response)
  return {'statusCode': 200}