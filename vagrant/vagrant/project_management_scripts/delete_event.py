#!/usr/bin/env python

import sys
import os

import mini_pycap as redcap

if len(sys.argv) > 1 and len(sys.argv) == 4:
    api_token = sys.argv[1]
    redcapurl = sys.argv[2]
    event_unique_id = sys.argv[3]
else:
    print("No arguments or not enough arguments (" + str(len(sys.argv)) + ") were specified, (args: 1 = AdminUserToken, 2 = REDCap Url, 3 = event_unique_id)")
    sys.exit(0)

if api_token and redcapurl and event_unique_id:

    data = {
        'token': api_token,
        'content': 'event',
        'action': 'delete',
        'format': 'json',
        'events[0]': event_unique_id
    }

    response = redcap.send(redcapurl, data)
    print(response)
