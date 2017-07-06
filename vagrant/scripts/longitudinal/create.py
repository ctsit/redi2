#!/usr/bin/env python
# -*- coding: utf-8 -*-

###############################################################################
# Copyright 2014-2015 University of Florida. All rights reserved.
# This file is part of UF CTS-IT's toolbox.
# Use of this source code is governed by the license found in the LICENSE file.
###############################################################################

# @author: Andrei Sura

"""
Creates a longitudinal REDCap project using selenium

Requires:
    - selenium
    - chrome (phantomjs not fully tested)


== Install required libraries in OS X
  pip install selenium
  brew install phantomjs
  brew install chromedriver
  brew install caskroom/cask/brew-cask
  brew cask install google-chrome

== Versions
pip freeze | grep selenium
==> selenium==2.46.1

phantomjs --version
==> 2.0.0


@TODO:
    - extract argument parsing into a dedicated function
    - expose a `delete projects by ids` function
"""

import os
import sys
import argparse
import logging

from selenium import webdriver

from project_creator import ProjectCreator, PROJECT_INFO_FILE
from project_creator import ProjectCreatorParams

current = os.path.dirname(os.path.realpath(__file__))
parent = os.path.abspath(os.path.join(current, os.pardir))

# DEFAULT_VERBOSITY = 3
DEFAULT_VERBOSITY = 2
# DEFAULT_METADATA = os.path.join(parent, 'forms/data-dictionary.csv')
# DEFAULT_SETTINGS = os.path.join(parent, 'settings.json')
DEFAULT_METADATA = 'data-dictionary.csv'
DEFAULT_SETTINGS = 'settings.json'
DEFAULT_URL = 'http://127.0.0.1:8081/redcap/index.php'
DEFAULT_VERSION = '6.0.5'

# Clinical Quality Improvement?
DEFAULT_PROJECT_NAME = 'Test Project'

# browser names
DEFAULT_BROWSER_FF = 'firefox'
DEFAULT_BROWSER_CH = 'chrome'
DEFAULT_BROWSER_PH = 'phantomjs'
DEFAULT_BROWSER = DEFAULT_BROWSER_CH

logger = logging.getLogger()
stream_handler = logging.StreamHandler(sys.stdout)
logger.addHandler(stream_handler)


def parse_args():
    """ Parse args for main()"""
    parser = argparse.ArgumentParser()

    # Action types:
    parser.add_argument('-d', '--download', action='store_true',
                        default=False,
                        help='download metadata for the project with properties'
                        ' stored in the PROJECT_INFO_FILE')
    parser.add_argument('-r', '--redeploy', action='store_true',
                        default=False,
                        help='upload metadata for the project with properties'
                        ' stored in the ProjectCreator#PROJECT_INFO_FILE')
    parser.add_argument('-e', '--eventsonly', action='store_true',
                        default=False,
                        help='map instruments to events for the project with'
                        ' properties stored in the PROJECT_INFO_FILE')

    # Project configuration
    parser.add_argument('-t', '--token',
                        default=None,
                        help='the API token for an existing project')
    parser.add_argument('-m', '--metadata',
                        default=DEFAULT_METADATA,
                        help='name of the REDCap metadata file')
    parser.add_argument('-s', '--settings',
                        default=DEFAULT_SETTINGS,
                        help='name of the settings json file')
    parser.add_argument('-v', '--verbosity',
                        default=DEFAULT_VERBOSITY, type=int,
                        help="[ 3|2|1|0 ] Verbosity level "
                        "(debug | info | warning | none)")
    parser.add_argument('-p', '--prompt', action='store_true',
                        default=False,
                        help='ask for url, project name, metadata file name')
    parser.add_argument('-n', '--name',
                        default=DEFAULT_PROJECT_NAME,
                        help='name of the REDCap Project to create')
    parser.add_argument('-u', '--url',
                        default=DEFAULT_URL,
                        help='base address of the REDCap Server')
    parser.add_argument('-w', '--whichversion',
                        default=DEFAULT_VERSION,
                        help='the number representing REDCap version')
    parser.add_argument('-b', '--browser',
                        default=DEFAULT_BROWSER,
                        help='browser name [ firefox | chrome]')

    parser.add_argument('-c', '--credentials',
                        type=argparse.FileType('r'),
                        help='file containing only the REDCap username and '
                        'password to use, separated by a vertical pipe. '
                        'Use "-" to read from stdin. Example: '
                        '"username|password"')

    args = parser.parse_args()
    return args


def main():
    args = parse_args()
    logger.level = logging.ERROR

    if args.verbosity == 1:
        logger.level = logging.WARNING
    elif args.verbosity == 2:
        logger.level = logging.INFO
    elif args.verbosity == 3:
        logger.level = logging.DEBUG

    url = args.url
    project_name = args.name
    metadata_abspath = os.path.abspath(args.metadata)

    if args.prompt:
        # Allow to change important parameters
        metadata_abspath = ProjectCreator.prompt(
            'Metadata file?', metadata_abspath)

        if not args.redeploy:
            # Ask for url/project name only for initial deployment
            url = ProjectCreator.prompt('REDCap Base Web Address (URL)?', url)
            project_name = ProjectCreator.prompt('Project name?', project_name)

    credentials = args.credentials
    if credentials:
        username, password = credentials.read().split('|')
        logger.info("Login with username: {}".format(username))
    else:
        username, password = None, None

    # Parameters for creating the project
    params = ProjectCreatorParams(url, args.whichversion,
                                  project_name, username, password,
                                  metadata_abspath)

    if args.download:
        if args.token is not None:
            # use specified params
            ProjectCreator.download_metadata_csv(url, args.token)
        else:
            # Read params from project.info file
            # ProjectCreator.download_metadata_csv(['a2_form_conmeds'])
            ProjectCreator.download_metadata_csv()
        return

    if DEFAULT_BROWSER_FF == args.browser:
        driver = webdriver.Firefox()
    elif DEFAULT_BROWSER_CH == args.browser:
        driver = webdriver.Chrome()
    else:
        # PhantomJS fails when creating API keys and deleting the default event
        # because it does not support the switch_to_alert()
        # http://stackoverflow.com/questions/15708518/how-can-i-handle-an-alert-with-ghostdriver-via-python
        driver = webdriver.PhantomJS()
        """
        #driver.desired_capabilities['handlesAlerts'] = True
        #cookies_file = 'cookies.txt'
        #service_args = ['--cookies-file={}'.format(cookies_file)]
        #driver = webdriver.PhantomJS(service_args=service_args)
        """

    try:
        pc = ProjectCreator(driver, params)
        pc.load_json_settings(args.settings)

        pc.loginto_project()
        if args.redeploy:
            pc.redeploy_existing_project()
            return
        if args.eventsonly:
            pinfo = ProjectCreator.retrieve_project_info(PROJECT_INFO_FILE)
            pc.projectid = pinfo['projectid']
            pc.url_versioned = pinfo['url_versioned']
            pc.map_instruments_to_events(has_default_event=False)
            pc.print_mapped_events()
            return

        pc.deploy_new_project()

    except Exception as exc:
        logger.error("Unable to complete project setup due: ".format(exc))
        png = 'error-create-long-project.png'
        driver.save_screenshot(png)
        logger.error("Failure step saved as screenshot: {}".format(png))
        raise
    finally:
        # pc.create_test_subject('001')
        # is_test_mode = True
        # when developing we can delete the project we just created
        is_test_mode = False
        if is_test_mode:
            pc.delete_project()

        driver.quit()
    logger.info("Done.")

if __name__ == '__main__':
    main()
