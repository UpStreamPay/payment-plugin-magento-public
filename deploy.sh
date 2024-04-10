#This script get the magento pod and excute the commands to install the plugin

#!/bin/bash
# Write help message
if [ "$1" == "-h" ] || [ "$1" == "--help" ]
then
      echo "Usage: ./script.sh, this need to be run from the root of the project"
      exit 0
fi

# Get the plugin name
PLUGIN_NAME=payment-plugin-magento.tar

# Create a payment-plugin-magento.tar file
tar -cvf $PLUGIN_NAME *

gcloud container clusters get-credentials usp-dev --region=europe-west1-b --project=usp-dev-qalf
gcloud compute ssh usp-dev-bastion --tunnel-through-iap --project=usp-dev-qalf --zone=europe-west1-b --ssh-flag="-4 -L8888:localhost:8888 -N -q -f"
export HTTPS_PROXY=localhost:8888


# Set the namespace
kubectl config set-context --current --namespace=magento

# Get the magento pod name
POD_NAME=$(kubectl get pods | grep 'usp-magento-' | grep -v 'elasticsearch' | grep -v 'mariadb' | awk '{print $1}')

#Print the pod name
echo "Magento pod name: $POD_NAME"

echo "Deleting the plugin from the magento pod"
kubectl exec $POD_NAME -- rm -rf /bitnami/magento/app/code/UpStreamPay/$PLUGIN_NAME

# Copy the plugin to the magento pod
echo "Copying the plugin to the magento pod"
kubectl cp $PLUGIN_NAME $POD_NAME:/bitnami/magento/app/code/UpStreamPay/

# Extract the plugin
echo "Extracting the plugin"
kubectl exec $POD_NAME -- ls /bitnami/magento/app/code/UpStreamPay/.

kubectl exec $POD_NAME -- tar -xvf /bitnami/magento/app/code/UpStreamPay/$PLUGIN_NAME -C /bitnami/magento/app/code/UpStreamPay/

# Run the magento upgrade
echo "Running the magento upgrade"
#kubectl exec $POD_NAME -- /bitnami/magento/bin/magento setup:upgrade