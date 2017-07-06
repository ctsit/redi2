ALTER TABLE redcap_validation_types CHANGE COLUMN `data_type` `data_type` ENUM('date','datetime','datetime_seconds','email','integer','mrn','number','number_comma_decimal','phone','postal_code','ssn','text','time','char','subject_id') NULL;
INSERT INTO `redcap_validation_types` VALUES ('year_only','Four-digit year','^\\d{4}$','^\\d{4}$','number',NULL,1);
INSERT INTO `redcap_validation_types` VALUES ('subject_id','Subject ID','/^\\d{3}-\\d{4}$/','/^\\d{3}-\\d{4}$/','subject_id',NULL,1);
