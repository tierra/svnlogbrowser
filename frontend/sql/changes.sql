CREATE TABLE `{PREFIX}_changes` (
  `revision` int(10) unsigned NOT NULL default '0',
  `action` char(1) character set latin1 collate latin1_general_cs NOT NULL default '',
  `path` varchar(255) character set latin1 collate latin1_general_cs NOT NULL default '',
  `copy_path` varchar(255) character set latin1 collate latin1_general_cs default NULL,
  `copy_revision` int(10) unsigned default NULL,
  KEY `revision` (`revision`),
  FULLTEXT KEY `path` (`path`,`copy_path`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
