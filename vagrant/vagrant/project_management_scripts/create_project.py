#!/usr/bin/env python

import json
import sys
import os

import mini_pycap as redcap

if len(sys.argv) > 1 and len(sys.argv) == 5:
    supertoken = sys.argv[1]
    redcapurl = sys.argv[2]
    output_file = sys.argv[3]
    config_file = sys.argv[4]

    if not os.path.isfile(config_file):
        print("Config file: " + config_file + " does not exist")
        sys.exit(0)

else:
    print("No arguments or not enough arguments (" + str(len(sys.argv)) + ") were specified, (args: 1 = SuperUserToken, 2 = REDCap Url, 3 = output file, 4 = project config file)")
    sys.exit(0)

if supertoken:
    print("Using super user token: " + supertoken)
    if redcapurl:

        if config_file:
            project_data = None
            with open(config_file) as project_config_file:
                project_data = project_config_file.read()
        else:
            print("Error reading config file: " + config_file)
            sys.exit(0)

        data = {
            'token': supertoken,
            'content': 'project',
            'format': 'csv',
            'returnFormat': 'json',
            'data' : project_data
        }

        api_token = redcap.send(redcapurl, data)
        if api_token:
            if output_file:
                with open(output_file, 'w') as token_file:
                    token_file.write(api_token)
                print("Writing token: " + str(api_token) + " to file: " + output_file)

                data = {
                    'token': api_token,
                    'content': 'project',
                    'format': 'json',
                    'returnFormat': 'json'
                }

                project_info = redcap.send(redcapurl, data)
                if project_info:
                    save_path = os.path.dirname(output_file) + '/project.info'
                    with open(save_path, 'w+') as project_file:
                        project_file.write(project_info)
                    print("Writing project info to: " + save_path)
            else:
                print("Token: " + str(api_token))
        else:
            print("No data returned from redcap, error encountered")
    else:
        print("No redcap url provided (argument 2)")
else:
    print("No super user token provided (argument 1)")
