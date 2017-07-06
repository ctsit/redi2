#!/usr/bin/env bash
source /vagrant/.env

if [ -z $2 ];
then
    echo "Please provide the full path to the config file and API TOKEN storage path"

else
    bash ${PROJECT_MANAGEMENT_SCRIPTS}make_super_user_token.sh
    SUPER_USER_TOKEN=`cat ${SUPER_USER_TOKEN_FILE_LOCATION}`
    PATH_TO_PROJECT_CONFIG=$1
    ADMIN_USER_TOKEN_FILE_LOCATION=$2

    echo "REDCAP API URL: "${URL_OF_API}
    echo "API TOKEN: "${SUPER_USER_TOKEN}

    python ${PROJECT_MANAGEMENT_SCRIPTS}create_project.py ${SUPER_USER_TOKEN} ${URL_OF_API} ${ADMIN_USER_TOKEN_FILE_LOCATION} ${PATH_TO_PROJECT_CONFIG}
fi
