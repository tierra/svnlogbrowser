CREATE TABLE `{PREFIX}_commits` (
  `revision` int(10) unsigned NOT NULL default '0',
  `author` varchar(32) character set latin1 collate latin1_general_cs NOT NULL default '',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` text NOT NULL,
  UNIQUE KEY `revision` (`revision`),
  FULLTEXT KEY `message` (`message`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
