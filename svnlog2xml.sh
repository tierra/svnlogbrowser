#!/bin/bash
# vim: ai ts=4 sts=4 et sw=4

# SVN Changelog: Log to XML Script (Step 1 of 2)
# Authors: Tim Kosse, Bryan Petty

# Name to use for this repository
NAME="svnLogBrowser"
# URL to the SVN repository (or subfolder)
URL="http://svn.svnlogbrowser.org/"

# Number of revision logs to grab at a time.
# This can be helpful for debugging or for keeping track
# of the progress on large remote repositories.
INCREMENT=200


######## CONFIGURATION ENDS HERE ########


echo "$NAME: $URL"

# Check that the SVN URL is valid and working.
if ! svn --non-interactive info "$URL" 1> /dev/null; then
	echo "Error retrieving information from the SVN repository."
    exit
fi

# This keeps track of the current revision we're pulling.
current=0
# This is the latest revision that was commited to the SVN repository.
latest=`svn --non-interactive info "$URL" | grep "^Revision:" | sed "s/.* //"`

# Uncomment this for testing these scripts with small numbers of revisions.
#latest=1500

mkdir -p "xml/$NAME"

# If we've made a run before already, we'll continue from this revision.
if [ -f "xml/$NAME/latest" ]; then
    prior_update=`cat "xml/$NAME/latest"`
    echo "Last revision retrieved: $prior_update"
    current=$((prior_update + 1))
fi

echo "Latest revision in SVN:  $latest"

if [ "$current" -gt "$latest" ]; then
    echo "No updates since last check, we're done here."; exit
fi

# Start looping through increment ranges of revision logs.
while [ "$current" -le "$latest" ]; do
    # Check for the control file so we can end early (and clean) if needed.
    if [ -f "stop" ]; then
        rm stop
        exit 1
    fi
    filename=`printf "%08d" $current`
    path="xml/$NAME/$filename.xml"
    if ! [ -f "$path" ]; then
        upperbound=$((current - 1 + INCREMENT))
        # Don't request more than are available
        if [ "$upperbound" -gt "$latest" ]; then
            upperbound="$latest"
        fi
        echo "Fetching revisions $current to $upperbound."
        if ! svn --non-interactive --verbose --xml log -r $upperbound:$current "$URL" > "$path"; then
            echo "Failed to retrieve logs from revision $current to $upperbound!"
            echo "Testing each revision individually for debug:"
            rm "$path"
            j=$current
            while [ $j -le $upperbound ]; do
                svn --verbose --xml log -r $j "$URL" > /dev/null
                j=$((j + 1))
            done
            echo "Done testing."
            exit
        fi
    fi
    current=$((current + INCREMENT))
done

`echo "$latest" > "xml/$NAME/latest"`

# If both steps can be done from the same machine, we might as well call the
# second step here.

python svnxml2db.py

