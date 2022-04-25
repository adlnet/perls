"""Provides handler for creating an LRS via Veracity API
"""
import boto3
import requests


def lambda_handler(event, context):
    """Provides handler for creating an LRS via Veracity API

    Parameters
    ----------
    event: dict, required
        API Gateway Lambda Proxy Input Format

    Returns
    ------
    API Gateway Lambda Proxy Output Format: dict
    """

    ## Lambda doesn't understand from XXX import YYY at a global
    ## level from what I can tell. Allow from XXX import YYY
    from datetime import date # pylint: disable=import-outside-toplevel
    from decouple import config  # pylint: disable=import-outside-toplevel

    print("Starting creation of the lrs")
    toc_date = date.today().strftime("%m/%d/%y")
    session = boto3.Session()
    secrets_client = session.client('secretsmanager')
    lrs_api_key = config('lrs_api_key')
    tenant = event['TENANT'].lower()
    email = event['EMAIL']
    admin_uuid = config('admin_uuid')
    lrs_base_url = config('lrs_base_url')
    lrs_user_url = f'{lrs_base_url}admin/api/user'
    lrs_lrs_url = f'{lrs_base_url}admin/api/lrs'
    lrs_key_url = f'{lrs_base_url}api/{tenant}/xapi-access-keys'
    user_password_secret = secrets_client.get_secret_value(
        SecretId=f"{tenant}_user_password")
    user_password = user_password_secret['SecretString']
    lrs_key_password_secret = secrets_client.get_secret_value(
        SecretId=f"{tenant}_lrs_key_password")
    lrs_key_password = lrs_key_password_secret['SecretString']
    try:
        # TODO: Create Forwarder if they have an LRS already.

        ### Check for existing user
        search_query = f'{{"email": "{email}"}}'
        lrs_user_response = requests.get(f'{lrs_user_url}?search={search_query}',
            headers={"x-veracity-api-key": lrs_api_key})
        lrs_user_info = lrs_user_response.json()

        ### Create a new user if one doesn't exist
        if not lrs_user_info:
            lrs_create_user_json = {
                "username": email,
                "email": email,
                "publicAccount": True,
                "password": user_password,
                "acceptsTOS": toc_date,
                "verifiedEmail": True
            }
            lrs_user_response = requests.post(lrs_user_url,
                json=lrs_create_user_json, headers={"x-veracity-api-key": lrs_api_key})
            lrs_user_info = lrs_user_response.json()
        else:
            ## Select first one in array
            lrs_user_info = lrs_user_info[0]

        ### Create LRS for Tenant
        ### with given user
        user_uuid = lrs_user_info['uuid']
        lrs_create_json = {
            "owner": admin_uuid,
            "lrsName": tenant,
            "active": True,
            "strict": False,
            "compatibilityLevel": 0,
            "verboseLogs": True,
            "permissions": {
                user_uuid: ["lrs.edit", "lrs.**.edit",
                            "lrs.view", "lrs.**.view"]
            }
        }
        requests.post(lrs_lrs_url,
            json=lrs_create_json, headers={"x-veracity-api-key": lrs_api_key})

        ### Create API Key for LRS
        lrs_create_key_json = {
            "name": tenant,
            "read": True,
            "write": True,
            "jwt": False,
            "enabled": True,
            "advancedQueries": True,
            "limitedRead": False,
            "username": tenant,
            "password": lrs_key_password
        }
        requests.post(lrs_key_url, json=lrs_create_key_json,
            headers={"x-veracity-api-key": lrs_api_key})
    except Exception as ex:
        print(f'An exception occurred: {ex}')
        raise

    return {"message": f'The LRS has been successfully created for tenant {tenant}.'}
