<?php

// SVN Changelog: Revision Changes Request Handler (Used from AJAX)
// Authors: Bryan Petty

require_once('config.php');
require_once('functions.php');

$logid = 1;
if(isset($_GET['t']))
{
    $logid = intval($_GET['t']);
    if(!isset($changelogs[$logid]))
        $logid = 1;
}

$changelog = $changelogs[$logid];
$revision = intval($_GET['r']);

$changes = mysql_query("SELECT * FROM ${changelog['changes_table']} " .
                       "WHERE revision = {$revision} ORDER BY path");
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
