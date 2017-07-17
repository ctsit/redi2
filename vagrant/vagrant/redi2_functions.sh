#!/usr/bin/env bash

function install_redi2() {
    pushd ${REDI2_USER_HOME}
    rm -rf ./redi2
    git clone ${REDI2_REPO}
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
        pushd redi2/build_scripts
        bash install_normal.sh
        # prepare a install2 tar
        bash package.sh
        popd
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2.tar.gz
    cp redi2.tar.gz ${PATH_TO_REPO_ROOT_IN_GUEST_FILE_SYSTEM}/redi2_deploy_tar/
    popd
}

