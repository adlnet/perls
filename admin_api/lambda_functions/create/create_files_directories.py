"""Copies public starter files
Uses a different method in order to ensure the correct
user is mounted to /efs
"""

import json
import os
import shutil
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

    print("Start to copy files process")
    tenant_info = event['body']
    tenant_data = json.loads(tenant_info)
    version = tenant_data['VERSION']
    tenant = tenant_data['TENANT'].lower()

    print("Create directories")
    destination = f"/mnt/efs/{tenant}"
    os.mkdir(f'{destination}/private')

    print("Copying public files.")
    files_source = f'/mnt/efs/version/{version}/starter/public'
    files_dest = f'{destination}/public'
    shutil.copytree(files_source, files_dest)

    return {'message': 'Files sucessfully copied'}
