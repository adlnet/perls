#Tenant Deployment Processes

##New Tenant Deploy
1.  SSH in the utility.perls.usalearning.net as the centos.  Only administrators with explicitly allowed IP addresses and the centos private key will be able to login.
2.  Change directory to /home/centos/perls.  "cd /home/centos/perls"
3. Create the tenant.env file based on the scripts/perls_tenants/sample_tenant.env file.
4.  Run the command "bash make_tenant.sh".

##Tenant Update
1. SSH in the utility.perls.usalearning.net as the centos.  Only administrators with explicitly allowed IP addresses and the centos private key will be able to login.
2. Change directory in the the tenant's home directory.  "cd /var/www/<tenant>"
3. Update the VERSION variable to the version of the CMS you are upgrading to.
4. Run the command "bash scripts/perls_tenants/bash update_tenant.sh"

##Tenant Deletion
1.  SSH in the utility.perls.usalearning.net as the centos.  Only administrators with explicitly allowed IP addresses and the centos private key will be able to login.
2. Change directory to /home/centos/perls.  "cd /home/centos/perls"
3. Run the command "bash delete_tenant.sh <tenant>"
