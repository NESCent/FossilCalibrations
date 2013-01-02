/*
 * Add tables for responsive "auto-complete" widgets in client UI:
    AC_names_nodes -- NCBI taxa (and other sources) and FDC-contributed nodes?
    AC_names_taxa  -- just NCBI nodes (and other sources)?
    AC_names_extant_species -- filtered view of AC_names_nodes (or AC_names_taxa), only leaf nodes?
    AC_names_clades -- filtered view of AC_names_taxa, all but leaf nodes?
    AC_names_searchable  -- all above, plus names of authors, publications, journals, collections, fossils

   These can probably be quickly retrieved from the existing tables.
    ? AC_names_authors
    ? AC_names_publications
    ? AC_names_journals
    ? AC_names_collections
    ? AC_names_fossils
 */

DROP TABLE IF EXISTS AC_names_nodes;
CREATE TABLE AC_names_nodes (
  name VARCHAR(255) NOT NULL,		-- eg, 'Homo sapiens'
  description VARCHAR(255) NOT NULL,	-- type and source, eg, 'scientific name, Linnaeus'
  is_taxon tinyint(1) unsigned NOT NULL,
  is_extant_species tinyint(1) unsigned NOT NULL,
  -- status? published, draft, deprecated...

  PRIMARY KEY (name), KEY (is_extant_species)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- create a staging table with same structure
DROP TABLE IF EXISTS tmp_AC_names_nodes;
CREATE TABLE tmp_AC_names_nodes AS SELECT * FROM AC_names_nodes;

-- Let's not create more huge tables just for taxa and extant species
CREATE OR REPLACE VIEW AC_names_taxa AS
  (SELECT * FROM AC_names_nodes WHERE (is_taxon = 1));

CREATE OR REPLACE VIEW AC_names_extant_species AS
  (SELECT * FROM AC_names_nodes WHERE (is_taxon = 1) AND (is_extant_species = 1));

CREATE OR REPLACE VIEW AC_names_clades AS
  (SELECT * FROM AC_names_nodes WHERE (is_extant_species = 0));
  -- does this make sense? or is an extant species a clade with one member?


-- create matching views for staging table
CREATE OR REPLACE VIEW tmp_AC_names_taxa AS
  (SELECT * FROM tmp_AC_names_nodes WHERE (is_taxon = 1));

CREATE OR REPLACE VIEW tmp_AC_names_extant_species AS
  (SELECT * FROM tmp_AC_names_nodes WHERE (is_taxon = 1) AND (is_extant_species = 1));

CREATE OR REPLACE VIEW tmp_AC_names_clades AS
  (SELECT * FROM tmp_AC_names_nodes WHERE (is_extant_species = 0));


DROP TABLE IF EXISTS AC_names_searchable;
CREATE TABLE AC_names_searchable (
  name VARCHAR(255) NOT NULL,		-- eg, 'Smithers'
  description VARCHAR(255) NOT NULL,	-- type and source, eg, 'author, Linnaeus'

  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- create a staging table with same structure
DROP TABLE IF EXISTS tmp_AC_names_searchable;
CREATE TABLE tmp_AC_names_searchable AS SELECT * FROM AC_names_searchable;

/*
 * Add tables for names and nodes submitted with calibrations to Fossil Calibration Database. These should
 * mimic NCBI_* tables, where each name record has a class and ONE associated node.
    FCD_names
    FCD_nodes
 * Strip column names to just the ones we need, and rename from 'taxon' to
 * 'node' in ID columns for clarity.
 */

-- Would we ever want to assign a taxonomic 'rank' to these nodes?
DROP TABLE IF EXISTS FCD_nodes;
CREATE TABLE FCD_nodes (
  node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  parent_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  -- rank VARCHAR(50) DEFAULT NULL,
  comments VARCHAR(255) DEFAULT NULL,

  PRIMARY KEY (node_id), KEY parent_node_id (parent_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Can/should someone "own" a name, even if others use it?
-- Should calibration/cladogram refer to specific names as well as nodes?
DROP TABLE IF EXISTS FCD_names;
CREATE TABLE FCD_names (
  node_id MEDIUMINT(11) UNSIGNED NOT NULL, 
  name VARCHAR(200) NOT NULL, 
  uniquename VARCHAR(100) DEFAULT NULL,
  class VARCHAR(50) NOT NULL DEFAULT '',

  KEY node_id (node_id), KEY type (class), KEY name (name)
) ENGINE=INNODB CHARSET=UTF8;


/*
 * Add a table for all "node pinning" decisions that define overlap between
 * phylogenetic trees.
    pinned_nodes
 */

DROP TABLE IF EXISTS pinned_nodes;
CREATE TABLE pinned_nodes (
  target_tree VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD-135'
  target_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  pinned_tree VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD-135' (or just 'FCD'?)
  pinned_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  calibration_id INT(11) NOT NULL, 	-- the calibration that pins this node
  comments VARCHAR(255) DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*
 * The pinned_nodes table in turn generates a bare-bones table tracking "node
 * identity". This maps single generic nodes in all_nodes multitree to one OR
 * MORE nodes and the source for each.)
    node_identity
 */

DROP TABLE IF EXISTS node_identity;
CREATE TABLE node_identity (
  all_nodes_id MEDIUMINT(8) UNSIGNED NOT NULL,
  source_tree VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD-135' (or just 'FCD'?)
  source_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  comments VARCHAR(255) DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*
 * Add a single "multitree" for fast searching and traversal against all trees
 * in the system.
    all_nodes
 */

DROP TABLE IF EXISTS all_nodes;
CREATE TABLE all_nodes (
  node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  parent_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  -- define the source of this node, and its original ID
  node_source VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD'
  source_node_id MEDIUMINT(8) UNSIGNED NOT NULL,

  PRIMARY KEY (node_id), KEY parent_node_id (parent_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- create a staging table with same structure
DROP TABLE IF EXISTS tmp_all_nodes;
CREATE TABLE tmp_all_nodes AS SELECT * FROM all_nodes;


/* 
 * Create stored procedures to populate the tables above. These should be
 * called only occasionally, prior to publication of a wave of new
 * (submitted) calibrations.
 *
 * NOTE that in most cases, we'll update into tmp_* versions of each table above.
 * This gives a special view for admin users (on demand) and allows for zero
 * down-time if we're replacing the real tables (since we can simply
 * DROP+RENAME in the final step if we're serious).
 */


/*
 * refreshMultitree( testOrFinal )
 * 	where testOrFinal = 'TEST' | 'FINAL'
 */

DROP PROCEDURE IF EXISTS refreshMultitree;

DELIMITER #

CREATE PROCEDURE refreshMultitree (IN testOrFinal VARCHAR(20))
BEGIN

-- DECLARE any local vars here

--
-- clear staging table, then rebuild
--
TRUNCATE TABLE tmp_all_nodes;

-- add nodes from NCBI taxonomy
INSERT INTO tmp_all_nodes (node_id, parent_node_id, node_source, source_node_id)
SELECT
 CONCAT('NCBI-', taxonid), 	-- OR two-part PK?
 CONCAT('NCBI-', parenttaxonid), -- allow "foreign" parent_source?
 'NCBI',
 taxonid
FROM
 NCBI_nodes;

-- TODO: add nodes from PBDB, other reference trees?

-- TODO: add nodes from FCD calibrations and account for pinning
INSERT INTO tmp_all_nodes (node_id, parent_node_id, node_source, source_node_id)
SELECT
 CONCAT('FCD-', node_id), 	-- OR two-part PK?
 CONCAT('FCD-', parent_node_id), -- allow "foreign" parent_source?
 'FCD',
 node_id
FROM
 FCD_nodes;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  TRUNCATE TABLE all_nodes;
  INSERT INTO all_nodes SELECT * FROM tmp_all_nodes;
END IF;

END #

DELIMITER ;














/*
 * refreshAutoCompleteTables( testOrFinal )
 * 	where testOrFinal = 'TEST' | 'FINAL'
 */

DROP PROCEDURE IF EXISTS refreshAutoCompleteTables;

DELIMITER #

CREATE PROCEDURE refreshAutoCompleteTables (IN testOrFinal VARCHAR(20))
BEGIN

-- DECLARE any local vars here

--
-- clear staging tables for node names, then rebuild
--
TRUNCATE TABLE tmp_AC_names_nodes;

INSERT INTO tmp_AC_names_nodes (name, description, is_taxon, is_extant_species)
SELECT
 names.name, -- uniquename?
 CONCAT(names.class,", NCBI taxon"),
 1,  -- all NCBI names(?) are taxa
 IF(nodes.rank = 'species', 1, 0)     -- OR -- IF((SELECT COUNT(*) FROM NCBI_nodes WHERE parenttaxonid = names.taxonid) = 0, 1, 0)
FROM
 NCBI_names AS names
LEFT OUTER JOIN NCBI_nodes AS nodes ON nodes.taxonid = names.taxonid;

INSERT INTO tmp_AC_names_nodes (name, description, is_taxon, is_extant_species)
SELECT
 names.name, -- uniquename?
 CONCAT(names.class,", FCD node"),
 1,  -- all NCBI names(?) are taxa
 IF((SELECT COUNT(*) FROM FCD_nodes WHERE parent_node_id = names.node_id) = 0, 1, 0)
FROM
 FCD_names AS names
LEFT OUTER JOIN FCD_nodes AS nodes ON nodes.node_id = names.node_id
LEFT OUTER JOIN pinned_nodes ON pinned_nodes.pinned_node_id = names.node_id;
-- also filter on publication status? JOIN calibration, publication

--
-- clear staging tables for all searchable names, then rebuild
-- 
TRUNCATE TABLE tmp_AC_names_searchable;

-- add node names (copied from above)
INSERT INTO tmp_AC_names_searchable (name, description)
SELECT
 name, -- uniquename?
 description
FROM
 tmp_AC_names_nodes;

-- add author names?
-- add journal names
-- add publications (eg, Smith 2001)
INSERT INTO tmp_AC_names_searchable (name, description)
SELECT
 ShortName,
 FullReference
FROM
 publications;

-- add collection names
INSERT INTO tmp_AC_names_searchable (name, description)
SELECT
 CollectionName,
 'Fossil collection'
FROM
 collections;

-- add fossil names
INSERT INTO tmp_AC_names_searchable (name, description)
SELECT
 Species, 	-- shouldn't these become node names?
 'Fossil species'
FROM
 fossils;

-- add location names
INSERT INTO tmp_AC_names_searchable (name, description)
SELECT
 LocalityName, 	-- shouldn't these become node names?
 CONCAT('Locality; ', Stratum)
FROM
 localities;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  TRUNCATE TABLE AC_names_nodes;
  INSERT INTO AC_names_nodes SELECT * FROM tmp_AC_names_nodes;

  TRUNCATE TABLE AC_names_searchable;
  INSERT INTO AC_names_searchable SELECT * FROM tmp_AC_names_searchable;
END IF;

END #

DELIMITER ;

