#!/usr/bin/env bash

END=300
# COLUMNS="event_name,arm_num,unique_event_name"
echo "["

echo ${COLUMNS}
for i in $(seq 1 $END);
do
    # echo ${i}"_arm_1,1,event_"${i}"_arm_1"
    echo '{
        "event_name": "Event '${i}'",
        "arm_num": "1",
        "day_offset": "'${i}'",
        "offset_min": "0",
        "offset_max": "0",
        "unique_event_name": "'${i}'_arm_1"
    }'

    if [ ${i} -lt ${END} ];
    then
        echo ","
    fi
done

echo "]"
