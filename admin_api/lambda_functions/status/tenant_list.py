def lambda_handler(event, context):
  import boto3
  import json
  from dateutil.tz import tzutc

  session = boto3.Session()
  cf_client = session.client('cloudformation')
  stacks = cf_client.describe_stacks()['Stacks']
  response = []
  for stack in stacks:
    try:    
      if stack['Tags'][0]['Key'] == 'Tenant':
        tenant = stack['Tags'][0]['Value']
        stack_status = stack['StackStatus']
    except:
      continue
    parameters = stack['Parameters']
    for parameter in parameters:
      if 'VERSION' in parameter['ParameterKey']:
        version = parameter['ParameterValue']

    tenant_status = {
      "name": tenant,
      "status": stack_status,
      "version": version
    }
    response.append(tenant_status)
    response_body={"tenants": response}
  return {
    "statusCode": 200,
    "body": json.dumps(response_body)
  }