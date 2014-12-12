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

    - **SKIP** SKIP Rebuild all calibration trees
    - **DO** Update searchable multitree
    - **DO** Update auto-complete lists
    - **DO** Update calibrations-by-clade table

   This last step may complete successfully within a few minutes. If so,
   congratulations! Most likely it will fail to complete, which means that a
   calibrated node was pinned to a node in the NCBI taxonomy that has been
   deleted in the latest version.
 
   _TODO: manual test for pinned + deleted nodes._

6. Review altered calibrations for correct placement in NCBI

