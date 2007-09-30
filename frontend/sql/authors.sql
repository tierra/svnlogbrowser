CREATE TABLE `{PREFIX}_authors` (
  `username` varchar(32) character set latin1 collate latin1_general_cs NOT NULL default '',
  `fullname` varchar(64) NOT NULL default '',
  `commits` int(10) unsigned NOT NULL default '0',
  `active` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
