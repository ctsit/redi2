#!/bin/bash

# Example usage:
#   ./merge-forms.bash > data-dictionary.csv

DIR="$( cd "$( echo "${BASH_SOURCE[0]%/*}" )"; pwd )"
FORMS_DIR="$DIR/../forms/"

# Note: all forms should have the subject identifier field from the first form
# in order to be allow dividing the project metadata (data-dictionary
# into one file per form
cat         $FORMS_DIR/form_a1_demographics.csv
awk 'FNR>2' $FORMS_DIR/forms_hcvtarget_03312016.csv
awk 'FNR>2' $FORMS_DIR/form_a2_conmeds.csv
