def lambda_handler(event, context):
  import tarfile
  import json

  print("Starting solr fs creation")
  tenant_info = event['body']
  tenant_data = json.loads(tenant_info)
  version = tenant_data['VERSION']
  tenant = tenant_data['TENANT'].lower()

  print("Copying solr config and index.")

  solr_src="/mnt/efs/version/" + version + "/starter/solr.tar.gz"
  dest= "/mnt/efs/" + tenant + "/solr"
  my_tar = tarfile.open(solr_src)
  my_tar.extractall(dest) 
  my_tar.close() 

  return {'statusCode': 200}