#!/usr/bin/env python

import sys
import os
import json
import csv

import mini_pycap as redcap

if len(sys.argv) > 1 and len(sys.argv) == 7:
    api_token = sys.argv[1]
    redcapurl = sys.argv[2]
    content = sys.argv[3]
    data_file = sys.argv[4]
    output_format = sys.argv[5]
    output_file = sys.argv[6]

    if content == 'metadata':
        if not os.path.isfile(data_file):
            print('Data file: ' + data_file + ' does not exist')
            sys.exit(0)
else:
    print("No arguments or not enough arguments (" + str(len(sys.argv)) + ") were specified, (args: 1 = AdminUserToken, 2 = REDCap Url, 3 = content, 4 = data file (source of input data), 5 = output format, 6 = output file path)")
    sys.exit(0)

if api_token and redcapurl and content and data_file and output_format and output_file:

    query_data = None
    with open(data_file, 'r') as data_file:
        query_data = data_file.read()
    if content == 'metadata':
        data_type = 'forms'
    elif content == 'event':
        data_type = 'arms'
    elif content == 'formEventMapping':
        data_type = 'arms'

    data = {
        'token': api_token,
        'content': str(content),
        'format': output_format,
        'returnFormat': 'json'
    }

    if query_data:
        data[data_type] = query_data

    response = redcap.send(redcapurl, data)

    with open(output_file, 'w') as output_file:
        if output_format == 'json':
            response = json.loads(response)
            response = json.dumps(response, indent=4)

        output_file.write(response)
