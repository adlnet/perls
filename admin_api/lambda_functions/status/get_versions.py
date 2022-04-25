def lambda_handler(event, context):

  from decouple import config
  import json
  import boto3

  session = boto3.Session()
  s3_client = session.client('s3')
  s3_bucket = config('s3_bucket')
  versions = s3_client.list_objects_v2(Bucket=s3_bucket, Delimiter='/')['CommonPrefixes']
  version_list=[]
  for v in versions:
    version = v.get('Prefix')
    version_list.append(version.strip('/'))
  response = {"versions": version_list}
  return {
    "statusCode": 200,
    "body": json.dumps(response)
  }