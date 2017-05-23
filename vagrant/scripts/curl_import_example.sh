#!/bin/sh

### Uses curl. Please make sure you have the module

# Set secret token specific to your REDCap project
# user_a token
# return message shoudl be ERROR: Error: The import could not complete because the records listed here already exist but do not belong to the user's Data Access Group
# records
TOKEN="023F7F557F4C3378DF7AA40B2DE3FF61"

# Set the url to the api (ex. https://YOUR_REDCAP_INSTALLATION/api/)
SERVICE="http://localhost:8080/redcap/api/ "

FILENAME="direct_export_from_rc.csv"

# UPLOAD a flat csv record contain in file file (/path/to/my.csv)
# Note the use of '<' to get curl to read in data from external file

DATA_STRING=$(<${FILENAME})
CURL_STRING=" -v -X POST ${SERVICE}
            -d token=${TOKEN}
            -d action=import
            -d content=record
            -d format=csv
            -d type=flat
            -d data='${DATA_STRING}'"



#echo $DATA_STRING
#echo "curl command string is: "$CURL_STRING

#curl $CURL_STRING

 curl    ${SERVICE} \
        --form token=${TOKEN} \
        --form action=import \
        --form overwriteBehavior=normal \
        --form content=record \
        --form format=csv \
        --form type=flat \
        --form data="${DATA_STRING}"
