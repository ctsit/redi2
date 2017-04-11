# redi2
REDCap Electronic Data - I (Ingester/Integrator/Importer) 2

## Deploying RED-I 2 ##

There are two strategies for deployment depending on the what the deploy target looks like.
If you are able to talk to both github and PyPi on the hosting system then follow the first install.
If your system is more locked down and you cannot talk to either github or PyPi then follow install 2.

### Install 1 ###

What follows are the requirements and steps to install redi2 with access to both github and PyPi

#### Requirements ####

  * git
  * python3
  * virtualenv
  * access to github
  * access to PyPi
  
#### Steps ####

Clone this repo and enter it.

Run `bash install_normal.sh`.

Edit the `NEW_SITE` directory in the `redi2` directory to have the right name and configs.

Set the `run.sh` script to run when you want it to.

### Install 2 ###

This install is for those who have a target system that is not able to call out to github or PyPi for
whatever reason. The general strategy is to do as much work in an environment like the one to which you 
will be deploying and bringing all that stuff over.

#### Requirements ####

##### System 1: talks to outside world system #####
  * git
  * python3
  * virtualenv
  * tar
  * access to github
  * access to PyPi

##### System 2: target system #####
  * ssh access
  * python3
  * virtualenv

#### Steps ####

Clone this repo and enter it on System 1.

Run `bash package.sh`.

Use `scp` to get the `redi2` directory to the target system. It will be in the directory above.

Use `ssh` to gain access to the target system. Extract the directory to the location you want to install redi2.

Run `bash install_two_remote.sh`

Edit the `NEW_SITE` directory in the `redi2` directory to have the right name and configs.

Run `source correct_path.env` to correct the python path. This needs to be done everytime you run the tools in the 
venv or not.

Set the `run.sh` script to run when you want it to.


## Meet the parts of redi2 ##

redi2 is really just a collection of tools and a few scripts which help you get set up. 

### Claw ###

Claw grabs files over sftp

### Auditor ###

Auditor redacts, maps, cleans and basically does whatever to the output of claw which should be a CSV.

### Optimus ###

Optimus takes your particular CSV and your project specific rules and transforms the data into a form which should
be easy to make right with redcap. Here is when branching logic should be applied and any derived data which 
can be inferred, but is not explicitly present in your raw CSV should be made.


### Lineman ###
 
Lineman is the first tool to talk to redcap. It grabs information related to the data you are passing in and 
validates that it can be imported. It will change the fields that it must in order to make sure that it will go in.
For example, it can take dates and map them to the correct event in the redcap.

### Pigeon ###

Pigeon carries the data to redcap. This is the last step and the only one where data will change on the
redcap server. This tool takes a greedy approach, so it will attempt to push in all that it can at once.
It can recover from errors and will keep trying to push the data in till it gives up and pushes in one record
at a time.

## Putting it all together ##

Once one has built their configs all that remains is to run the tools in the right order. Pretty easy! 
Happy redcapping!
