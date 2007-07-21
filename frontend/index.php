<?php

// SVN Changelog: Online Viewer
// Authors: Tim Kosse, Bryan Petty

if(!file_exists('config.php'))
	die('Please copy "config.php.dist" to "config.php" and change your settings as needed.');

require('config.php');

// GET Var	Description
// ------------------------------------------------------------------
// "t"		Changelog type.
// "p"		Current page.
// "c"		Number of changes per page.
// "s"		File summary setting.
// "r"		Search range (logs, files, or both)
// "q"		Search query.
// "d"		Only show these developers' changes.
// "a"		Show only active devs (filter selection, not commits)

$time_start = microtime(true);

include("template.php");

$template = new Template();

$template->assign_var('STYLESHEET', $stylesheet);

function anchor($location, $label, $onclick = '', $class = '')
{
	$anchor = "<a href=\"$location\"";
	if($onclick != '')
		$anchor .= " onclick=\"$onclick\"";
	if($class != '')
		$anchor .= " class=\"$class\"";
	$anchor .= ">$label</a>";
	return $anchor;
}

function image($src, $alt = '', $title = '')
{
	$image = "<img src=\"images/$src\" alt=\"$alt\"";
	if($title != '')
		$image .= " title=\"$title\"";
	$image .= " />";
	return $image;
}

foreach($icons as $action => $image)
	$template->assign_var('ICON_' . $action, $image);

$dev_delimiter = '-';

$defaults = array();
$clv = array();

// Initialize all settings...
// (set defaults where needed while validating input)

$defaults['t'] = 0;
$defaults['p'] = 1;
$defaults['c'] = CHANGELOG_MIN_PER_PAGE;
$defaults['s'] = 1; // Update below if changed.
$defaults['q'] = '';
$defaults['r'] = 3;
$defaults['d'] = array();

$clv['t'] = $defaults['t'];
if(isset($_GET['t']))
{
    $clv['t'] = (int)$_GET['t'];
    if(!isset($changelogs[$clv['t']]))
        $clv['t'] = 0;
}

$changelog = $changelogs[$clv['t']];
$template->assign_var('TYPE', $changelog['title']);

// Now that we know the type, we can setup the database connection.

$db = mysql_connect($changelog['host'], $changelog['username'], $changelog['password'])
	or die("<br>Could not connect to the MySQL Server!\n");

mysql_select_db($changelog['database'], $db)
	or die("<br>Could not select the MySQL database \"$qp_mysql_database\"!\n");

// Lets us correctly read UTF-8 columns
mysql_query("SET NAMES 'utf8'");

$devs = array();
$result = mysql_query("SELECT * FROM authors WHERE username != ''
		       $condition ORDER BY commits DESC");
while($row = mysql_fetch_assoc($result))
{
	$devs[$row['username']] = array(
		'name' => $row['fullname'],
		'changes' => $row['commits'],
		'active' => $row['active']
	);
	if($row['fullname'] == '')
		$devs[$row['username']]['name'] = "User: " . $row['username'];
}

$clv['r'] = $defaults['r'];
if(isset($_GET['r']))
{
	$numeric = (int)$_GET['r'];
	if(1 <= $numeric && $numeric <= 3)
		$clv['r'] = $numeric;
}

$clv['s'] = $defaults['s'];
if(isset($_GET['s']) && $_GET['s'] == 0)
	$clv['s'] = 0;

$clv['q'] = $defaults['q'];
if(isset($_GET['q']))
{
	$searchq = $_GET['q'];
	if(get_magic_quotes_gpc())
	{
		if(ini_get('magic_quotes_sybase'))
			$searchq = str_replace("''", "'", $searchq);
		else
			$searchq = stripslashes($searchq);
	}
	$clv['q'] = mysql_real_escape_string($searchq);
}

$clv['d'] = $defaults['d'];
if(isset($_GET['d']))
{
	$new = array();
	$values = explode($dev_delimiter, $_GET['d']);
	if($values !== false)
	{
		foreach($values as $entry)
		{
			if(isset($devs[$entry]) && !in_array($entry, $new))
				$new[] = $entry;
		}
	}
	if(count($new) > 0)
		$clv['d'] = $new;
}

$clv['c'] = $defaults['c'];
if(isset($_GET['c']))
{
    $clv['c'] = (int)$_GET['c'];
    if($clv['c'] < CHANGELOG_MIN_PER_PAGE)
        $clv['c'] = CHANGELOG_MIN_PER_PAGE;
    else if($clv['c'] > CHANGELOG_MAX_PER_PAGE)
        $clv['c'] = CHANGELOG_MAX_PER_PAGE;
}

// We'll first get a quick and dirty upper limit on the page count so
// we can save a few CPU cycles in case the page request was higher
// than the max page on a default view with nothing filtered.

$row = mysql_fetch_row(mysql_query("SELECT COUNT(revision) FROM commits"));
$total_revisions = $row[0];
$pagecount = ceil($total_revisions / $clv['c']);

$clv['p'] = $defaults['p'];
if(isset($_GET['p']))
{
	$clv['p'] = (int)$_GET['p'];
	if($clv['p'] < 1)
		$clv['p'] = 1;
	if($clv['p'] > $pagecount)
		$clv['p'] = $pagecount;
}

// Do the actual query for the revisions requested and adjust the revision count.
$num_revisions = 0;
$db_where_expr = '';
for($x = 0; $x < count($clv['d']); $x++)
{
	$db_where_expr .= "author = '{$clv['d'][$x]}'";
	if($x + 1 != count($clv['d']))
		$db_where_expr .= " OR ";
}
if($clv['q'] != '')
{
	// TODO: Handle search queries

//	if($clv['r'] == 1 || $clv['r'] == 2)
//		$db_where_expr .= " MATCH(path";
}
$db_offset = ($clv['p'] - 1) * $clv['c'];
if($db_where_expr != '') $db_where_expr = "WHERE $db_where_expr";
$db_query = "SELECT SQL_CALC_FOUND_ROWS * FROM commits $db_where_expr ORDER BY date DESC";
$db_limit = " LIMIT $db_offset, {$clv['c']}";
//echo "Query: $db_query\n\n";
$db_commits = mysql_query($db_query . $db_limit);
if($db_commits !== false)
	$num_revisions = mysql_num_rows($db_commits);
//else
//	echo mysql_error();

// Grab the total revision count for our real query.
$result = mysql_query("SELECT FOUND_ROWS()");
$row = mysql_fetch_row($result);
$total_revisions = $row[0];

// Re-adjust page count based on real query this time.
$pagecount = ceil($total_revisions / $clv['c']);
if(!$pagecount)
	$pagecount = 1;
if($clv['p'] > $pagecount)
{
	// Somehow a request still came through beyond the range of available pages,
	// but still low enough for the default view with nothing filtered.
	// We'll want to cap the page and re-run the query for the last available page.
	$clv['p'] = $pagecount;
	$db_offset = ($clv['p'] - 1) * $clv['c'];
	// So much for saving CPU cycles this run, but this should rarely happen.
	$db_commits = mysql_query($db_query . " LIMIT $db_offset, {$clv['c']}");
}

$template->assign_var('LINECOUNT', number_format($total_revisions));

$sep = $changelog['pathsep'];
$pathsep = $sep;
if($sep == '\\') $sep = '\\\\';
$preg_trunk  = "%^{$changelog['trunk']}(.*)$%";
$preg_branch = "%^{$changelog['branches']}$sep([^$sep]+)(.*)$%";
$preg_tag    = "%^{$changelog['tags']}$sep([^$sep]+)(.*)$%";

function svnlog_format_path($path, $action = '')
{
	global $pathsep;
	global $preg_trunk, $preg_branch, $preg_tag;

	$xhtml = ''; $clean_path = '';
	
	if(preg_match($preg_trunk, $path, $matches))
		$clean_path = $matches[1];
	else if(preg_match($preg_branch, $path, $matches))
	{
		$text .= "<span class=\"branch\">[{$matches[1]}]</span>&nbsp;";
		$clean_path = $matches[2];
	}
	else if(preg_match($preg_tag, $path, $matches))
	{
		$text .= "<span class=\"tag\">[{$matches[1]}]</span>&nbsp;";
		$clean_path = $matches[2];
	}
	
	if($clean_path == '') $clean_path = $pathsep;
	
	if($action == '')
		$text .= "{$clean_path}";
	else
		$text .= "<span class=\"{$action}\">{$clean_path}</span>";

	return $text;
}

$output = "<dl>\n";
$commit_summary_id = 0;
while($commit = mysql_fetch_assoc($db_commits))
{
	$output .= "  <dt>";
	$output .= "r{$commit['revision']}: {$commit['date']}";
	if($commit['author'] != '')
		$output .= ' [' . $commit['author'] . '] ' . $devs[$commit['author']]['name'];
	$output .= "</dt>\n";

	$output .= "  <dd>\n";

	$changes = mysql_query("SELECT * FROM changes WHERE revision = {$commit['revision']} ORDER BY path");
	if($changes === false)
		$output .= "    <p>No changes found for this commit.</p>\n";
	else
	{
		$output .= "    <p>\n";
		$num_changes = mysql_num_rows($changes);
		if($clv['s'] == 1 && $num_changes > $changelog['file_summary_limit'])
		{
			$output .= "      " . anchor("javascript:;", "Click to show all " . number_format($num_changes) . " changes...",
				"this.className='hidden';document.getElementById('csi{$commit_summary_id}').className='shown';", "shown");
			$output .= "<span id=\"csi{$commit_summary_id}\" class=\"hidden\">\n";
		}
		while($row = mysql_fetch_assoc($changes))
		{
			$path = $row['path']; $matches = array();
			$output .= "      " . image($icons[$row['action']], $row['action']) . "&nbsp;&nbsp;";
			$output .= svnlog_format_path($path, $row['action']);
			if($changelog['viewvc'] != '')
			{
				$output .= "&nbsp;&nbsp;<span class=\"ext_links\">[";
				switch($row['action'])
				{
				case 'M':
					$previous_revision = $commit['revision'] - 1;
					$output .= anchor("{$changelog['viewvc']}{$row['path']}?" .
						"r1={$previous_revision}&amp;r2={$commit['revision']}" .
						"&amp;pathrev={$commit['revision']}", "diff");
					$output .= ", " . anchor("{$changelog['viewvc']}{$row['path']}?" .
						"view=log&amp;pathrev={$commit['revision']}", "log"). "]</span>";
					break;
				case 'D':
					$previous_revision = $commit['revision'] - 1;
					$output .= anchor("{$changelog['viewvc']}{$row['path']}?view=log" .
						"&amp;pathrev={$previous_revision}", "old log");
					break;
				default: // 'A' and 'R' are basically the same for needed links.
					$output .= anchor("{$changelog['viewvc']}{$row['path']}?view=log" .
						"&amp;pathrev={$commit['revision']}", "log");
					break;
				}
				if($changelog['svn'] != '')
					$output .= ", " . anchor("{$changelog['svn']}{$row['path']}", "file");
				$output .= "]</span>";
			}
			else if($changelog['svn'] != '')
			{
				$output .= "&nbsp;&nbsp;<span class=\"ext_links\">[";
				$output .= anchor("{$changelog['svn']}{$row['path']}", "file");
				$output .= "]</span>";
			}
			if($row['copy_path'] != '')
			{
				if($changelog['viewvc'] != '')
					$output .= "&nbsp;&nbsp;(copied from " . anchor("{$changelog['viewvc']}{$row['copy_path']}?" .
						"view=log&amp;pathrev={$row['copy_revision']}", "r{$row['copy_revision']}") . " of ";
				else
					$output .= "&nbsp;&nbsp;(copied from r{$row['copy_revision']} of ";
				$output .= svnlog_format_path($row['copy_path']) . ")";
			}
			$output .= "<br />\n";
		}
		if($clv['s'] == 1 && $num_changes > $changelog['file_summary_limit'])
		{
			$output .= "      </span>\n";
			$commit_summary_id++;
		}
		$output .= "    </p>\n";
	}

	$message = nl2br(htmlspecialchars(trim($commit['message'])));
	$output .= "    <p>{$message}</p>\n";
	$output .= "  </dd>\n";
}
$output .= "</dl>\n";

$template->assign_var('CHANGELOG', $output);


// This will save us a lot of trouble generating links below.
// It will also reduce average URL lengths by filtering default values.
// Post returns the array of significant variables if true.
function get_query($custom = array(), $post = false)
{
	global $clv, $defaults, $dev_delimiter;
	$new = array_merge($clv, $custom);
	foreach($new as $key => $value)
		if(/*!array_key_exists($key, $custom) &&*/ $defaults[$key] == $value)
			unset($new[$key]);
	if(isset($new['d'])) $new['d'] = implode($dev_delimiter, $new['d']);
	if($post) return $new;
	$query = http_build_query($new, '', '&amp;');
	if($query != '')
		$query = '?' . $query;
	else
	{
		$url = $_SERVER['REQUEST_URI'];
		$query = substr($url, 0, strpos($url, '?'));
	}
	return $query;
}


// Assign remaining template variables that require get_query()...


$types = '';
while(list($key, $data) = each($changelogs))
{
	if($clv['t'] != $key)
		$types .= anchor(get_query(array('t' => $key)), $data['title']);
	else
		$types .= $data['title'];
	$types .= ', ';
}

$template->assign_var('TYPES', substr($types, 0, -2));

function get_pn_link($page)
{ return anchor(get_query(array('p' => $page)), $page) . " "; }

$pagenav = '';
if($clv['p'] > 1)
	$pagenav .= anchor(get_query(array('p' => $clv['p'] - 1)), image($icons['page_previous'], "Previous Page", "Previous Page"));
else
	$pagenav .= image($icons['page_previous'], "Previous Page", "Previous Page");
if($clv['p'] > 3)
	$pagenav .= get_pn_link(1);
if($clv['p'] > 4)
{
	$p1 = round(1 + ($clv['p'] - 3) / 3);
	$p2 = round(1 + ($clv['p'] - 3) / 3 * 2);
	$pagenav .= get_pn_link($p1);
	if($p1 != $p2)
		$pagenav .= get_pn_link($p2);
}
if($clv['p'] > 2)
	$pagenav .= get_pn_link($clv['p'] - 2);
// These aren't needed with the previous and next page links.
//if($clv['p'] > 1)
//	$pagenav .= get_pn_link($clv['p'] - 1);
$pagenav .= $clv['p'] . ' ';
//if($clv['p'] < $pagecount)
//	$pagenav .= get_pn_link($clv['p'] + 1);
if($clv['p'] < ($pagecount - 1))
	$pagenav .= get_pn_link($clv['p'] + 2);
if($clv['p'] < ($pagecount - 3))
{
	$p1 = $pagecount - ceil(1 + ($pagecount - $clv['p'] - 4) / 3 * 2);
	$p2 = $pagecount - floor(1 + ($pagecount - $clv['p'] - 4) / 3);
	$pagenav .= get_pn_link($p1);
	if($p1 != $p2)
		$pagenav .= get_pn_link($p2);
}
if($clv['p'] < ($pagecount - 2))
	$pagenav .= get_pn_link($pagecount);
$pagenav = substr($pagenav, 0, -1);
if($clv['p'] < $pagecount)
	$pagenav .= anchor(get_query(array('p' => $clv['p'] + 1)), image($icons['page_next'], "Next Page", "Next Page"));
else
	$pagenav .= image($icons['page_next'], "Next Page", "Next Page");

$template->assign_var('PAGENAV', $pagenav);

$changelinks = '';
$changesArray = array(25, 50, 100, 250);
while(list(, $changes) = each($changesArray))
{
	$page = ceil((($clv['p'] - 1) * $clv['c'] + 1) / $changes);
	if($changes != $clv['c'])
		$changelinks .= anchor(get_query(array('p' => $page, 'c' => $changes)), $changes) . ' ';
	else
		$changelinks .= $changes . ' ';
}
$template->assign_var('CHANGES', $changelinks);

$filesummary = '';
if($clv['s'] == 1)
	$filesummary = 'On | ' . anchor(get_query(array('s' => 0)), 'Off');
else
	$filesummary = anchor(get_query(array('s' => 1)), 'On') . ' | Off';
$template->assign_var('SUMMARIZE', $filesummary);

$hiddenvars = '';
foreach(get_query(array('q' => $defaults['q'], 'r' => $defaults['r'], 'p' => 1), true) as $name => $value)
	$hiddenvars .= '<input type="hidden" name="' . $name . '" value="' . $value . '"/>';
$template->assign_var('SEARCHQV', $hiddenvars);

$searchmethod = '';
if($clv['r'] == 1)
	$searchmethod .= '<input type="radio" name="r" value="1" id="search_files" checked="checked" /><label for="search_files">Files</label>';
else
	$searchmethod .= '<input type="radio" name="r" value="1" id="search_files" /><label for="search_files">Files</label>';
if($clv['r'] == 2)
	$searchmethod .= ' <input type="radio" name="r" value="2" id="search_logs" checked="checked" /><label for="search_logs">Logs</label>';
else
	$searchmethod .= ' <input type="radio" name="r" value="2" id="search_logs" /><label for="search_logs">Logs</label>';
if($clv['r'] == 3)
	$searchmethod .= ' <input type="radio" name="r" value="3" id="search_both" checked="checked" /><label for="search_both">Both</label>';
else
	$searchmethod .= ' <input type="radio" name="r" value="3" id="search_both" /><label for="search_both">Both</label>';
$template->assign_var('SEARCHRANGE', $searchmethod);

$template->assign_var('SEARCHQUERY', $clv['q']);


$devcontrol = '';
$devs_asort = array();

if($clv['d'] != $defaults['d'])
	$devcontrol .= '<div style="text-align: center; padding-bottom: 5px;">' .
		anchor(get_query(array('d' => $defaults['d'], 'p' => 1)), 'Show All') . "</div>\n";

// Create dev filter sorted by commits
$devcontrol .= '  <div id="devsort_commits">' . "\n";
foreach($devs as $username => $details)
{
	if(!$details['active'])
		$devcontrol .= '    <div class="inactive_user">';
	else
		$devcontrol .= '    <div>';
	$num_changes = number_format($details['changes']);
	if(array_search($username, $clv['d']) !== false)
		$devcontrol .= $details['name'] . " ($num_changes)";
	else
		$devcontrol .= anchor(get_query(array('d' => array($username), 'p' => 1)),
				      $details['name']) . " ($num_changes)";
	$devcontrol .= "</div>\n";
	$devs_asort[$details['name'] . ':' . $username] = array('username' => $username,
		'changes' => $details['changes'], 'active' => $details['active']);
}
$devcontrol .= "  </div>\n";

// Create dev filter sorted alphabetically
ksort($devs_asort);
$devcontrol .= '  <div id="devsort_adiv">' . "\n";
foreach($devs_asort as $name => $info)
{
	if(!$info['active'])
		$devcontrol .= '    <div class="inactive_user">';
	else
		$devcontrol .= '    <div>';
	$num_changes = number_format($info['changes']);
	$fullname = substr($name, 0, strrpos($name, ':'));
	if(array_search($info['username'], $clv['d']) !== false)
		$devcontrol .= $fullname . " ($num_changes)";
	else
		$devcontrol .= anchor(get_query(array(
			'd' => array($info['username']), 'p' => 1)),
			$fullname) . " ($num_changes)";
	$devcontrol .= "</div>\n";
}
$devcontrol .= "  </div>\n";

$template->assign_var('DEVCONTROL', $devcontrol);


// Do the actual page output now...


$time_end = microtime(true);
$template->assign_var('PROCESSTIME', sprintf('%.5f', $time_end - $time_start));

$template->set_filenames(array('index' => $page_tpl));
//printf("Page Generation Time: %02.5f Seconds", $time_end - $time_start);
$template->pparse('index');

?>
