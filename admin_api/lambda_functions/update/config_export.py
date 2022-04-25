def lambda_handler(event, context):
  import boto3
  from decouple import config

  print("Starting Admin container")
  session = boto3.Session()
  cf_client = session.client('cloudformation')
  ecs_client = session.client('ecs')
  tenant = event['tenant'].lower()
  base_stack = config('base_stack')
  base_stack_info = cf_client.describe_stacks(StackName=base_stack)
  task_definition = tenant + '-php-admin-task'

  outputs = base_stack_info['Stacks'][0]['Outputs']
  subnets=[]
  for output in outputs:
    if 'PrivateSubnet' in output['OutputKey']:
        subnets.append(output['OutputValue'])
    if 'ECSCluster' in output['OutputKey']:
      ecs_cluster = output['OutputValue']
    if 'SGECS' in output['OutputKey']:
      ecs_security_group = output['OutputValue']

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
    overrides={ 'containerOverrides': [
      {
        'name': tenant + '-php',
        'command': [
            "sh", "/var/www/html/admin_api/config_export.sh", tenant
        ]
      }
    ]
    }
  )
