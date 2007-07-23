#!/usr/bin/env python
# vim ai ts=4 sts=4 et sw=4

# SVN Changelog: XML to Database Script (Step 2 of 2)
# Author: Bryan Petty

# Use the same name here as you did in svnlog2xml.sh
name = 'svnLogBrowser'

dbinfo = {
    'host':	'localhost',
    'username':	'',
    'password':	'',
    'database':	''
}

status_update_count = 5000

######## CONFIGURATION ENDS HERE ########

import datetime, glob, os, re, sys
from xml.dom import minidom
# This should be easily changed to use entirely different database types.
import MySQLdb as dbapi

prefix = sys.path[0]
xmlpath = os.path.join(prefix, 'xml', name);

try:
    db = dbapi.connect(host   = dbinfo['host'],
                       user   = dbinfo['username'],
                       passwd = dbinfo['password'],
                       db     = dbinfo['database'])
except dbapi.Error, e:
    print "There was an error connecting to the database: %s" % e
    sys.exit()

latest = int(file(os.path.join(xmlpath, 'latest').read().strip())
commits = {}

print "Parsing SVN XML log files..."
count = 0

# We step backwards through revisions adding the latest first.
for filename in glob.glob(os.path.join(xmlpath, '*.xml')):

    try:
        doc = minidom.parse(filename)
    except IOError:
        print "Error reading SVN XML log file '%s', please check file permissions." % filename
        sys.exit()
    except:
        print "Error parsing SVN XML log file '%s', please ensure it is valid XML." % filename
        sys.exit()

    for xmllog in doc.getElementsByTagName('logentry'):
        
        logentry = {}
        revision = -1

        try:
            logentry['revision'] = int(xmllog.attributes['revision'].value)
            revision = logentry['revision']
            logentry['date']     = xmllog.getElementsByTagName('date')[0].firstChild.data
            paths = []
            for xmlpath in xmllog.getElementsByTagName('path'):
                path = {}
                path['action'] = xmlpath.attributes['action'].value
                path['path']   = xmlpath.firstChild.data
                try:
                    path['copyfrom_path']     = xmlpath.attributes['copyfrom-path'].value
                    path['copyfrom_revision'] = xmlpath.attributes['copyfrom-rev'].value
                except:
                    path['copyfrom_path']     = None
                    path['copyfrom_revision'] = None
                paths.append(path)
            logentry['paths']    = paths
            logentry['message']  = xmllog.getElementsByTagName('msg')[0].firstChild.data
        except IndexError:
            if revision is not -1:
                print 'Failed parsing XML for revision %d in "%s"!' % (revision, filename)
            else:
                print 'Failed parsing unknown XML logentry in file "%s"!' % filename
            continue

        try:
            # Apparently, author is optional and happens with repositories
            # converted from CVS with cvs2svn.
            logentry['author']   = xmllog.getElementsByTagName('author')[0].firstChild.data
        except:
            logentry['author']   = '' # As it's optional, we'll still add the revision.
        
        commits[logentry['revision']] = logentry
        
        count += 1
        if count % status_update_count is 0:
            print "Revisions Parsed: %s" % count

    os.remove(filename)

# At this point, we should have all log info contained in 'commits'.

print "Adding logs to the database..."
count = 0

cursor = db.cursor()

# This is only helpful while working on this script.
#cursor.execute('TRUNCATE TABLE commits')
#cursor.execute('TRUNCATE TABLE changes')
#db.commit()

date_pattern = re.compile(r'^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\.\d{6}Z$')

for commit in commits.values():

    d = [int(value) for value in date_pattern.search(commit['date']).groups()]
    commit_date = datetime.datetime(d[0], d[1], d[2], d[3], d[4], d[5], tzinfo=None)

    try:
        cursor.execute('INSERT INTO commits (revision, author, date, message) VALUES (%s, %s, %s, %s)',
                       (commit['revision'], commit['author'], commit_date, commit['message']))
    except dbapi.DatabaseError, e:
        print "Error adding commit to database: %s" % e
        db.rollback()
        continue

    # If the above was added successfully, we'll add the changed paths now.
    try:
        for change in commit['paths']:
            cursor.execute('INSERT INTO changes (revision, action, path, copy_path, copy_revision) VALUES (%s, %s, %s, %s, %s)',
                           (commit['revision'], change['action'], change['path'], change['copyfrom_path'], change['copyfrom_revision']))
    except dbapi.DatabaseError, e:
        print "Error adding change information (for revision %s) to the database: %s" % (commit['revision'], e)
        db.rollback()
        continue

    db.commit()

    count += 1
    if count % status_update_count is 0:
        print "Revisions Added to Database: %s" % count

# Update the total commit counts and active status for each author (adding new authors to the table if needed).
cursor.execute("""INSERT INTO authors (username, commits, active)
                  SELECT author, COUNT(author), SUBDATE(NOW(), INTERVAL 1 YEAR) < MAX(date)
                  FROM commits GROUP BY author
                  ON DUPLICATE KEY UPDATE commits = VALUES(commits), active = VALUES(active)""")
db.commit()

