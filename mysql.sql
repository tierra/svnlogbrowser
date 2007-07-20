-- phpMyAdmin SQL Dump
-- version 2.10.1
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Jul 19, 2007 at 12:30 AM
-- Server version: 5.0.41
-- PHP Version: 5.2.0-10

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE `authors` (
  `username` varchar(32) collate latin1_general_cs NOT NULL default '' COMMENT 'SVN Username',
  `fullname` varchar(64) character set utf8 NOT NULL default '' COMMENT 'Display Name',
  `commits` int(10) unsigned NOT NULL default '0' COMMENT 'Total Commits',
  `active` tinyint(1) NOT NULL default '0' COMMENT 'Has Committed Recently',
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

CREATE TABLE `changes` (
  `revision` int(10) unsigned NOT NULL default '0' COMMENT 'SVN Revision',
  `action` char(1) collate latin1_general_cs NOT NULL default '' COMMENT 'Action Type',
  `path` varchar(255) collate latin1_general_cs NOT NULL default '' COMMENT 'File or Folder Changed',
  `copy_path` varchar(255) collate latin1_general_cs default NULL COMMENT 'If this was copied or renamed, this is where it was from.',
  `copy_revision` int(10) unsigned default NULL COMMENT 'Revision this was copied from if it was copied or renamed.',
  KEY `revision` (`revision`),
  FULLTEXT KEY `path` (`path`,`copy_path`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;

CREATE TABLE `commits` (
  `revision` int(10) unsigned NOT NULL default '0' COMMENT 'SVN Revision',
  `author` varchar(32) collate latin1_general_cs NOT NULL default '' COMMENT 'Username',
  `date` datetime NOT NULL default '0000-00-00 00:00:00' COMMENT 'Commit Date',
  `message` text character set utf8 NOT NULL COMMENT 'Log Message',
  UNIQUE KEY `revision` (`revision`),
  FULLTEXT KEY `message` (`message`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;
