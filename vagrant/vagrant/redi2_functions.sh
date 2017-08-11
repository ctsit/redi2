#!/usr/bin/env bash

function install_redi2() {
    TAR_DIR=${PATH_TO_REPO_ROOT_IN_GUEST_FILESYSTEM}/redi2_deploy_tar/

    pushd ${REDI2_USER_HOME}
    rm -rf ./redi2
    #git clone ${REDI2_REPO}
    git clone -b feature_fix_vagrant ${REDI2_REPO}
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
        pushd redi2/build_scripts
        # prepare a install2 tar
        bash package.sh
        # then install normally for synthetic runs
        bash install_normal.sh
        popd
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2
    chown -R ${REDI2_USER}:${REDI2_USER} ./redi2.tar.gz
    
    mkdir -p $TAR_DIR
    cp redi2.tar.gz $TAR_DIR
    popd
}
