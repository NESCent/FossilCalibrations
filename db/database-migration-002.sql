/*
 * Create tables to hold NCBI node and identifiers
 *
 * This is based closely on notes from the MARTA project, found here:
 *   http://bergelson.uchicago.edu/Members/mhorton/taxonomydb.build 
 *
 */

CREATE TABLE NCBI_nodes (
  taxonid mediumint(8) unsigned NOT NULL,
  parenttaxonid mediumint(8) unsigned NOT NULL,
  rank varchar(50) default NULL,
  embl_code varchar(20) default NULL,
  division_id smallint(6) NOT NULL,
  inherited_div_flag tinyint(1) unsigned NOT NULL,
  genetic_code_id smallint(6) NOT NULL,
  inherited_gc_flag tinyint(1) unsigned NOT NULL,
  mitochondrial_genetic_codeid smallint(6) NOT NULL,
  inherited_mgc_flag tinyint(1) unsigned NOT NULL,
  genbank_hidden_flag tinyint(1) unsigned NOT NULL,
  hidden_subtree_root_flag tinyint(1) unsigned NOT NULL,
  comments varchar(255) default NULL,

  PRIMARY KEY  (taxonid), KEY parenttaxonid (parenttaxonid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


create table NCBI_names (
  taxonid MEDIUMINT(11) UNSIGNED NOT NULL, 
  name VARCHAR(200) NOT NULL, 
  uniquename VARCHAR(100) DEFAULT NULL,
  class VARCHAR(50) NOT NULL DEFAULT '',

  KEY taxonid (taxonid), KEY type (class), KEY name (name), KEY uniquename (uniquename)
) ENGINE=INNODB CHARSET=UTF8;



/*
 * Download and import NCBI node tree and identifiers into the new tables.
 * 
 * TODO: Download current NCBI archive files from 
 *   ftp://ftp.ncbi.nih.gov/pub/taxonomy/taxdump.tar.gz
 *   ftp://ftp.ncbi.nih.gov/pub/taxonomy/gi_taxid_nucl.dmp.gz
 * ... or equivalent archives in other formats.
 *
 * TODO: Save these files on the MySQL server, possibly in '/fossil-calibration/import'.
 *
 * TODO: Modify the filesystem paths below to match your server's layout.
 */

LOAD DATA INFILE '/opt/lampp/htdocs/fossil-calibration/import/nodes.dmp' INTO TABLE NCBI_nodes FIELDS TERMINATED BY '\t|\t' LINES TERMINATED BY '\t|\n' (taxonid, parenttaxonid,rank,embl_code,division_id,inherited_div_flag,genetic_code_id,inherited_gc_flag, mitochondrial_genetic_codeid,inherited_mgc_flag,genBank_hidden_flag,hidden_subtree_root_flag,comments);

LOAD DATA INFILE '/opt/lampp/htdocs/fossil-calibration/import/names.dmp' INTO TABLE NCBI_names FIELDS TERMINATED BY '\t|\t' LINES TERMINATED BY '\t|\n' (taxonid, name, uniquename, class);
