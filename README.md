# REDI2 v1.4.1
REDCap Electronic Data - I (Ingester/Integrator/Importer) 2

REDI2 is a suite of tools used to take data from a csv and import it into a redcap project using the API.
It is the spiritual successor to REDI.

## Meet the parts of redi2 ##
![data_flow](https://docs.google.com/drawings/d/1bVDUGXkr1n2RrGORnIeeY2nuyVz8BhUtTBgcgNcQeKw/pub?w=843&h=713)

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

## Vagrant testing ##

RED-I2 can be tested on a vagrant. In order to do so the following steps need to be completed in order.

- clone the repo
- go into the redi2/vagrant/vagrant directory
- get a copy of redcap version 6.16.8 zip file and place in the redi2/vagrant/vagrant directory with the name redcap6.16.8.zip
- rename the file `rename_to_dot_env` to `.env` in the same directory `$ cp rename_to_dot_env .env`
- run vagrant up. (Make sure to install the plugins in the plugins.txt file)
- open your browser to http://redi2.dev/redcap/
-- go to Projects > HCV Target > API, take note of your token
- run vagrant ssh from the vagrant/vagrant directory and go to ~/redi2/synthetic_data
- edit the synth.pigeon.conf.yaml and insert the token from before
- run `bash prep_synthetic_data.sh` if it fails then your install went wrong or more likely you dont have the right token

Now if you check your redcap you should see that there are four subjects entered. These are used in the full synthetic
data run that we are about to do. Go back the terminal which is logged into the vagrant and do the following:

- cd ~
- cd redi2/synthetic_data
- In the synth.optimus.conf.yaml, synth.lineman.conf.yaml change the token to the one in the synth.pigeon.conf.yaml
- run `bash synth_run_prepare.sh`
- cd ~/redi2/redi2/NEW_SITE
- run `bash synth.run.sh`
- go look at your redcap and see the data that has been added. Additionally check the NEW_SITE/data folder for the files generated during the run

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

Edit the `NEW_SITE` directory in the `redi2` directory to have the right name.

Build your configs in the `configs` directory

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

#### Vagrant Steps ####

By using vagrant up, one gets a copy of the redi2.tar.gz in `repo/vagrant/redi2_deploy_tar` that is used in the
manual steps. It will be build on the debian jessie vagrant and bundled from there. If you want to skip the
steps that take place on System 1 then this is what you should do.

##### Host machine #####

`cd redi2/vagrant/vagrant`

`vagrant up`

Now go to the "Host machine" section of the "Manual Steps" and continue from there using the tar in
`redi2/vagrant/redi2_deploy_tar`

#### Manual Steps ####

##### System 1 #####

Clone this repo and enter it on System 1.

`cd redi2/build_scripts`

Run `bash package.sh`.

Return to the directory where redi2 was cloned.

##### Host machine #####

Use `scp` to get the `redi2.tar.gz` directory to System 2. It will be in the directory above.

Use `ssh` to gain access to System 2.

##### System 2 #####

Run `tar -xzf redi2.tar.gz` to extract the directory in the location that you want to install redi2

`cd redi2/deploy_scripts`

Run `bash install_two_remote.sh`

Edit the `NEW_SITE` directory in the `redi2` directory to have the right name.

Build your configs in the `configs` directory

Add `source ../correct_path.env` to the line right below the virtualenv source line in the run.sh

Set the `run.sh` script to run when you want it to.


## Putting it all together ##

Once one has built their configs all that remains is to run the tools in the right order. Pretty easy!
Happy redcapping!
