def lambda_handler(event, context):
  import boto3
  import json
  from decouple import config
  from dateutil.tz import tzutc

  session = boto3.Session()
  cf_client = session.client('cloudformation')
  pathParameters = event['pathParameters']
  tenant = pathParameters['tenant'].lower()
  try:
    stack = cf_client.describe_stacks(StackName=tenant)
  except:
    raise Exception("The tenant " + tenant + " does not exist.")

  parameters = stack['Stacks'][0]['Parameters']
  for parameter in parameters:
    if 'VERSION' in parameter['ParameterKey']:
      version = parameter['ParameterValue']

  stack_status = stack['Stacks'][0]['StackStatus']

  response_body = {
    "name": tenant,
    "status": stack_status,
    "version": version
  }

  return {
    "statusCode": 200,
    "body": json.dumps(response_body)
  }
