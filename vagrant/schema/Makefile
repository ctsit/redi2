include .env

FLAGS_SCHEMA_ONLY=--no-data --skip-lock-tables --triggers --routines --events --skip-add-locks --skip-comments
FLAGS_DATA_ONLY  =--no-create-info --skip-lock-tables --skip-add-locks --skip-comments --skip-extended-insert --no-create-db

help:
	@echo "Run one of these targets"
	@echo "  dump_schema            Dump only the database schemaq"
	@echo "  dump_minimal_data      Dump only the data need by every instance of the app"
	@echo "  dump_testing_data      Dump data need to instantiate the dev instance"
	@echo "  dump                   All of the above"

dump_schema:
	mysqldump -u $(DB_ROOT_USER) -p$(DB_ROOT_PASS) -h $(DB_HOST) $(FLAGS_SCHEMA_ONLY) $(DB) | sed -e 's/AUTO_INCREMENT=[[:digit:]]* //' | sed "/50013 DEFINER=/d" > $(DUMP_SCHEMA_DEV)
	grep 'CREATE TABLE' $(DUMP_SCHEMA_DEV) | wc -l

dump_minimal_data:
	mysqldump -u $(DB_ROOT_USER) -p$(DB_ROOT_PASS) -h $(DB_HOST) $(FLAGS_DATA_ONLY) \
	--ignore-table=$(DB).redcap_crons_history \
	--ignore-table=$(DB).redcap_sessions \
	--ignore-table=$(DB).redcap_auth_history \
	--ignore-table=$(DB).redcap_ip_cache \
	--ignore-table=$(DB).redcap_log_event \
	--ignore-table=$(DB).redcap_log_view \
	--ignore-table=$(DB).redcap_page_hits \
	--ignore-table=$(DB).redcap_log_view_requests \
	$(DB) > data_minimal.sql
	du -h data_minimal.sql

dump_testing_data:
	mysqldump -u $(DB_ROOT_USER) -p$(DB_ROOT_PASS) -h $(DB_HOST) $(FLAGS_DATA_ONLY) \
	$(DB) \
	redcap_user_information > data_testing.sql

dump: dump_schema dump_minimal_data dump_testing_data
