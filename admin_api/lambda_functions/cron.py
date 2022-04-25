def lambda_handler(event, context):
    import requests
    import boto3

    session = boto3.Session()
    secrets_client = session.client('secretsmanager')
    cf_client = session.client('cloudformation')
    tenant = event['tenant']
    cron_key_secret = secrets_client.get_secret_value(SecretId=tenant + "_cron_key")
    cron_key = cron_key_secret['SecretString']
    
    tenant_stack_info = cf_client.describe_stacks(StackName=tenant)
    env_vars = tenant_stack_info['Stacks'][0]['Parameters']
    for var in env_vars:
      if var['ParameterKey'] == 'ADDRESS':
        address = var['ParameterValue']
        full_address = "https://" + address + "/cron/" + cron_key
    cron_response = requests.get(full_address) 
    try:
        cron_response.raise_for_status()
    except requests.exceptions.HTTPError as e:
        return "Error: " + str(e)   
    return
