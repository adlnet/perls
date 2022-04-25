def lambda_handler(event, context):
  import boto3
  import json

  session = boto3.Session()
  cf_client = session.client('cloudformation')
  lambda_client = session.client('lambda')
  path_parameters = event['pathParameters']
  tenant = path_parameters['tenant'].lower()

  ###check if tenant exists
  try:
    cf_client.describe_stack_resources(StackName=tenant)
  except:
    return {
      "statusCode": 404,
      "body": "Tenant does not exist"
    }
  us_response = lambda_client.invoke(
      FunctionName='delete_tenant',
      InvocationType='Event',
      LogType='Tail',
      Payload=json.dumps(event)
  )
  print(us_response)
  return { 'statusCode': 200 }
