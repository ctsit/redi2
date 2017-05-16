# REDCap VM for the HCV Target Project

## Overview

This directory contains a Vagrant VM used to testing and development work on the HCV Target 2.0 and 3.0 projects. It is based on the VM defined in CTS-IT's redcap_deployment repo.

## Requirements

This VM requires that Vagrant, VirtualBox, vagrant-hostsupdater plugin, vagrant-env and vagrant-triggers plugin be installed on the host system.

See [Creating the Test VM With Vagrant](docs/creating_the_test_vm_with_vagrant.rst) for details on how to meet those requirements.

## Using the Development Environment

With the above requirements and configuration completed, start the VM with the command

    vagrant up

After about two minutes, the VM should be accessible at [http://redcap.dev/redcap/](http://redcap.dev/redcap/) and at [https://redcap.dev/redcap/](https://redcap.dev/redcap/) (or whatever URL _URL\_OF\_DEPLOYED\_APP_ is set to in _.env_)

