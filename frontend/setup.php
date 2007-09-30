<?php

// SVN Changelog: Online Viewer Web Administration
// Authors: Bryan Petty

if(!file_exists('config.php'))
	die('Please copy "config.php.dist" to "config.php" and change your settings as needed.');

require_once('config.php');
require_once("template.php");

session_cache_expire(24);
session_start();

/// Build and output the final page within the template.
function slb_output($content, $message = '')
{
    global $stylesheet;
    if($message != '')
        $content = "<p>$message</p>\n\n" . $content;
    $template = new Template();
    $template->assign_var('STYLESHEET', $stylesheet);
    $template->assign_var('CONTENT', $content);
    $template->set_filenames(array('setup' => 'setup.tpl'));
    $template->pparse('setup');
    die();
}

/// Display an error message in the template, and die.
function slb_die($error, $show_main = true)
{
    $content = '';
    $message = "<div class=\"error_msg\">$error</div>";
    if($show_main)
        $content .= slb_main_page();
    slb_output($content, $message);
    die();
}

/// Validate the submitted password.
if(isset($_POST['auth']))
{
    sleep(1); // This isn't secure, but better than nothing.
    if($_POST['auth'] == $db_password)
        $_SESSION['authed'] = true;
    else
        slb_die('Incorrect password.', false);
}

/// Show login form if not authenticated.
if(!isset($_SESSION['authed']))
{
    $content = <<<CONTENT
<form method="post" action="setup.php">
  <label for="auth">Password:</label>
  <input type="password" name="auth" id="auth" size="16" maxlength="128" value=""/>
</form>
CONTENT;
    slb_output($content, 'Please enter the same password used for database access.');
}

/// Build the changelog table for viewing/editing with the new changelog form.
function slb_main_page()
{
    global $changelogs;
    $content = "<h2>Changelogs:</h2>\n\n";

    if(count($changelogs) == 0)
        $content .= "<p>No changelogs have been setup.</p>\n\n";
    else
    {
        $content .= '<table border="0" cellspacing="1" cellpadding="5">' . "\n" .
                    "<tr><th>Name</th><th>SVN URL</th><th>Revision</th><th>Action</th></tr>\n";
        foreach($changelogs as $id => $cl)
        {
            $content .= <<<CONTENT
<tr>
    <td>${cl['title']}</td><td>${cl['svn']}</td><td>${cl['latest']}</td>
    <td><a href="setup.php?edit&amp;t=$id"><img src="images/pencil.png" alt="Edit" title="Edit"/></a>
        <a href="setup.php?delete&amp;t=$id" onclick="return confirm('Are you sure you want to delete ${cl['name']}?');">
            <img src="images/delete.png" alt="Delete" title="Delete"/></a></td>
</tr>
CONTENT;
        }
        $content .= "\n</table>";
    }

    $content .= "\n\n" . slb_changelog_form();

    return $content;
}

/// Build the XHTML form for adding or editing a changelog.
function slb_changelog_form($id = false)
{
    global $changelogs;
    $id_input = ''; $header = 'Add New'; $submit_value = 'Add';
    $prefix_input = "<td><input type=\"text\" size=\"10\" id=\"prefix\" name=\"prefix\" value=\"${cl['table_prefix']}\"/></td></tr>";
    $cl = array('title' => '', 'table_prefix' => '', 'svn' => '',
                'summary_limit' => '10', 'trunk' => '', 'tags' => '',
                'branches' => '', 'diff_url' => '');
    if($id !== false)
    {
        $id_input = '<input type="hidden" name="cl_id" value="'.$id.'"/>';
        $cl = $changelogs[$id];
        $header = 'Editing ' . $cl['title']; $submit_value = 'Update';
        $prefix_input = "<td>${cl['table_prefix']} (need to create a new changelog to change for now)</td></tr>";
    }

    return <<<FORM
<h2>$header:</h2>

<form method="post" action="setup.php">
    $id_input
    <input type="hidden" name="edit" value="edit"/>
    <table border="0" cellspacing="1" cellpadding="5">
        <tr><td><label for="name">Name:</label></td>
            <td><input type="text" size="20" id="name" name="name" value="${cl['title']}"/></td></tr>
        <tr><td><label for="prefix">Table Prefix:</label></td>
            $prefix_input
        <tr><td><label for="svn_url">SVN URL:</label></td>
            <td><input type="text" size="45" id="svn_url" name="svn_url" value="${cl['svn']}"/></td></tr>
        <tr><td><label for="summary_limit">Summary Limit:</label></td>
            <td><input type="text" size="5" id="summary_limit" name="summary_limit" value="${cl['summary_limit']}"/></td></tr>
        <tr><td><label for="trunk">Trunk Path:</label></td>
            <td><input type="text" size="20" id="trunk" name="trunk" value="${cl['trunk']}"/></td></tr>
        <tr><td><label for="tags">Tags Path:</label></td>
            <td><input type="text" size="20" id="tags" name="tags" value="${cl['tags']}"/></td></tr>
        <tr><td><label for="branches">Branches Path:</label></td>
            <td><input type="text" size="20" id="branches" name="branches" value="${cl['branches']}"/></td></tr>
        <tr><td><label for="diff_url">Diff URL:</label></td>
            <td><input type="text" size="45" id="diff_url" name="diff_url" value="${cl['diff_url']}"/></td></tr>
    </table>
    <input type="submit" name="submit" value="$submit_value"/>
</form>
FORM;

}

/// User submitted a new changelog, or changes to an existing one.
if(isset($_POST['edit']))
{
    $newcl = true;
    if(isset($_POST['cl_id']))
    {
        $newcl = false;
        $id = $_POST['cl_id'];
    }

    $name           = $_POST['name'];
    $prefix         = $_POST['prefix'];
    $svn_url        = $_POST['svn_url'];
    $summary_limit  = $_POST['summary_limit'];
    $trunk          = $_POST['trunk'];
    $tags           = $_POST['tags'];
    $branches       = $_POST['branches'];
    $diff_url       = $_POST['diff_url'];

    if($newcl)
    {
        foreach($changelogs as $id => $cl)
            if($cl['table_prefix'] == $prefix)
                slb_die('The given table prefix is already in use.');

        // Add new tables with prefix.

        if(($sql_authors = file_get_contents('sql/authors.sql')) === false ||
           ($sql_changes = file_get_contents('sql/changes.sql')) === false ||
           ($sql_commits = file_get_contents('sql/commits.sql')) === false)
           slb_die('Error reading SQL table files, please check permissions.');

        $sql_authors = str_replace('{PREFIX}', $prefix, $sql_authors);
        $sql_changes = str_replace('{PREFIX}', $prefix, $sql_changes);
        $sql_commits = str_replace('{PREFIX}', $prefix, $sql_commits);

        if(!mysql_query($sql_authors) || !mysql_query($sql_changes) || !mysql_query($sql_commits))
            slb_die(mysql_error());

        // Insert new changelog into settings.

        if(!mysql_query("INSERT INTO `changelogs` (`name`, `table_prefix`, `svn_url`, `summary_limit`, `trunk`, `tags`, `branches`, `diff_url`)" .
                        "VALUES (\"$name\", \"$prefix\", \"$svn_url\", $summary_limit, \"$trunk\", \"$tags\", \"$branches\", \"$diff_url\")"))
            slb_die(mysql_error());
        slb_read_settings();

        slb_output(slb_main_page(), $name . ' changelog added successfully.');
    }
    else if(isset($id))
    {
        // Update existing changelog settings in the database.

        if(!mysql_query("UPDATE `changelogs` SET `name` = \"$name\", `svn_url` = \"$svn_url\", " .
                        "`summary_limit` = $summary_limit, `trunk` = \"$trunk\", `tags` = \"$tags\", " .
                        "`branches` = \"$branches\", `diff_url` = \"$diff_url\" WHERE `id` = $id"))
            slb_die(mysql_error());
        slb_read_settings();

        slb_output(slb_main_page(), $name . ' changelog updated successfully.');
    }

    slb_die('Error adding/updating changelog.');
}

/// User is requesting deletion of a changelog.
else if(isset($_GET['delete']))
{
    $id = $_GET['t'];
    $cl = $changelogs[$id];
    $name = $cl['title'];

    if(!mysql_query("DROP TABLE `${cl['authors_table']}`, `${cl['changes_table']}`, `${cl['commits_table']}`"))
        slb_die(mysql_error());
    if(!mysql_query("DELETE FROM `changelogs` WHERE `id` = $id"))
        slb_die(mysql_error());
    slb_read_settings();

    slb_output(slb_main_page(), $name . ' changelog has been removed.');
}

/// User submitted full name changes to a changelog.
else if(isset($_POST['dev_update']))
{
    $id = $_POST['cl_id'];
    $cl = $changelogs[$id];

    $result = mysql_query("SELECT * FROM `${cl['authors_table']}`");
    while($row = mysql_fetch_assoc($result))
    {
        $fullname = addslashes($_POST['fn_'.$row['username']]);
        if(!mysql_query("UPDATE `${cl['authors_table']}` SET `fullname` = \"$fullname\" WHERE `username` = \"${row['username']}\""))
            slb_die(mysql_error());
    }

    slb_output(slb_main_page(), "Updated developers for ${cl['title']} successfully.");
}

/// User requested to edit an existing changelog, show the forms.
if(isset($_GET['edit']))
{
    $id = $_GET['t'];
    $cl = $changelogs[$id];

    $content = "<p><a href=\"setup.php\">Back to Main</a></p>\n\n";
    $content .= slb_changelog_form($id);

    $content .= "\n\n<h2>Developers:</h2>\n\n";

    if(!($result = mysql_query("SELECT * FROM `${cl['authors_table']}` ORDER BY `username`")))
        slb_die(mysql_error());
    if(mysql_num_rows($result) == 0)
        $content .= "<p>No developers to edit, please run an update first.</p>";
    else
    {

        $content .= <<<CONTENT
<form method="post" action="setup.php">
<input type="hidden" name="dev_update" value="dev_update"/>
<input type="hidden" name="cl_id" value="$id"/>
<table border="0" cellspacing="1" cellpadding="5">
<tr><th>Username</th><th>Full Name</th><th>Commits</th><th>Active</th></tr>
CONTENT;

        while($row = mysql_fetch_assoc($result))
        {
            $active = $row['active'] ? 'Yes' : 'No';
            $fullname = stripslashes(htmlspecialchars($row['fullname']));
            $content .= <<<CONTENT
<tr>
    <td>${row['username']}</td>
    <td><input type="text" size="20" name="fn_${row['username']}" value="$fullname"/></td>
    <td>${row['commits']}</td><td>$active</td>
</tr>
CONTENT;
        }

        $content .= <<<CONTENT
</table>
<input type="submit" name="submit" value="Update"/>
</form>
CONTENT;

    }

    slb_output($content);
}

// If we're still running here, we default to the main page.
slb_output(slb_main_page());

?>
