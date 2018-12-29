
CREATE TABLE IF NOT EXISTS `wallet_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '组名',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='sdt用户所属组';



INSERT INTO `wallet_group` (`id`, `name`) VALUES
	(1, 'all'),
	(2, '用户'),
	(3, '官方钱包'),
	(4, '其他钱包'),
	(5, '运营团队');


CREATE TABLE IF NOT EXISTS `wallet_group_purse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purse_id` int(11) NOT NULL DEFAULT '0' COMMENT '钱包id',
  `group_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户组',
  PRIMARY KEY (`id`),
  KEY `user_id` (`purse_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包-组-关系对应';


INSERT INTO wallet_group_purse(purse_id) select fd_id from wallet_purse;
UPDATE wallet_group_purse  SET group_id = 2; 



