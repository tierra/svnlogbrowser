<?php

// SVN Changelog: Revision Changes Request Handler (Used from AJAX)
// Authors: Bryan Petty

require_once('config.php');
require_once('functions.php');

$logid = 0;
if(isset($_GET['t']))
{
    $logid = intval($_GET['t']);
    if(!isset($changelogs[$logid]))
        $logid = 0;
}

$changelog = $changelogs[$logid];
$revision = intval($_GET['r']);

$db = mysql_connect($changelog['host'], $changelog['username'], $changelog['password'])
	or die("<br>Could not connect to the MySQL Server!\n");

mysql_select_db($changelog['database'], $db)
	or die("<br>Could not select the MySQL database \"$qp_mysql_database\"!\n");

// Lets us correctly read UTF-8 columns
mysql_query("SET NAMES 'utf8'");

$changes = mysql_query("SELECT * FROM changes WHERE revision = {$revision} ORDER BY path");
if($changes === false)
	print "No changes found for this commit.";
else
{
	$num_changes = mysql_num_rows($changes);
	while($row = mysql_fetch_assoc($changes))
	{
		print svnlog_format_change($revision, $row['action'], $row['path'],
								   $row['copy_path'], $row['copy_revision']);
	}
}

?>
