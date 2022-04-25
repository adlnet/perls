def lambda_handler(event, context):
  import boto3
  import time
  import json
  from decouple import config

  print("Starting Admin container")
  session = boto3.Session()
  cf_client = session.client('cloudformation')
  ecs_client = session.client('ecs')
  secrets_client = session.client('secretsmanager')
  base_stack = config('base_stack')
  tenant_info = event['body']
  tenant_data = json.loads(tenant_info)
  tenant = tenant_data['TENANT'].lower()
  task_definition = tenant + '-php-admin-task'
  method = event['httpMethod']

  ###check if tenant exists
  try:
    response = cf_client.describe_stack_resources(StackName=tenant)
  except:
    raise Exception('Tenant '+ tenant +' does not exist.')
  else:
    print("Updating Tenant " + tenant)

  ### Wait until the CF stack has finished created/updated
  stack_status_complete = 'stack_create_complete' if method == 'POST' else 'stack_update_complete'
  try:
    waiter = cf_client.get_waiter(stack_status_complete)
    waiter.wait(StackName=tenant)
  except boto3.exceptions.ClientError as ex:
    error_message = ex.response['Error']['Message']
    if error_message == 'No updates are to be performed.':
        print("No changes")
    else:
        raise

  cron_key_secret = secrets_client.get_secret_value(SecretId=tenant + "_cron_key")
  cron_key = cron_key_secret['SecretString']
  base_stack_info = cf_client.describe_stacks(StackName=base_stack)
  outputs = base_stack_info['Stacks'][0]['Outputs']
  subnets=[]
  for output in outputs:
    if 'PrivateSubnet' in output['OutputKey']:
        subnets.append(output['OutputValue'])
    if 'ECSCluster' in output['OutputKey']:
      ecs_cluster = output['OutputValue']
    if 'SGECS' in output['OutputKey']:
      ecs_security_group = output['OutputValue']
  command = [
    "drush", "deploy"
  ]
  if method == 'POST':
    email = tenant_data['EMAIL']
    cms_username = tenant + "_admin"
    full_name = tenant_data['FULL_NAME']
    command = ["sh", "/var/www/html/admin_api/create_cms.sh",
               "-u", cms_username,
               "-e", email,
               "-n", full_name,
               "-c", cron_key]

  ecs_client.run_task(
    cluster=ecs_cluster,
    launchType='FARGATE',
    taskDefinition=task_definition,
    count=1,
    platformVersion='1.4.0',
    networkConfiguration={
      'awsvpcConfiguration': {
          'subnets': subnets,
          'securityGroups': [
              ecs_security_group,
          ],
          'assignPublicIp': 'ENABLED'
      }
    },
      overrides={'containerOverrides': [
        {
          'name': tenant + '-php',
          'command': command
        }
      ]
    }
  )

  return { 'statusCode': 200 }
