#!/bin/bash

# Contributors:
#    Philip Chase <philipbchase@gmail.com>

# Copyright (c) 2016, University of Florida
# All rights reserved.
#
# Distributed under the BSD 3-Clause License
# For full text of the BSD 3-Clause License see http://opensource.org/licenses/BSD-3-Clause

function hcvtarget_overlay_redcap_code() {
    log "Executing ${FUNCNAME[0]}"
    REQUIRED_PARAMETER_COUNT=2
    if [ $# != $REQUIRED_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} copies custom HCVTARGET code into the deployed redcap web app"
        echo "${FUNCNAME[0]} requires these $REQUIRED_PARAMETER_COUNT parameters in this order:"
        echo "PATH_TO_REPO_ROOT_IN_GUEST_FILESYSTEM    Root of the project's git repository"
        echo "PATH_TO_APP_IN_GUEST_FILESYSTEM          Root of the deployed application"
        return 1
    else
        PATH_TO_REPO_ROOT_IN_GUEST_FILESYSTEM=$1
        PATH_TO_APP_IN_GUEST_FILESYSTEM=$2
    fi

    cp -r $PATH_TO_REPO_ROOT_IN_GUEST_FILESYSTEM/app/* $PATH_TO_APP_IN_GUEST_FILESYSTEM

}

function hcvtarget_rewrite_hook_pid() {

    log "Executing ${FUNCNAME[0]}"
    REQUIRED_PARAMETER_COUNT=4
    if [ $# != $REQUIRED_PARAMETER_COUNT ]; then
        echo "${FUNCNAME[0]} rewrites the project ID directories that activation HCVTARGET hooks"
        echo "${FUNCNAME[0]} requires these $REQUIRED_PARAMETER_COUNT parameters in this order:"
        echo "PROJECT_NAME   The name of the project whose project ID needs replacement"
        echo "OLD_PID        The existing project ID that needs to be changed"
        echo "NEW_PID        The replacement project ID for project PROJECT_NAME"
        echo "PATH_TO_APP_IN_GUEST_FILESYSTEM   Root of the deployed application"
        return 1
    else
        PROJECT_NAME="$1"
        OLD_PID=$2
        NEW_PID=$3
        PATH_TO_APP_IN_GUEST_FILESYSTEM=$4
    fi

    log "Changing PID for REDCap project $PROJECT_NAME from $OLD_PID to $NEW_PID"

    HOOKS_DIR=$PATH_TO_APP_IN_GUEST_FILESYSTEM/hooks
    if [ ! -d $HOOKS_DIR ]; then
        log "ERROR: $HOOKS_DIR directory does not exist."
        return 1
    fi

    SOURCE=$HOOKS_DIR/pid${OLD_PID}
    TARGET=$HOOKS_DIR/pid${NEW_PID}
    if [ -d $SOURCE ]; then
        mv $SOURCE $TARGET
    else
        log "WARN: SOURCE directory, $SOURCE, does not exist"
        if [ -d $TARGET ]; then
            log "WARN: TARGET directory, $TARGET, *does* exist.  Was $SOURCE already renamed to $TARGET?"
        fi
        return 1
    fi

    if [ ! -d $TARGET ]; then
        log "ERROR: rename of $SOURCE to $TARGET failed"
        return 1
    fi

}
