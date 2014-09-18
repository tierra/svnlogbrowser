<?php
// $Id$

// SVN Changelog: Common Helper Functions
// Authors: Bryan Petty

require_once('config.php');

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

function svnlog_format_path($path, $action = '')
{
	global $pathsep, $changelog;
	$xhtml = ''; $clean_path = ''; $matches = array();

	$sep = $changelog['pathsep'];
	$pathsep = $sep;
	if($sep == '\\') $sep = '\\\\';

	// Trunk Commit
	if(preg_match("%^{$changelog['trunk']}(.*)$%", $path, $matches))
		$clean_path = $matches[1];
	// Branch Commit
	else if(preg_match("%^{$changelog['branches']}$sep([^$sep]+)(.*)$%", $path, $matches))
	{
		$xhtml .= "<span class=\"branch\">[{$matches[1]}]</span>&nbsp;";
		$clean_path = $matches[2];
	}
	// Tag Commit
	else if(preg_match("%^{$changelog['tags']}$sep([^$sep]+)(.*)$%", $path, $matches))
	{
		$xhtml .= "<span class=\"tag\">[{$matches[1]}]</span>&nbsp;";
		$clean_path = $matches[2];
	}
	else
		$clean_path = $path;
	
	if($clean_path == '') $clean_path = $pathsep;
	
	if($action == '')
		$xhtml .= "{$clean_path}";
	else
		$xhtml .= "<span class=\"{$action}\">{$clean_path}</span>";

	return $xhtml;
}

function svnlog_format_change($revision, $action, $path, $copy_path = '', $copy_revision = '')
{
	global $icons, $changelog;
	$output = '';

	$output .= "      " . image($icons[$action], $action) . "&nbsp;&nbsp;";
	$output .= svnlog_format_path($path, $action);
	if($changelog['diff_url'] != '')
	{
		$output .= "&nbsp;&nbsp;<span class=\"ext_links\">[";
		switch($action)
		{
		case 'M':
			$previous_revision = $revision - 1;
			$output .= anchor("{$changelog['diff_url']}{$path}?" .
				"r1={$previous_revision}&amp;r2={$revision}" .
				"&amp;pathrev={$revision}", "diff");
			$output .= ", " . anchor("{$changelog['diff_url']}{$path}?" .
				"view=log&amp;pathrev={$revision}", "log");
			break;
		case 'D':
			$previous_revision = $revision - 1;
			$output .= anchor("{$changelog['diff_url']}{$path}?view=log" .
				"&amp;pathrev={$previous_revision}", "old log");
			break;
		default: // 'A' and 'R' are basically the same for needed links.
			$output .= anchor("{$changelog['diff_url']}{$path}?view=log" .
				"&amp;pathrev={$revision}", "log");
			break;
		}
		if($changelog['link_files'] && $action != 'D')
			$output .= ", " . anchor("{$changelog['svn_root']}{$path}", "file");
		$output .= "]</span>";
	}
	else if($changelog['trac_url'] != '')
	{
		$output .= "&nbsp;&nbsp;<span class=\"ext_links\">[";
		switch($action)
		{
		case 'M':
			$output .= anchor("{$changelog['trac_url']}/changeset/{$revision}{$path}", "diff") . ", ";
			$output .= anchor("{$changelog['trac_url']}/log{$path}?rev={$revision}", "log");
			break;
		case 'D':
			$output .= anchor("{$changelog['trac_url']}/log{$path}?rev={$revision}", "old log");
			break;
		default: // 'A' and 'R' are basically the same for needed links.
			$output .= anchor("{$changelog['trac_url']}/log{$path}?rev={$revision}", "log");
			break;
		}
		if($changelog['link_files'] && $action != 'D')
			$output .= ", " . anchor("{$changelog['svn_root']}{$path}", "file");
		$output .= "]</span>";
	}
	else if($changelog['link_files'] && $action != 'D')
	{
		$output .= "&nbsp;&nbsp;<span class=\"ext_links\">[";
		$output .= anchor("{$changelog['svn_root']}{$path}", "file");
		$output .= "]</span>";
	}
	if($copy_path != '')
	{
		if($changelog['diff_url'] != '')
			$output .= "&nbsp;&nbsp;(copied from " . anchor("{$changelog['diff_url']}{$copy_path}?" .
				"view=log&amp;pathrev={$copy_revision}", "r{$copy_revision}") . " of ";
		else
			$output .= "&nbsp;&nbsp;(copied from r{$copy_revision} of ";
		$output .= svnlog_format_path($copy_path) . ")";
	}
	$output .= "<br />\n";

	return $output;
}

function svnlog_format_message($message)
{
	$formatted = array();
	$tokenized = explode("\n", $message);
	foreach($tokenized as $line)
	{
		$line = htmlspecialchars($line); $x = 0;
		while($x < strlen($line)) { if($line[$x] != ' ') break; $x++; }
		$space = str_repeat(' &nbsp;', (int)($x / 2));
		$line = $space . substr($line, $x - ($x % 2));
		$formatted[] = '&nbsp;' . $line;
	}
	if(trim(end($tokenized)) == '') array_pop($formatted);
	$text = implode($formatted, "\n<br/>");
	// Munge email addresses for now until options can be added to changelogs.
	$text = preg_replace('/((?:[a-z0-9\-_]+\.)*[a-z0-9\-_]+)@(?:[a-z0-9\-]+\.)+[a-z]{2,4}/i', '$1@...', $text);

	return $text;
}

if(!function_exists('http_build_query'))
{
	function http_build_query($data, $prefix = null, $separator = null, $key = null)
	{
		$res = array();
		foreach((array)$data as $k => $v)
		{
			$tmp_key = is_int($k) ? $prefix . $k : $k;
			if($key) $tmp_key = $key.'['.$tmp_key.']';
			if(is_array($v) || is_object($v))
				$res[] = http_build_query($v, $prefix, $separator, $tmp_key);
			else
				$res[] = urlencode($tmp_key) . "=" . urlencode($v);
		}
		if($separator == null) $separator = ini_get('arg_separator.output');
		return implode($separator, $res);
	}
}

?>
