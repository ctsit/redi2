#!/usr/bin/env bash

function install_redi2() {
    pushd ${REDI2_USER_HOME}
    rm -rf redi2
    git clone ${REDI2_REPO}
        pushd redi2/build_scripts
        bash install_normal.sh
        popd
    chmod -R 777 .
    popd
}

