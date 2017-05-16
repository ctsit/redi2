#!/usr/bin/env python

import sys
import os

import mini_pycap as redcap

if len(sys.argv) > 1 and len(sys.argv) == 7:
    api_token = sys.argv[1]
    redcapurl = sys.argv[2]
    data_file = sys.argv[3]
    content = sys.argv[4]
    action = sys.argv[5]
    content_type = sys.argv[6]

    if not os.path.isfile(data_file):
        print("Data file: " + data_file + " does not exist")
        sys.exit(0)

else:
    print("No arguments or not enough arguments (" + str(len(sys.argv)) + ") were specified, (args: 1 = AdminUserToken, 2 = REDCap Url, 3 = data file, 4 = content, 5 = action, 6 = content type)")
    sys.exit(0)

if api_token and redcapurl and data_file and content:

    with open(data_file, 'r') as data_file:
        data = data_file.read()

    if content == 'metadata':
        print('Loading items from metadata...')
    else:
        print('Loading ' + content + ' items from file...')

    if action == 'None' or action == 'none':
        action = None

    data = {
        'token': api_token,
        'content': str(content),
        'action': str(action),
        'format': content_type,
        'data': data
    }

    response = redcap.send(redcapurl, data)

    if content == 'metadata':
        # last_bracket = response.rfind('}')
        # form_count = response[last_bracket+1:]
        print('Loaded all items from metadata')
    else:
        print('Loaded ' + response + ' ' + content + ' items')
