#!/usr/bin/env bash

END=300
echo "["
echo '{"arm_num":"1","unique_event_name":"event_1_arm_1","form": "demographics"},'
for i in $(seq 1 $END);
do
    echo '{"arm_num":"1","unique_event_name":"event_'${i}'_arm_1","form": "conmeds"},'
    echo '{"arm_num":"1","unique_event_name":"event_'${i}'_arm_1","form": "cbc_imported"},'
    echo '{"arm_num":"1","unique_event_name":"event_'${i}'_arm_1","form": "chemistry_imported"},'
    echo '{"arm_num":"1","unique_event_name":"event_'${i}'_arm_1","form": "inr_imported"},'
    echo '{"arm_num":"1","unique_event_name":"event_'${i}'_arm_1","form": "hcv_rna_imported"}'

    if [ ${i} -lt ${END} ];
    then
        echo ","
    fi
done
echo "]"
