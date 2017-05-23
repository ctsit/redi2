# Introduction

This repository contains tools used to create forms in REDCap and test them via Vagrant.

The form [`form_a2_conmeds.csv`](forms/form_a2_conmeds.csv) contains the following fields:


<pre>
    a2_1_med_order_date
    a2_2_med_order_start_date
    a2_3_order_end_date
    a2_4_order_display_name
    a2_5_order_discrete_dose
    a2_6_order_discrete_dose_unit
    a2_8_rxnorm_code
</pre>

Original field names:

<pre>
	MedOrderDate
	MedOrderStartDate
	MedOrderEndDate
	MedOrderDisplayName
	MedOrderDiscreteDose
	MedOrderDiscreteDoseUnit
	RXNORMCode
</pre>

# Initial Setup

The vagrant box is configured to perform the project creation and
data dictionary upload by default if you execute:

<pre>
vagrant plugin install vagrant-triggers
cd vagrant && vagrant up
-- or just use the alias :) --
make vup
</pre>

# Adding Forms

New forms should be stored in the [forms/](forms/) folder.
To make the new form automatically deployed on vagrant up please add it at the
bottom of the [scripts/merge-forms.bash](scripts/merge-forms.bash) script.

<pre>
...
awk 'FNR>2' $FORMS_DIR/form_xyz.csv
</pre>

# Re-uploading a new data dictionary (without re-creating the vagrant)

<pre>
cd scripts/longitudinal
make merge
make redeploy
</pre>


# Downloading the current data dictionary

To download the data dictionary for all forms you can run:
<pre>
cd scripts/longitudinal
make download
</pre>

Note: you can run a cURL command to download the fields of just one form.
Please take a look at [scripts/longitudinal/Makefile](scripts/longitudinal/Makefile)
