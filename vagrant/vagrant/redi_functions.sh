#!/usr/bin/env bash

function install_redi() {
    apt-get update
    apt-get install -y libxml2-dev libxslt-dev python-dev libssl-dev libffi-dev

    pip install -r ${REDI_REQUIREMENTS_FILE}

    pushd ${REDI_USER_HOME}
    redi_venv_directory=${REDI_VERSION//[.]/_}
    redi_venv_directory="redi_${redi_venv_directory}"
    virtualenv ${redi_venv_directory}
    source ${redi_venv_directory}/bin/activate
    pip install -Iv redi==${REDI_VERSION}
    popd > /dev/null

    deactivate

    chown -R ${REDI_USER}.${REDI_USER_GROUP} ${REDI_USER_HOME}/${redi_venv_directory}
}

function install_gsm() {
    apt-get update
    apt-get install -y python-setuptools python-dev libxml2 libxslt1-dev libffi-dev

    pushd ${REDI_USER_HOME}
    rsm_file_name=rsm-${RSM_VERSION}-${RSM_PYTHON_VERSION}.egg
    wget ${RSM_INSTALL_URL}/${RSM_VERSION}/${rsm_file_name}
    gsm_venv_directory=${RSM_VERSION//[.]/_}
    gsm_venv_directory="gsm_${gsm_venv_directory}"
    virtualenv ${gsm_venv_directory}
    source ${gsm_venv_directory}/bin/activate

    easy_install ${rsm_file_name} -d ${gsm_venv_directory}/bin

    popd > /dev/null

    deactivate

    chown -R ${REDI_USER}.${REDI_USER_GROUP} ${REDI_USER_HOME}/${gsm_venv_directory}
}
