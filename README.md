# svnLogBrowser

**This project has been retired, use at your own risk!**

svnLogBrowser provides a web-based frontend for browsing through commit logs
from any Subversion repository, and is released under the [GNU GPL][gpl]. It
gives developers a tool for quickly locating changes, reviewing peer
developer's commits, or just a general overview of what recent changes have
been made to a project.

[gpl]: http://svn.svnlogbrowser.org/trunk/LICENSE


## Features

- Quick, fulltext searches on changed paths or logs of all commits.
- Ability to syndicate only specific SVN paths, not just whole repositories.
- Identifies configured trunk, tag, or branch commits.
- Visitors can browse and search through individual developers' commits.
- Automatic links to path logs and file diffs. (only ViewVC is currently
  supported, more to follow soon)
- Automatic direct links to changed SVN files/paths. (only when using 
  `http[s]://` Subversion URLs)
- Customizable display names for developers.
- Commit counts for each developer.
- Optimized interface only shows active developers by default.
- Supports multiple changelogs in single installation.
- Stores current view settings in URL for linking to peers.
- Large commits can be hidden by default. (optimized with AJAX)
- Easy to use web installation and configuration script.


## Live Demos

[Subversion SVN Changelog][svnlog] - A changelog of all commits to the
[Subversion](http://subversion.tigris.org/) repository. The svnLogBrowser
changelog is also hosted here.

[wxWidgets SVN Changelog][wxlog] - This changelog shows off the power of these
scripts on one of the largest open source SVN repositories (with about 50,000
revisions).

[svnlog]: http://demo.svnlogbrowser.org/
[wxlog]: http://wx.ibaku.net/changelog/


## Requirements

- Webserver with PHP 4.3 or later
- MySQL 4.1 or later (untested on 4.0)
- Python 2.4 or later
- Python Modules: [MySQLdb][mysqldb], [pysvn][pysvn]

[mysqldb]: http://sourceforge.net/projects/mysql-python/
[pysvn]: http://pysvn.tigris.org/


## Quick Start

1. Setup a new MySQL database and user with access to that database.
2. Edit the `slb-update.py` script, and configure the database credentials.
3. Move the `frontend` folder to the location on your website you would like
   users to access the logs.
4. In your frontend folder, move `config.php.dist` to `config.php`, and edit
   `config.php`, adding in the database credentials. (this is done once for
   the update script, and once for the web frontend)
5. Point your browser at `http://example.com/path/to/slb/setup.php` to
   configure svnLogBrowser. See the "Configuration" section for details on
   settings.
6. Run `slb-update.py` to make the first update. Keep in mind that this can
   take a long time for large repositories, be patient.
    * `$ python slb-update.py`
7. Go back to the `setup.php` page, and add any developer's names you wish to
   display, then lock out access to the `setup.php` page. The page may require
   the password to edit anything, but it is not secure.
    * `$ chmod 000 setup.php`
8. Edit your crontab, and add the `slb-update.py` script to run however often
   you want it to update your changelogs.


## Introduction

There are two individual parts of svnLogBrowser that need to work together to
bring users the latest changes. The first is the frontend which consists of
all the PHP scripts for browsing changes, and changing settings. The second is
the Python `slb-update.py` script, which is built to run periodically from
cron which reads the latest commits from the SVN repository, and adds them to
the database for quick retrieval and searching.

The scripts are written in a way that it's possible to configure a secondary
server to do the updates so the web frontend only requires MySQL and PHP, but
it is more efficient to run the update script on the same server as MySQL.

Once the first update has been made, subsequent updates will only retrieve
logs for new commits. So after you've made the first pass, updates will
usually only take a few seconds. If you don't like the interval of time when
commits have been made, but aren't shown in the frontend, you could even add
the update script into the commit hooks for the SVN repository if you have the
appropriate access. (instructions not given)


## Configuration

This section outlines what each of the changelog settings are for.

Name: This is the displayed name for the changelog, simple enough.

**Table Prefix**: Each of your changelogs use individual tables in the
database to ease the stress of searches and administration. The name of the
tables used will be prefixed with this setting. These must be unique between
changelogs.

**SVN URL**: This should be the URL to the SVN repository you want to watch
logs from. You can use any of the standard URI that Subversion supports. Any
of `svn://`, `http://`, and `https://` will work. If you use a HTTP based URL,
svnLogBrowser will automatically link to the files in the browser so users can
take a look at the whole file in question.

**Summary Limit**: If any one commit contains more than the given number of
changes here, then they will be hidden from the user by default with a link
the user must click to see them. This is overridden if the user has turned off
file summarizing. This functionality helps prevent PHP memory limit problems
and also speeds up page load times significantly for some requests since the
changes aren't actually generated, sent to the browser, and rendered until the
user clicks the link. 10 changes is a good default setting for most.

**Trunk, Tags, and Branches**: (Optional) For svnLogBrowser to identify, mark,
and shorten the display of changed paths, it needs to be told where each of
these folders are located relative to the repository root. In traditional
repositories, these will simply be `/trunk`, `/tags`, and `/branches`
respectively. Please note the missing trailing slash, this is important.

**Diff URL**: (Optional) If you have ViewVC setup for your repository, you can
add the URL to your top ViewVC page for the repository and svnLogBrowser will
automatically link changes to their diffs and path logs in ViewVC. For
Example, if your repository was `MyProject`, you would set this to something
like this: `http://www.myproject.com/viewvc/MyProject` (no trailing slash)
