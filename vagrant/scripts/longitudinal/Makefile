# Helper script for executing common tasks for the longitudinal project
#
# @author: Andrei Sura
#
# TODO: add support for selecting a browser

VERBOSITY := 2
BROWSER := chrome # firefox, phantomjs
#BROWSER := firefox
SAND_URL 	:= 'http://127.0.0.1:8080/redcap/index.php'
SAND_URL_AUTO   := 'http://127.0.0.1/redcap/index.php'

REDCAP_URL 	:= $(SAND_URL)
REDCAP_URL_AUTO := $(SAND_URL_AUTO)
META_FILE          := 'data-dictionary.csv'
JSON_SETTINGS      := 'settings.json'
CRED_FILE          := ~/longitudinal_credentials

RUNNER := python create.py -m $(META_FILE) -s $(JSON_SETTINGS) -v $(VERBOSITY) --url $(REDCAP_URL) -b $(BROWSER)
RUNNER_AUTO := python create.py -m $(META_FILE) -s $(JSON_SETTINGS) -v $(VERBOSITY) --url $(REDCAP_URL_AUTO) -b $(BROWSER)
API_TOKEN := $(shell grep api_token project.info | cut -d= -f2)

help:
	@echo "Available tasks:"
	@echo "\t download               : Download the data dictionary from $(REDCAP_URL)"
	@echo "\t deploy                 : Create a project with multiple events on $(REDCAP_URL) using $(JSON_SETTINGS)"
	@echo "\t deploy_auto            : Create a project with multiple events on $(REDCAP_URL_AUTO) using $(JSON_SETTINGS)"
	@echo "\t deploy_with_login      : Create a project with multiple events on $(REDCAP_URL) using $(JSON_SETTINGS)"
	@echo "\t merge                  : Merge forms/*.csv into one metadata file - $(META_FILE)"
	@echo "\t redeploy               : Re-deploy the project metadata file - $(META_FILE)"

check_meta:
	@test -f $(META_FILE) \
        || ( \
            echo 'Please create the $(META_FILE) file by merging csv files in the "forms" folder ' \
            && echo 'You can merge the files by executing:' \
            && echo "\t bash scripts/merge-forms.bash > scripts/longitudinal/data-dictionary.csv" \
            && exit 1)


deploy: check_meta
	@# Deploy with prompting for confirmation
	$(RUNNER) -p

deploy_auto: check_meta
	@# Headless deployment without prompting (used by "vagrant up")
	DISPLAY=:99 PATH="/usr/lib/chromium-browser:$(PATH)" $(RUNNER_AUTO)


deploy_with_login: check_meta
	$(RUNNER) -c $(CRED_FILE)

download:
	@$(RUNNER) -d -t $(API_TOKEN)

merge:
	bash ../merge-forms.bash > data-dictionary.csv

redeploy: check_meta
	$(RUNNER) -r

events:
	$(RUNNER) -e
