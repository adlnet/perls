def lambda_handler(event, context):
  import boto3
  import json
  from decouple import config

  session = boto3.Session()
  cf_client = session.client('cloudformation')
  lambda_client = session.client('lambda')
  tenant_info = event['body']
  tenant_data = json.loads(tenant_info)
  path_parameters = event['pathParameters']
  tenant = path_parameters['tenant'].lower()
  tenant_data['TENANT'] = tenant
  event['body'] = json.dumps(tenant_data)
  s3_bucket = config('s3_bucket')
  version = tenant_data['VERSION']
  template_url = 'https://' + s3_bucket + '.s3.amazonaws.com/' + version + '/code/scripts/tenant.yml'
  ce_payload = {"tenant": tenant}

  ce_response = lambda_client.invoke(
      FunctionName='config_export',
      InvocationType='RequestResponse',
      LogType='Tail',
      Payload=json.dumps(ce_payload)
  )
  print(ce_response)
  stack_update_response = cf_client.update_stack(
      StackName=tenant,
      TemplateURL=template_url,
      Parameters=[
          {
            'ParameterKey': 'TENANT',
            'ParameterValue': tenant
          },
          {
             'ParameterKey': 'VERSION',
             'ParameterValue': version
          },
          {
            'ParameterKey': 'DBPASSWORDARN',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPPASSWORDARN',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'PRIORITY',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'LRSHOST',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'LRSPASSWORDARN',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPUSERNAME',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPHOST',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPFROM',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPPORT',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SMTPPROTOCOL',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'BaseStack',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SNSTopic',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'Project',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'BRAND',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'ADDRESS',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'FIREBASEKEY',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'FIREBASEID',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'ALTHost',
            'UsePreviousValue': True
          },
          {
            'ParameterKey': 'SIMPLESAMLHOST',
            'UsePreviousValue': True
          }
      ],
      Capabilities=[
          'CAPABILITY_NAMED_IAM'
      ],
      Tags=[
          {
              'Key': 'Tenant',
              'Value': tenant
          },
      ]
  )
  print(stack_update_response)
  admin_response = lambda_client.invoke(
      FunctionName='admin_function',
      InvocationType='Event',
      LogType='Tail',
      Payload=json.dumps(event)
  )

  print(admin_response)
  return {'statusCode': 200}
