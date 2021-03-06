SET FOREIGN_KEY_CHECKS=0;
LOCK TABLES `_of_sso_bale_attr` WRITE, `_of_sso_bale_pack` WRITE, `_of_sso_login_log` WRITE, `_of_sso_pack_func` WRITE, `_of_sso_realm_attr` WRITE, `_of_sso_realm_func` WRITE, `_of_sso_realm_pack` WRITE, `_of_sso_user_attr` WRITE, `_of_sso_user_bale` WRITE, `_of_sso_user_pack` WRITE;
REPLACE INTO `_of_sso_pack_func` (`id`,`realmId`,`packId`,`funcId`) VALUES ('1','1','1','1'),
('2','1','1','2'),
('3','1','1','3'),
('4','1','1','4'),
('5','1','1','5'),
('6','1','1','6'),
('7','1','1','7'),
('8','1','1','8'),
('9','1','1','9'),
('10','1','1','10'),
('11','1','1','11'),
('12','1','1','12'),
('13','1','1','13'),
('14','1','1','14'),
('15','1','1','15'),
('16','1','1','16'),
('17','1','1','17'),
('18','1','1','18'),
('19','1','1','19'),
('20','1','1','20'),
('21','1','1','21'),
('22','1','1','22'),
('23','1','1','23');
REPLACE INTO `_of_sso_realm_attr` (`id`,`name`,`lable`,`pwd`,`state`,`notes`,`trust`) VALUES ('1','sso','单点登陆系统','123456','1','单点登陆系统','1');
REPLACE INTO `_of_sso_realm_func` (`id`,`realmId`,`name`,`state`,`lable`,`data`) VALUES ('1','1','userAdd','1','用户添加',''),
('2','1','userMod','1','用户修改',''),
('3','1','userDel','1','用户删除',''),
('4','1','userIce','1','用户停用',''),
('5','1','userPack','1','用户角色',''),
('6','1','realmAdd','1','系统添加',''),
('7','1','realmMod','1','系统修改',''),
('8','1','realmDel','1','系统删除',''),
('9','1','realmIce','1','系统停用',''),
('10','1','packAdd','1','角色添加',''),
('11','1','packMod','1','角色修改',''),
('12','1','packDel','1','角色删除',''),
('13','1','packIce','1','角色停用',''),
('14','1','packFunc','1','角色权限',''),
('15','1','funcAdd','1','权限添加',''),
('16','1','funcMod','1','权限修改',''),
('17','1','funcDel','1','权限删除',''),
('18','1','funcIce','1','权限停用',''),
('19','1','baleAdd','1','集合添加',''),
('20','1','baleMod','1','集合修改',''),
('21','1','baleDel','1','集合删除',''),
('22','1','baleIce','1','集合停用',''),
('23','1','balePack','1','集合角色','');
REPLACE INTO `_of_sso_realm_pack` (`id`,`realmId`,`name`,`state`,`lable`,`data`) VALUES ('1','1','admin','1','管理员','');
REPLACE INTO `_of_sso_user_attr` (`id`,`name`,`pwd`,`nick`,`notes`,`state`,`find`,`time`) VALUES ('1','admin','e10adc3949ba59abbe56e057f20f883e','系统管理员','','1','\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0','2037-12-30 00:00:00');
REPLACE INTO `_of_sso_user_pack` (`id`,`realmId`,`packId`,`userId`) VALUES ('1','1','1','1');
UNLOCK TABLES;
SET FOREIGN_KEY_CHECKS=1;
