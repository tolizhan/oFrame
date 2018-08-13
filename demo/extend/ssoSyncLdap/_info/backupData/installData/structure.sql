INSERT INTO `__TABLES` (`TABLE_NAME`,`ENGINE`,`ROW_FORMAT`,`TABLE_COLLATION`,`AUTO_INCREMENT`,`CREATE_OPTIONS`,`TABLE_COMMENT`) VALUES (/*`N:T'*/'e_ssosyncldap_smstip'/*`N:T'*/,'InnoDB','Compact','utf8_general_ci','0','','单点登录员工短信提醒通讯簿'),
(/*`N:T'*/'e_ssosyncldap_unrecorded'/*`N:T'*/,'MyISAM','Fixed','utf8_general_ci','0','row_format=FIXED','单点登录无帐号的用户登记表');
INSERT INTO `__COLUMNS` (`TABLE_NAME`,`COLUMN_NAME`,`ORDINAL_POSITION`,`COLUMN_DEFAULT`,`IS_NULLABLE`,`CHARACTER_SET_NAME`,`COLLATION_NAME`,`COLUMN_TYPE`,`EXTRA`,`COLUMN_COMMENT`) VALUES (/*`N:T'*/'e_ssosyncldap_smstip'/*`N:T'*/,'name','1',NULL,'NO','utf8','utf8_general_ci','char(200)','','员工帐号'),
(/*`N:T'*/'e_ssosyncldap_smstip'/*`N:T'*/,'mobile','2',NULL,'NO','utf8','utf8_general_ci','char(20)','','手机号码'),
(/*`N:T'*/'e_ssosyncldap_smstip'/*`N:T'*/,'pwdTip','3',NULL,'NO',NULL,NULL,'datetime','','密码过期最近提示时间'),
(/*`N:T'*/'e_ssosyncldap_unrecorded'/*`N:T'*/,'name','1',NULL,'NO','utf8','utf8_general_ci','char(200)','','无帐号的用户名'),
(/*`N:T'*/'e_ssosyncldap_unrecorded'/*`N:T'*/,'pwd','2',NULL,'NO','utf8','utf8_general_ci','char(200)','','登录时使用的密码'),
(/*`N:T'*/'e_ssosyncldap_unrecorded'/*`N:T'*/,'time','3',NULL,'NO',NULL,NULL,'datetime','','最后登录时间');
INSERT INTO `__STATISTICS` (`TABLE_NAME`,`NON_UNIQUE`,`INDEX_NAME`,`COLUMNS_NAME`,`INDEX_TYPE`) VALUES (/*`N:T'*/'e_ssosyncldap_smstip'/*`N:T'*/,'0','PRIMARY','`name`','BTREE'),
(/*`N:T'*/'e_ssosyncldap_unrecorded'/*`N:T'*/,'0','PRIMARY','`name`','BTREE');
