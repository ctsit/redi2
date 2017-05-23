# REDCap Project Management Scripts

## Available Tools
- None of these scripts should be required to be used independent of the bootstrap part of this install, or using reset_db
- All methods are run either when the vagrant is initialized or if you run the primary bootstrap method called 'reset_db'
- All methods are located in vagrant/redcap_deployment_functions.sh
- All scripts reside in ./vagrant/project_management_scripts

* Create a Project (create_project)
    - This method activates initialize.sh, which starts by running make_super_user_token.sh. Next, it runs python create_project.py with the following arguments:
        - SUPER_USER_TOKEN
        - REDCAP_API_URL
        - WHERE_THE_ADMIN_TOKEN_IS_LOCATED
        - PATH_TO_PROJECT_CONFIG
* Load Forms from Metadata (deploy_forms)
    - This method runs a generic script called 'load_data.py'
    - This script takes 5 arguments:
        - ADMIN_USER_API_TOKEN
        - REDCAP_API_URL
        - Metadata in JSON format
        - Content = metadata
        - Action = import

* Remove a Single Event (remove_default_event)
    - This method is strictly for removing a single event. REDCap creates an event by default, and this event needs to be removed in order to load the events from an event file, without needing to handicap your data by removing your first event.
    - TODO: Need to make a way to maybe rename the default event

* Load Events from File (deploy_events)
    - This method runs a generic script called 'load_data.py'
    - This script takes 5 arguments:
        - ADMIN_USER_API_TOKEN
        - REDCAP_API_URL
        - Events in JSON format
        - Content = event
        - Action = import

* Load Event Mapping from File (deploy_event_map)
    - This method runs a generic script called 'load_data.py'
    - This script takes 5 arguments:
        - ADMIN_USER_API_TOKEN
        - REDCAP_API_URL
        - Event Mapping in JSON format
        - Content = formEventMapping
        - Action = import

## Scripts Used
* create_project.py - Used by initialize.sh to create a new project provided a super user token, a url, a file to place the admin token, and a configuration file for the project
* delete_event.py - Used to delete a single event, provided an admin token, a url, and a unique event id
* initialize.sh - Used to build a project, provided a project config file, then it runs make_super_user_token.sh to manually create a super token
* load_data.py - This is a utility method that does a small number of import methods using the REDCap API by way of the mini_pycap.py script
* make_event_map.sh - A basic script to make a new event map file
* make_events.sh - A basic script to make a new event file
* make_super_user_token.sh - A script that manually creates a super user token by manual SQL entries into REDCap's database
* mini_pycap.py - A small pycurl based script for sending data to the REDCap API
* read_forms.py - A small utility script to load and verify a forms metadata file
* project.config - The base project configuration file for this project

## Data

The methods in vagrant/redcap_deployment_functions.sh use REDCap project definitions stored at ./projects/<project_name>  ./projects/<project_name> has a strict naming and file format convention:

./projects/<project_name>/project.config - a csv file with the definition for the empty project
./projects/<project_name>/metadata.json - a JSON file describing every form and every field in the project.  This should be an export from REDCap in the metadata JSON format.
./projects/<project_name>/event.json - a JSON file describing every longitudinal event in the project.  This should be an export from REDCap in the event JSON format.
./projects/<project_name>/event_map.json - a JSON file describing the mapping of forms to events in the project.  This should be an export from REDCap in the event_mapping JSON format.
