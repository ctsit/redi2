#!/bin/bash

# Contributors:
#    Christopher P. Barnes <senrabc@gmail.com>
#    Andrei Sura: github.com/indera
#    Mohan Das Katragadda <mohan.das142@gmail.com>
#    Philip Chase <philipbchase@gmail.com>
#    Ruchi Vivek Desai <ruchivdesai@gmail.com>
#    Taeber Rapczak <taeber@ufl.edu>
#    Josh Hanna <josh@hanna.io>

# Copyright (c) 2015, University of Florida
# All rights reserved.
#
# Distributed under the BSD 3-Clause License
# For full text of the BSD 3-Clause License see http://opensource.org/licenses/BSD-3-Clause

function update_redcap_connection_settings() {
    log "Executing ${FUNCNAME[0]}"

    REQUIRED_PARAMETER_COUNT=6
    if [ $# != $REQUIRED_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} Rewrites the CakePHP database.php for this app."
        echo "${FUNCNAME[0]} requires these $REQUIRED_PARAMETER_COUNT parameters in this order:"
        echo "DEPLOY_DIR           The directory where the app is deployed"
        echo "DATABASE_NAME        The database the app will use"
        echo "DATABASE_USER        Database user who will have access to DATABASE_NAME"
        echo "DATABASE_PASSWORD    Password of DATABASE_USER"
        echo "DATABASE_HOST        The host from which DATABASE_USER is authorized to access DATABASE_NAME"
        echo "SALT                 The string that will be used to salt value before hashing"
        return 1
    else
        DEPLOY_DIR=$1
        DATABASE_NAME=$2
        DATABASE_USER=$3
        DATABASE_PASSWORD=$4
        DATABASE_HOST=$5
        SALT=$6
    fi

    # edit redcap database config file (This needs to be done after extraction of zip files)
    DB_CONFIG_FILE=$DEPLOY_DIR/database.php
    echo "Setting the connection variables in: $DB_CONFIG_FILE"
    echo "\$hostname   = \"$DATABASE_HOST\";" >> $DB_CONFIG_FILE
    echo "\$db         = \"$DATABASE_NAME\";"    >> $DB_CONFIG_FILE
    echo "\$username   = \"$DATABASE_USER\";"    >> $DB_CONFIG_FILE
    echo "\$password   = \"$DATABASE_PASSWORD\";"  >> $DB_CONFIG_FILE
    echo "\$salt   = \"$SALT\";"  >> $DB_CONFIG_FILE
}

# Create tables from sql files distributed with redcap under
#  redcap_vA.B.C/Resources/sql/
#
# @see install.php for details
function create_redcap_tables() {
    log "Executing ${FUNCNAME[0]}"
    REQUIRED_PARAMETER_COUNT=5
    if [ $# != $REQUIRED_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} Creates a MySQL database, a DB user with access to the DB, and sets user's password."
        echo "${FUNCNAME[0]} requires these $REQUIRED_PARAMETER_COUNT parameters in this order:"
        echo "DATABASE_NAME        Name of the database to create"
        echo "DATABASE_USER        Database user who will have access to DATABASE_NAME"
        echo "DATABASE_PASSWORD    Password of DATABASE_USER"
        echo "DEPLOY_DIR           The directory where the app is deployed"
        echo "DB_EPOCH_VERSION     The version of the schema files to be loaded before applying upgrades"
        return 1
    else
        DATABASE_NAME=$1
        DATABASE_USER=$2
        DATABASE_PASSWORD=$3
        DEPLOY_DIR=$4
        DB_EPOCH_VERSION=$5
    fi


    log "Creating REDCap tables..."
    SQL_DIR=$DEPLOY_DIR/redcap_v$DB_EPOCH_VERSION/Resources/sql
    mysql -u$DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < $SQL_DIR/install.sql
    mysql -u$DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < $SQL_DIR/install_data.sql
    mysql -u$DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME -e "UPDATE $DATABASE_NAME.redcap_config SET value = '$DB_EPOCH_VERSION' WHERE field_name = 'redcap_version' "

    files=$(ls -v $SQL_DIR/create_demo_db*.sql)
    for i in $files; do
        log "Executing sql file $i"
        mysql -u$DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < $i
    done
}

# Set REDCap settings for this dev instance of REDCap
function configure_redcap() {
    echo "Setting redcap_base_url to $URL_OF_DEPLOYED_APP..."
    echo "update redcap_config set value='$URL_OF_DEPLOYED_APP' where field_name = 'redcap_base_url';" | mysql
}

# Adjust PHP vars to match REDCap needs
function configure_php_for_redcap() {
    echo "Configuring php to match redcap needs..."
    php5_confd_for_redcap="/etc/php5/apache2/conf.d/90-settings-for-redcap.ini"
    echo "max_input_vars = $max_input_vars" > $php5_confd_for_redcap
    echo "upload_max_filesize = $upload_max_filesize" >> $php5_confd_for_redcap
    echo "post_max_size = $post_max_size" >> $php5_confd_for_redcap
}

# Turn on REDCap Cron
function configure_redcap_cron() {
    echo "Turning on REDCap Cron job..."
    crond_for_redcap=/etc/cron.d/redcap
    echo "# REDCap Cron Job (runs every minute)" > $crond_for_redcap
    echo "* * * * * root /usr/bin/php $PATH_TO_APP_IN_GUEST_FILESYSTEM/cron.php > /dev/null" >> $crond_for_redcap
}

# move the edocs folder
function move_edocs_folder() {
    echo "Moving the edocs folder out of web space..."
    edoc_path="/var/edocs"
    default_edoc_path="$PATH_TO_APP_IN_GUEST_FILESYSTEM/edocs"
    if [ ! -e $edoc_path ]; then
        if [ -e $default_edoc_path ]; then
            rsync -ar $default_edoc_path $edoc_path && rm -rf $default_edoc_path/*
        else
            mkdir $edoc_path
        fi
        # adjust the permissions on the new
        chown -R www-data.www-data $edoc_path
        find $edoc_path -type d | xargs -i chmod 775 {}
        find $edoc_path -type f | xargs -i chmod 664 {}
    fi
    set_redcap_config "Adjusting DB for edocs move..." edoc_path $edoc_path
}

function set_redcap_config() {
    info_text=$1
    field_name=$2
    value=$3
    echo "$1"
    mysql -e "UPDATE $DB.redcap_config SET value = '$value' WHERE field_name = '$field_name';"
}

function set_hook_functions_file() {
    log "Executing ${FUNCNAME[0]}"
    MAX_PARAMETER_COUNT=1
    if [ $# -gt $MAX_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} Sets the redcap_config value hook_functions_file."
        echo "${FUNCNAME[0]} allows one optional parameter:"
        echo "PATH_TO_HOOK_FUNCTIONS_FILE   Full path to the script that will manage hooks"
        return 1
    fi
    if [ $# -gt 1 ]; then
        PATH_TO_HOOK_FUNCTIONS_FILE=$1
    else
        PATH_TO_HOOK_FUNCTIONS_FILE=$PATH_TO_APP_IN_GUEST_FILESYSTEM/hooks/redcap_hooks.php
    fi

    set_redcap_config "Setting hook_functions_file to $PATH_TO_HOOK_FUNCTIONS_FILE ..." "hook_functions_file" "$PATH_TO_HOOK_FUNCTIONS_FILE"
}

function make_twilio_features_visible() {
    set_redcap_config "Making twilio features visible..." "twilio_enabled_by_super_users_only" "0"
}

# Check if the Apache server is actually serving the REDCap files
function check_redcap_status() {
    echo "Checking if redcap application is running..."
    curl -s http://localhost/redcap/ | grep -i 'Welcome\|Critical Error'
    echo "Please try to login to REDCap as user 'admin' and password: 'password'"
}

function upgrade_db () {
    log "Executing ${FUNCNAME[0]}"
    REQUIRED_PARAMETER_COUNT=5

    if [ $# != $REQUIRED_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} Creates a MySQL database, a DB user with access to the DB, and sets user's password."
        echo "${FUNCNAME[0]} requires these $REQUIRED_PARAMETER_COUNT parameters in this order:"
        echo "DATABASE_NAME        Name of the database to create"
        echo "DATABASE_USER        Database user who will have access to DATABASE_NAME"
        echo "DATABASE_PASSWORD    Password of DATABASE_USER"
        echo "DEPLOY_DIR           The directory where the app is deployed"
        echo "DB_EPOCH_VERSION     The version of the schema files to be loaded before applying upgrades"
        return 1
    else
        DATABASE_NAME=$1
        DATABASE_USER=$2
        DATABASE_PASSWORD=$3
        DEPLOY_DIR=$4
        DB_EPOCH_VERSION=$5
    fi

    SCHEMA_FOLDER=$DEPLOY_DIR/schema

    # load every upgrade.sql with a higher version number than the $DB_EPOCH_VERSION
    for dir in `find ${SCHEMA_FOLDER} -maxdepth 1 -type d | sort --version-sort | grep -A1000 ${DB_EPOCH_VERSION} | tail -n +2` ;
    do
        if [ -e ${dir}/upgrade.sql ];
        then
            create_tables ${DATABASE_NAME} ${DATABASE_USER} ${DATABASE_PASSWORD} ${dir}/upgrade.sql
        fi
    done

}

URL_OF_API=${URL_OF_DEPLOYED_APP}"/api/"

# deploy all projects in the .projects/ folder.
# All projects are assumed to be longitudinal.
# TODO: Add support for non-longitudinal projects.
function deploy_projects() {
    for PROJECT in `find ${PATH_TO_REPO_ROOT_IN_GUEST_FILESYSTEM}/projects/ -maxdepth 2 -name "project.config"  | cut -d/ -f1-4`; do
        deploy_project ${PROJECT}
    done
}

# deploy one complete project from project create to form-event relationships
function deploy_project() {
    PROJECT_FOLDER=$1
    API_TOKEN_PATH=${PROJECT_FOLDER}/${ADMIN_USER_TOKEN_FILE_NAME}
    # Run project creation scripts
    create_project ${PROJECT_FOLDER}/project.config ${API_TOKEN_PATH}

    source ${PROJECT_FOLDER}/settings.ini

    API_TOKEN=`cat ${API_TOKEN_PATH}`

    # By default redcap makes a single event. Remove this event before loading the event.X file from ./forms
    remove_default_event ${API_TOKEN}

    # Run event deployment scripts to the project created
    deploy_events ${API_TOKEN} ${PROJECT_FOLDER}/event.${IMPORT_CONTENT_TYPE} ${IMPORT_CONTENT_TYPE}

    # Run form deployment scripts to the project created
    deploy_forms ${API_TOKEN} ${PROJECT_FOLDER}/metadata.${IMPORT_CONTENT_TYPE} ${IMPORT_CONTENT_TYPE}

    # Run event map deployment scripts to the project created
    deploy_event_map ${API_TOKEN} ${PROJECT_FOLDER}/event_map.${IMPORT_CONTENT_TYPE} ${IMPORT_CONTENT_TYPE}

}

# Calling the project management script for creating a new project
function create_project() {
    log "Executing ${FUNCNAME[0]}"
    PROJECT_CONFIG=$1
    API_TOKEN_PATH=$2
    bash ${PROJECT_MANAGEMENT_SCRIPTS}initialize.sh ${PROJECT_CONFIG} ${API_TOKEN_PATH}
}

# Load forms from a metadata file in JSON format
# load_data.py takes arguments for any basic import method in REDCap
# APIToken REDCapUrl FileToBeLoaded Content Action
function deploy_forms() {
    log "Executing ${FUNCNAME[0]}"
    API_TOKEN=$1
    PATH_TO_INPUT_DATA=$2
    CONTENT_TYPE=${3}
    log "Using: {$API_TOKEN}"
    log "Sending to: ${URL_OF_API}"
    log "Getting data from: ${PATH_TO_INPUT_DATA}"
    log "Using Content Type: ${CONTENT_TYPE}"
    python ${PROJECT_MANAGEMENT_SCRIPTS}load_data.py ${API_TOKEN} ${URL_OF_API} ${PATH_TO_INPUT_DATA} metadata import ${CONTENT_TYPE}
}

# Remove the default event in the new project
# delete_event.py takes 3 arguments to remove a single event by its unique id
# APIToken REDCapUrl EventUniqueId
function remove_default_event() {
    log "Executing ${FUNCNAME[0]}"
    API_TOKEN=$1
    python ${PROJECT_MANAGEMENT_SCRIPTS}delete_event.py ${API_TOKEN} ${URL_OF_API} event_1_arm_1
}

# Load events from a file in JSON format
# load_data.py takes arguments for any basic import method in REDCap
# APIToken REDCapUrl FileToBeLoaded Content Action
function deploy_events() {
    log "Executing ${FUNCNAME[0]}"
    API_TOKEN=$1
    PATH_TO_INPUT_DATA=$2
    CONTENT_TYPE=${3}
    python ${PROJECT_MANAGEMENT_SCRIPTS}load_data.py ${API_TOKEN} ${URL_OF_API} ${PATH_TO_INPUT_DATA} event import ${CONTENT_TYPE}
}

# Load event mapping from a file in JSON format
# load_data.py takes arguments for any basic import method in REDCap
# APIToken REDCapUrl FileToBeLoaded Content Action
function deploy_event_map() {
    log "Executing ${FUNCNAME[0]}"
    API_TOKEN=$1
    PATH_TO_INPUT_DATA=$2
    CONTENT_TYPE=${3}
    python ${PROJECT_MANAGEMENT_SCRIPTS}load_data.py ${API_TOKEN} ${URL_OF_API} ${PATH_TO_INPUT_DATA} formEventMapping import ${CONTENT_TYPE}
}
