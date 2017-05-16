#!/usr/bin/env python

import json
import sys

all_file = sys.argv[1]

if all_file:
    with open(all_file, 'r') as all_file:
        data = all_file.read()

    json_data = json.loads(data)
    print(len(json_data))

else:
    print('No all forms file specified')
