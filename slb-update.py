#!/usr/bin/env python
# vim ai ts=4 sts=4 et sw=4
# $Id$

# svnLogBrowser: Log Update Script
# Author: Bryan Petty

# We just need database info here, from which we'll pull the rest of the
# configuration from. This should be the same database that the web frontend
# is (or will be) running from.

db_hostname = 'localhost'
db_username = ''
db_password = ''
db_database = ''


######## CONFIGURATION ENDS HERE ########


SLB_VERSION = '1.0.0'
print 'svnLogBrowser - Version: %s' % SLB_VERSION

import MySQLdb as dbapi
import sys, pysvn, time
from string import Template

def clear_null(value):

    if value is None: return ''
    return value

def get_changes(paths):
    """Converts pysvn.PysvnLog['changed_paths'] value to a compatible tuple for
       adding into the database."""

    changes = []
    for path in paths:
        copyfrom_rev = None
        if path['copyfrom_revision'] is not None:
            copyfrom_rev = path['copyfrom_revision'].number
        changes += [(path['action'], path['path'], path['copyfrom_path'], copyfrom_rev)]
    return changes

def insert_commit(db, cursor, table_prefix, log):
    """Adds a pysvn.PysvnLog entry into the svnLogBrowser database."""

    revision = log['revision'].number
    date = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(log['date']))
    commit_table = '%s_commits' % table_prefix
    changes_table = '%s_changes' % table_prefix
    changes = [(revision, action, path, copy_path, copy_rev) for
               (action, path, copy_path, copy_rev) in get_changes(log['changed_paths'])]

    try:
        cursor.execute("INSERT INTO `" + commit_table + "` (`revision`, `author`, `date`, `message`) VALUES (%s, %s, %s, %s)",
                       (revision, clear_null(log['author']), date, clear_null(log['message'])))
    except dbapi.DatabaseError, e:
        (code, message) = e
        # 1062 = Duplicate key error, meaning this commit was already added.
        if code != 1062: print "Error adding commit to database: %s" % e
        db.rollback()
        return

    # If the above was added successfully, we'll add the changed paths now.
    try:
        cursor.executemany("INSERT INTO `" + changes_table + "` (`revision`, `action`, `path`, `copy_path`, `copy_revision`) VALUES (%s, %s, %s, %s, %s)", changes)
    except dbapi.DatabaseError, e:
        print "Error adding change information (for revision %s) to the database: %s" % (revision, e)
        db.rollback()
        return

    db.commit()


# Connect to the database.
try:
    db = dbapi.connect(host    = db_hostname,
                       user    = db_username,
                       passwd  = db_password,
                       db      = db_database,
                       charset = 'utf8')
    cursor = db.cursor()

    # Pull the configuration from the database.
    cursor.execute('SELECT `id`, `name`, `table_prefix`, `latest_revision`, `svn_url` FROM `changelogs`')
    if cursor.rowcount < 1:
        print 'There are no changelogs setup, please run configuration first.'
        sys.exit(1)
    changelogs = cursor.fetchall()

except dbapi.Error, e:
    print 'Error initializing database: %s' % e
    sys.exit(1)

# Startup our SVN client.
client = pysvn.Client()
client.exception_style = 1

# Update the total commit counts and active status for each author (adding new authors to the table if needed).
authors_update = Template("""
    INSERT INTO `${prefix}_authors` (`username`, `commits`, `active`)
    SELECT `author`, COUNT(`author`), SUBDATE(NOW(), INTERVAL 1 YEAR) < MAX(`date`)
    FROM `${prefix}_commits` GROUP BY `author`
    ON DUPLICATE KEY UPDATE `commits` = VALUES(`commits`), `active` = VALUES(`active`)""")

# Main changelog loop.
for cl in changelogs:

    (uid, name, table_prefix, latest_revision, svn_url) = cl
    print 'Changelog: %s' % name
    print 'Last Update: %d' % latest_revision

    try:
        entry = client.info2(svn_url, revision =
                             pysvn.Revision(pysvn.opt_revision_kind.head),
                             recurse = False)
    except pysvn.ClientError, (e_msg, e):
        for error_message, code in e:
            print 'Error: #%d - %s' % (code, error_message)
        continue

    if len(entry) < 1:
        print 'Error retrieving information from the SVN repository.'
        continue

    (root_path, svn_info) = entry[0]
    print 'Current Revision: %d' % svn_info['rev'].number

    cursor.execute('UPDATE `changelogs` SET `svn_root` = %s WHERE `id` = %s', (svn_info['repos_root_URL'], uid))
    db.commit()

    if svn_info['rev'].number == latest_revision:
        print "No commits since last update, we're done here."
        continue

    # We split up the workload to only grab 100 revisions at a time.
    # This should be less stressful on the SVN server, and give us a way to
    # show the current progress.
    log_ranges = []
    latest_revision += 1
    while latest_revision + 99 < svn_info['rev'].number:
        log_ranges += [(latest_revision, latest_revision + 99)]
        latest_revision += 100
    log_ranges += [(latest_revision, svn_info['rev'].number)]

    for (start_rev, end_rev) in log_ranges:

        print 'Retrieving revisions %d through %d.' % (start_rev, end_rev)

        try:
            messages = client.log( svn_url,
                revision_start = pysvn.Revision(pysvn.opt_revision_kind.number, start_rev),
                revision_end = pysvn.Revision(pysvn.opt_revision_kind.number, end_rev),
                discover_changed_paths = True, strict_node_history = True, limit = 0 )
        except pysvn.ClientError, (e_msg, e):
            fatal_error = True
            for error_message, code in e:
                print 'Code:' , code , 'Message:' , error_message
                if code == 195012: fatal_error = False
            if not fatal_error:
                continue
            sys.exit(1)

        for log in messages:
            insert_commit(db, cursor, table_prefix, log)

        cursor.execute("UPDATE `changelogs` SET `latest_revision` = %s WHERE `id` = %s", (end_rev, uid))
        db.commit()

    cursor.execute( authors_update.substitute(prefix = table_prefix) )
    db.commit()

