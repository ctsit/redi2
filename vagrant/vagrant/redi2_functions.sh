#!/usr/bin/env bash

function install_redi2() {
    pushd ${REDI2_USER_HOME}
    rm -rf ./redi2
    git clone ${REDI2_REPO}
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
        pushd redi2/build_scripts
        bash install_normal.sh
        popd
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
    popd
}

