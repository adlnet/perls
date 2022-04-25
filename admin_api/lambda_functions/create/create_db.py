def lambda_handler(event, context):
  import mysql.connector
  import boto3
  import json
  import re
  from mysql.connector.errors import OperationalError, ProgrammingError

  print("Starting db load")
  session = boto3.Session()
  secrets_client = session.client('secretsmanager')
  version = event['VERSION']
  tenant = event['TENANT'].lower()
  brand = event['BRAND']
  db_password_secret = secrets_client.get_secret_value(SecretId=tenant + '_db_password')
  db_password = db_password_secret['SecretString']
  db_name = tenant + "_" + brand
  access_hosts = "10.0.0.0/255.255.0.0"
  rds_secret = secrets_client.get_secret_value(SecretId='rds_admin_login')
  rds_info = json.loads(rds_secret['SecretString'])
  rds_user = rds_info['username']
  rds_password = rds_info['password']
  rds_host = rds_info['host']

  sql_file = "/mnt/efs/version/" + version + "/starter/CMS-Database.sql"

  ###create mysql user and db for tenant
  mydb = mysql.connector.connect(
    host=rds_host,
    user=rds_user,
    password=rds_password
  )
  mycursor = mydb.cursor()
  create_mysql_user = "CREATE USER %s@%s IDENTIFIED BY %s"
  mycursor.execute(create_mysql_user, (tenant, access_hosts, db_password))
  mycursor.execute("CREATE DATABASE {}".format(db_name))
  grant = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES ON {}.* TO %s@%s"
  mycursor.execute(grant.format(db_name), (tenant, access_hosts))
  mycursor.execute("Flush Privileges")
  mycursor.close()
  mydb.close()

  ###import db
  tenantdb = mysql.connector.connect(
    host=rds_host,
    user=rds_user,
    password=rds_password,
    database=db_name
  )
  cursor = tenantdb.cursor()

  statement = ""
  for line in open(sql_file, "r"):
    if re.match(r'--', line):  # ignore sql comment lines
      continue
    if not re.search(r';$', line):  # keep appending lines that don't end in ';'
      statement = statement + line
    else:  # when you get a line ending in ';' then exec statement and reset for next statement
      statement = statement + line
      try:
        cursor.execute(statement)
      except (OperationalError, ProgrammingError) as e:
        print("[WARN] MySQLError during execute statement Args: '{}'".format(str(e.args)))
      statement = ""
  tenantdb.commit()
  tenantdb.close()
  print('Database version ' + version + ' has sucessfully been loaded for ' + tenant)
  return { "statusCode": 200 }

