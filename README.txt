Sourceforge Tracker Export Utility
-------
sftrackerexport is a small utility to retrieve tracker items (bugs, feature 
requests or patches) from a Sourceforge project and prepare them to be imported
into The Bug Genie (thebuggenie.com). It retrieves the data from Sourceforge's 
Allura API, imports new users into The Bug Genie, and writes CSV files that can
be imported through The Bug Genie's web-based import window.

sftrackerexport was written to work with The Bug Genie 3.2.4 and hasn't been
extensively tested. I wrote it for a single use, without much concern about
optimization, but I am sharing it in case it can shorten the work load for 
others who might want to move their data. I used it to successfuly port more
than 5,000 issues for UFO: Alien Invasion, an open-source tactical strategy
game (ufoai.org).

*FILES*

/config.php
   Where you will set all the appropriate ids for your bug genie projects,
   statuses, categories, milestones, priorities, user groups, etc. You'll
   also configure the API calls that are made.
   
/sftrackerexport.php
   Retrieves the data, writes the CSV files and adds users to the db.
   
/_cSfQuery.php
   The class that handles API queries, errors, formatting, etc.
   
/tools/get_sftracker_parameters.php
   A small tool to retrieve a list of all parameters used by your Sourceforge
   tracker, such as priority, labels, milestones, etc.

/tools/join_csv_files.php
   If you run the export script in small batches (recommended), you'll end up
   with a lot of CSV files to import. This utility will just combine them into
   fewer files for you.

/tools/view_bad_row.php
   If bug genie detects any errors in your CSV file, it will tell you the row
   number. This utility will help you identify and view specific rows to find
   errors.

*HOW TO USE*

1. Run tools/get_sftracker_parameters.php

In order to successfully import your tracker items to The Bug Genie, you'll
need to define every item parameter you want to import from Sourceforge: 
labels, milestones, priorities, assigned users, etc. To make this easy, you
should run tools/get_sftracker_parameters.php, which will print out arrays
of each of these things so you can add them to The Bug Genie.

2. Configure parameter links in config.php

Once you've added the parameters to The Bug Genie, you'll need to go into
the database to find out each parameter's id. You'll then need to fill out
all the arrays in config.php, pointing each Sourceforge parameter to the
appropriate id in The Bug Genie.

Note: this utility puts Sourceforge "labels" into The Bug Genie "categories",
which means multiple labels are lost. Only the first is kept.

3. Configure $baseurl in _cSfQuery.php

Set the $baseurl to match your Sourceforge project's REST API page.

4. Run sftrackerexport.php

It should create CSV files in 
/export/<tracker-type>_<batch_page>-<batch_page-number>.csv and a log file in 
/log/<datetime> which will list errors. 

*KNOWN ISSUES*

1. The Bug Genie does not have support for importing comments. To get around
this, sftrackerexport retrieves the comment data and places it into the
description for each issue.

2. The Bug Genie 3.2.4 does not support setting an issue_no or posted date 
when importing, so by default you will not be able to keep the same bug ids or
know the date an issue was originally posted. However, I got around this by 
extending The Bug Genie's import code to support these fields. You can see how
to do this in this commit:

https://github.com/NateWr/thebuggenie/commit/2d2d2f7dfc822955b8d3fb059b29514b78384408

When you do this, you'll also want to uncomment the lines in
sftrackerexport.php where these fields are added to the CSV file. In The Bug
Genie, your bugs, feature requests and patches will share a common database,
and must have unique issue_no values, so you can only port the ids for one
tracker type.

3. I found that out of 5,000 tickets, about 100 comments simply couldn't be 
retrieved. Sourceforge's REST API would not return them, for unknown reasons.
If this happens, the program notes this in the description so that you can
know to check the archived Sourceforge tracker for the missing comment.

4. Sometimes the REST API call will just fail, but its not a permanent fail.
Running small batches will make it easy to re-run just the batch with an error.
Set $batch_page in config.php to run just one batch again.

*CONTACTING ME*

You can reach me at the UFO: Alien Invasion forums under the username "H-Hour",
ufoai.org/forum