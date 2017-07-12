#!/usr/bin/env python

import sys
import os
import json

if len(sys.argv) > 1 and len(sys.argv) == 3:
    info_file = sys.argv[1]
    value_requested = sys.argv[2]

    if not os.path.isfile(info_file):
        print("Project info file: " + info_file + " does not exist")
        sys.exit(0)

else:
    print("No arguments or not enough arguments (" + str(len(sys.argv)) + ") were specified, (args: 1 = project info file, 2 = key of data requested from project info)")
    sys.exit(0)

if info_file and value_requested:
    with open(info_file, 'r') as info_file:
        data = info_file.read()

    json_data = json.loads(data)

    if value_requested in json_data:
        print(json_data[value_requested])
    else:
        print('No key named ' + value_requested + ' exists in json data')
else:
    if not info_file:
        print('No project info file specified')

    if not value_requested:
        print('No key supplied')
