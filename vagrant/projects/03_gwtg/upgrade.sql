-- Disable auto-incrementing of the redcap id's
UPDATE `redcap_projects` SET `redcap_projects`.`auto_inc_set`=0 WHERE `redcap_projects`.`project_id`=(SELECT rp_a.`project_id` FROM (SELECT * FROM `redcap_projects`) AS rp_a WHERE rp_a.`project_name`='gwtg_test_project');
