CREATE TABLE IF NOT EXISTS `changelogs` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  `table_prefix` varchar(32) NOT NULL,
  `latest_revision` int(10) unsigned NOT NULL default '0',
  `svn_url` varchar(255) NOT NULL,
  `svn_root` varchar(255) default NULL,
  `summary_limit` tinyint(3) unsigned NOT NULL default '10',
  `trunk` varchar(255) default NULL,
  `tags` varchar(255) default NULL,
  `branches` varchar(255) default NULL,
  `diff_url` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
