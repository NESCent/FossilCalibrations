## Updating FCDB to the latest NCBI taxonomy

Due to the need for review and cleanup, this feature is not currently functional
on the web-based **Admin Dashboard**; clicking its button redirects here.

**This is currently a manual process intended for a skilled sysadmin.**
All steps below assume that the operator has shell access to the FCDB webserver
and 'root' credentials (or equivalent) in MySQL.


### Install stored procedures (once only)

1. Install an improved MySQL stored procedure **refreshCalibrationsByClade** that
   reports (then skips over) any calibration whose tree is pinned to a deleted
   NCBI node. From the MySQL console:
   ```sql
   mysql> SOURCE refreshCalibrationsByClade.sql
   ```
   ... or from the command shell:
   ```sh
   cd ncbi-update
   mysql -uroot -p < refreshCalibrationsByClade.sql
   ```
   These commands are equivalent. Most examples below will use the command-shell
   form.
   
2. Install new MySQL stored procedure **stashPinnedLineages** that records the
   NCBI lineage of all calibration trees in the system.
   ```sh
   mysql -uroot -p < stashPinnedLineages.sql
   ```

### Update NCBI taxonomy and adjust calibrations

_The instructions below should be followed **every time** you update to the latest NCBI taxonomy._

1. Stash current NCBI lineages for later comparison
   ```sh
   cd ncbi-update
   mysql -uroot -p < stash-before-NCBI-update.sql
   ```

2. Dump (backup) the current FCDB database, using the current date for the
   filename. This is strictly a precaution, so that you can revert the entire
   database if the upgrade goes wrong somehow.
   ```sh
   cd db-dump
   mysqldump --user=root --password   \
     --routines --quick --single-transaction   \
     --default-character-set=utf8     \
     --databases FossilCalibration    \
     -r FossilCalibration-2014-12-10.sql
   ```

3. Fetch the latest NCBI archive files from NIH (this is actually pretty
   quick!) into a clean directory.
   ```sh
   mkdir ncbi-import
   cd ncbi-import
   wget ftp://ftp.ncbi.nih.gov/pub/taxonomy/taxdump.tar.gz
   wget ftp://ftp.ncbi.nih.gov/pub/taxonomy/gi_taxid_nucl.dmp.gz
   ```
   Unzip these "dump" files in place. NOTE that the taxdump archive is small
   and quick, but the taxid_nucl data takes a minute to make one huge file.
   ```sh
   gunzip -c taxdump.tar.gz | tar xf - 
   gunzip < gi_taxid_nucl.dmp.gz > gi_taxid_nucl.dmp 
   ```

4. Pull these dump files into MySQL, replacing the current `NCBI_nodes` and
   `NCBI_names` tables. **NOTE that you should first modify this script to
   match the absolute filesystem paths used above!**
   ```sh
   cd ncbi-update
   mysql -uroot -p < update-NCBI-from-dump.sql
   ```
   If the script reports errors finding the files, please check the paths in
   `update-NCBI-from-dump.sql`. Otherwise, wait several minuts for the script
   to finish.

5. Refresh FCDB tables and check for errors. Begin by using the FCDB website
   Admin Dashboard to drive some of the usual rebuilding tasks:

    - **SKIP** Rebuild all calibration trees
    - **DO** Update searchable multitree
    - **DO** Update auto-complete lists
    - **DO** Update calibrations-by-clade table

   This last step may complete successfully within a few minutes. If so,
   congratulations! You can skip to the next numbered step below. **If the
   'Update calibrations-by-clade table' feature fails**, run this procedure
   again from a MySQL console to see why. Typically this is due to pinned NCBI
   nodes that no longer exists in the latest taxonomy, as shown here:
   ```sql
   mysql> CALL refreshCalibrationsByClade('FINAL');
   PLEASE REFRESH THE NODE LOCATION for calibration 123
   PLEASE REFRESH THE NODE LOCATION for calibration 98
   ```
   This can be resolved by resetting the node location in the affected
   calibrations, using the normal web editor for calibrations. Simply retype
   the taxon names in the auto-complete widgets on Side A and Side B, and note
   when the IDs change in each case. Typically one or more of these will move
   to a new node in NCBI (as a synonum), or you might need to choose a new
   taxon name to locate the calibrated node.

   Do this for each calibration shown in the MySQL console message above, then
   re-try the call to refreshCalibrationsByClade until it completes normally.

   **If this test fails even after revising the calibrated node location,**
   return to the Admin Dashboard and rebuild the multitree and autocomplete
   lists.  This may reveal new choices in the node-location UI that will solve
   the problem.

6. Check the Admin Dashboard to see if all indicators are green (up to date).
   If not, re-run these tasks (generally from top to bottom) until everything
   is green. Now we should have data integrity and normal behavior in the
   Search and Browse tools.
   
7. Compare the old and new NCBI lineages to find which calibrations are in changing areas 
   of the NCBI taxonomy. This is done by from the command line:
   ```sh
   cd ncbi-update
   mysql -uroot -p < compare-after-NCBI-update.sql
   ```
   Watch the output of this script, which will end with a list of affected
   calibration IDs. If there are changes close to the root of the NCBI
   taxonomy, this might well include most of the calibrations in the system!
   ```sh
    +-----------------------------------------------------------------+
    |                                                                 |
    +-----------------------------------------------------------------+
    | REVIEW THE CALIBRATIONS BELOW based on changes to NCBI lineage: |
    +-----------------------------------------------------------------+
    1 row in set (15.40 sec)

    +----------------+
    | calibration_id |
    +----------------+
    |             99 |
    |            103 |
    |            104 |
   ..... ET CETERA .....
    |            282 |
    |            122 |
    +----------------+
    74 rows in set (15.40 sec)
   ```

   _NOTE that this list may include IDs from calibrations that have been
   deleted from the system. In this case, please disregard the extra IDs._

8. Review the altered calibrations for correct placement in the NCBI taxonomy.
   This is probably best done in the Browse view of FCDB, but you might also
   see changes in the Search tool when filtering by clade or tip taxa.

   Most of the calibrations should be in sensible places. If you find problems
   with the placement of a calibrated node, modify its location in the
   calibration editor as described above. Then **remember to rebuild FCDB
   tables** in the Admin Dashboard before proofing the results!


