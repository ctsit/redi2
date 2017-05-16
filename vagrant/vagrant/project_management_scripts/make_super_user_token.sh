#!/usr/bin/env bash
source /vagrant/.env

USER_ID=${ADMIN_USER_ID}
USER_NAME=${ADMIN_USER_NAME}
HOST_IP=${CLIENT_IP_ADDRESS}
API_TOKEN=`cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 64 | head -n 1`
API_TOKEN_FILE=${SUPER_USER_TOKEN_FILE_LOCATION}
TODAY=`date +"%Y%m%d%H%M%S"`

LOG_QUERY="INSERT INTO redcap_log_event VALUES (NULL,0,${TODAY},'${USER_NAME}','${HOST_IP}','ControlCenter/user_api_ajax.php','MANAGE','redcap_user_information',NULL,'${USER_NAME}',NULL,'user="${USER_NAME}"','Manually create Super API Token for user',0,NULL);"
USER_UPDATE_QUERY="UPDATE redcap_user_information SET api_token='${API_TOKEN}' WHERE ui_id=${USER_ID} AND username='${USER_NAME}';"

echo "Logging creation of super user token for ${USER_NAME}"
echo "${LOG_QUERY}" | mysql
echo "Creating super user token: ${API_TOKEN}"
echo "${USER_UPDATE_QUERY}" | mysql
echo "Saving token to file: ${API_TOKEN_FILE}"
echo ${API_TOKEN} > ${API_TOKEN_FILE}
