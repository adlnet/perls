"""
Triggers jobs to create tenant and dependencies
"""

import os
import json
import boto3
def lambda_handler(event, context):
    """
      Parameters
      ----------
      event: dict, required
          API Gateway Lambda Proxy Input Format

      Returns
      ------
      API Gateway Lambda Proxy Output Format: dict
      """

    from decouple import config  # pylint: disable=import-outside-toplevel

    print("Starting stack creation")
    session = boto3.Session()
    cf_client = session.client('cloudformation')
    secrets_client = session.client('secretsmanager')
    elb_client = session.client('elbv2')
    lambda_client = session.client('lambda')
    tenant_data = json.loads(event['body'])
    version = tenant_data['VERSION']
    tenant = tenant_data['TENANT'].lower()
    smtp_username = tenant_data['SMTPUSERNAME']
    smtp_host = tenant_data['SMTPHOST']
    smtp_from = tenant_data['SMTPFROM']
    smtp_port = tenant_data['SMTPPORT']
    smtp_protocol = tenant_data.get('SMTPPROTOCOL') or 'tls'
    brand = tenant_data['BRAND']

    #set vars for later use
    s3_bucket = config('s3_bucket')
    project = config('project')
    snstopic = config('snstopic')
    base_stack = config('base_stack')
    cms_base_url = config('cms_base_url')
    lrs_base_url = config('lrs_base_url')
    firebase_key= config('firebase_key')
    firebase_id = config('firebase_id')

    template_url = f'https://{s3_bucket}.s3.amazonaws.com/{version}/code/scripts/tenant.yml'
    lrs_host = f'{lrs_base_url}{tenant}/xapi/'

    address = f'{tenant}.perls.{cms_base_url}'
    listener_name = 'PERLSAppLBListener443'

    ### Get info from secrets manager
    db_password_secret = secrets_client.get_secret_value(
        SecretId=f'{tenant}_db_password')
    lrs_key_password_secret = secrets_client.get_secret_value(
        SecretId=f'{tenant}_lrs_key_password')
    smtp_password_secret = secrets_client.get_secret_value(
        SecretId=f'{tenant}_smtp_password')

    print("Copying starter content.")
    dirs=os.listdir('/mnt/efs/')
    print(dirs)
    dest = f"/mnt/efs/{tenant}"
    os.mkdir(dest)
    os.mkdir(dest + "/solr")

    # Run Lambda function to expand public/private files with efs ap where
    # POSIX uid and gid set to 1000 so solr can access the dir
    lambda_response = lambda_client.invoke(
        FunctionName='create_files_directories',
        InvocationType='Event',
        LogType='Tail',
        Payload=json.dumps(event)
    )

    # Run Lambda function to expand solr files with efs ap where
    # POSIX uid and gid set to 8983 so solr can access the dir
    print(lambda_response)
    lambda_response = lambda_client.invoke(
        FunctionName='create_solr_fs',
        InvocationType='Event',
        LogType='Tail',
        Payload=json.dumps(event)
    )
    print(lambda_response)

    #Get LB Listener and next priority number info
    app_listener_info = cf_client.describe_stack_resource(
        StackName=base_stack, LogicalResourceId=listener_name)
    #TODO: Change listerner name for PERLS stack
    app_listener_arn = app_listener_info['StackResourceDetail']['PhysicalResourceId']
    listener_rules = elb_client.describe_rules(ListenerArn=app_listener_arn)
    rules = listener_rules['Rules']
    priority_int = 0
    for rule in rules:
        try:
            rule_priority = int(rule['Priority'])
            if rule_priority > priority_int:
                priority_int = int(rule['Priority'])
        except (TypeError, NameError, KeyError, ValueError) as ex:
            print(f'An exception occurred setting rules: {ex}')
            break

    priority_int += 1
    priority = str(priority_int)

    ### Create Cloudformation tenant stack
    stack_creation_response = cf_client.create_stack(
        StackName=tenant,
        TemplateURL=template_url,
        Parameters=[
            {
                'ParameterKey': 'TENANT',
                'ParameterValue': tenant,
            },
            {
                'ParameterKey': 'VERSION',
                'ParameterValue': version,
            },
            {
                'ParameterKey': 'DBPASSWORDARN',
                'ParameterValue': db_password_secret['ARN'],
            },
            {
                'ParameterKey': 'SMTPPASSWORDARN',
                'ParameterValue': smtp_password_secret['ARN'],
            },
            {
                'ParameterKey': 'PRIORITY',
                'ParameterValue': priority,
            },
            {
                'ParameterKey': 'LRSHOST',
                'ParameterValue': lrs_host,
            },
            {
                'ParameterKey': 'LRSPASSWORDARN',
                'ParameterValue': lrs_key_password_secret['ARN'],
            },
            {
                'ParameterKey': 'SMTPUSERNAME',
                'ParameterValue': smtp_username,
            },
            {
                'ParameterKey': 'SMTPHOST',
                'ParameterValue': smtp_host,
            },
            {
                'ParameterKey': 'SMTPFROM',
                'ParameterValue': smtp_from,
            },
            {
                'ParameterKey': 'SMTPPROTOCOL',
                'ParameterValue': smtp_protocol,
            },
            {
                'ParameterKey': 'SMTPPORT',
                'ParameterValue': smtp_port,
            },
            {
                'ParameterKey': 'BaseStack',
                'ParameterValue': base_stack,
            },
            {
                'ParameterKey': 'SNSTopic',
                'ParameterValue': snstopic,
            },
            {
                'ParameterKey': 'Project',
                'ParameterValue': project,
            },
            {
                'ParameterKey': 'BRAND',
                'ParameterValue': brand,
            },
            {
                'ParameterKey': 'ADDRESS',
                'ParameterValue': address,
            },
            {
                'ParameterKey': 'FIREBASEKEY',
                'ParameterValue': firebase_key,
            },
            {
                'ParameterKey': 'FIREBASEID',
                'ParameterValue': firebase_id,
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
    print(stack_creation_response)
    lambda_response = lambda_client.invoke(
      FunctionName='admin_function',
      InvocationType='Event',
      LogType='Tail',
      Payload=json.dumps(event)
    )
    print(lambda_response)
    return {'message': 'Stack creation successful'}
