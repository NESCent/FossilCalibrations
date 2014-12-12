-- Use local files to update our tables for NCBI taxonomy
-- 
-- NOTE that the filesystem paths below should be modified to reflect the current system!

USE FossilCalibration;

UPDATE site_status SET 
  NCBI_status = 'Updating now'; 

TRUNCATE TABLE NCBI_nodes;
TRUNCATE TABLE NCBI_names;

LOAD DATA INFILE '/var/www/html/db-dump/ncbi-import/nodes.dmp' INTO TABLE NCBI_nodes 
  FIELDS TERMINATED BY '\t|\t' 
  LINES TERMINATED BY '\t|\n' 
  (taxonid, parenttaxonid,rank,embl_code,division_id,inherited_div_flag,genetic_code_id,inherited_gc_flag, mitochondrial_genetic_codeid,inherited_mgc_flag,genBank_hidden_flag,hidden_subtree_root_flag,comments);

LOAD DATA INFILE '/var/www/html/db-dump/ncbi-import/names.dmp' INTO TABLE NCBI_names 
  FIELDS TERMINATED BY '\t|\t' 
  LINES TERMINATED BY '\t|\n' 
  (taxonid, name, uniquename, class);

SELECT 'NCBI update complete! Regenerate all FCDB data, then compare pinned nodes.' AS '';

UPDATE site_status SET 
  NCBI_status = 'Up to date',
  last_NCBI_update = CURRENT_TIMESTAMP,
  needs_autocomplete_build = true,
  needs_multitree_build = true,
  autocomplete_status = 'Needs update',
  multitree_status = 'Needs update',
  cladeCalibration_status = 'Needs update';
