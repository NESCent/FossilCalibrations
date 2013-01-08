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
  is_public_name TINYINT(1) NOT NULL,   -- true (1) if this name is used in ANY public tree

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
  is_public_name TINYINT(1) NOT NULL,   -- true (1) if this name is used in ANY public tree

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
    FCD_trees
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
  tree_id MEDIUMINT(8) UNSIGNED NOT NULL,  -- FK to FCD_trees
  -- is_public_node TINYINT(1) NOT NULL,

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

-- Also a lightweight table for FCD trees
DROP TABLE IF EXISTS FCD_trees;
CREATE TABLE FCD_trees (
  tree_id MEDIUMINT(8) UNSIGNED NOT NULL,
  root_node_id MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  calibration_id MEDIUMINT(8) UNSIGNED NOT NULL,
  comments VARCHAR(255) DEFAULT NULL,
  is_public_tree TINYINT(1) NOT NULL,

  PRIMARY KEY (tree_id), KEY calibration_id (calibration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


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
  comments VARCHAR(255) DEFAULT NULL,
  is_public_node TINYINT(1) NOT NULL    -- refers only to pinned node, not target!

) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*
 * The pinned_nodes table in turn generates a bare-bones table tracking "node
 * identity". This maps single generic nodes in the multitree to one OR MORE 
 * source nodes.
    node_identity
 */

DROP TABLE IF EXISTS node_identity;
CREATE TABLE node_identity (
  multitree_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  source_tree VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD-135' (or just 'FCD'?)
  source_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  comments VARCHAR(255) DEFAULT NULL,
  is_public_node TINYINT(1) NOT NULL,   -- TRUE if source node is public

  PRIMARY KEY (multitree_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- create a staging table with same structure
DROP TABLE IF EXISTS tmp_node_identity;
CREATE TABLE tmp_node_identity AS SELECT * FROM node_identity;


/*
 * Add a single "multitree" for fast searching and traversal against all trees
 * in the system. Each record here is a single parent-child path belonging to
 * one OR MORE source trees. If any of these trees are public, is_public_path
 * should be true (1).
    multitree
 */

DROP TABLE IF EXISTS multitree;
CREATE TABLE multitree (
  node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  parent_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  -- define the source of this node, and its original ID
-- node_source VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD'
-- source_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  -- NO, this 1:* relation is resolved using node_identity
  is_public_path TINYINT(1) NOT NULL,

  PRIMARY KEY (node_id), KEY parent_node_id (parent_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- create a staging table with same structure
DROP TABLE IF EXISTS tmp_multitree;
CREATE TABLE tmp_multitree AS SELECT * FROM multitree;


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
 * refreshNodeIdentity( testOrFinal )
 * 	where testOrFinal = 'TEST' | 'FINAL'
 */

DROP PROCEDURE IF EXISTS refreshNodeIdentity;

DELIMITER #

CREATE PROCEDURE refreshNodeIdentity (IN testOrFinal VARCHAR(20))
BEGIN

-- DECLARE any local vars here

/* all vars below are used when walking pinned_nodes */
-- temporary vars to hold found field values
DECLARE the_target_tree VARCHAR(20);
DECLARE the_target_node_id MEDIUMINT(8) UNSIGNED;
DECLARE the_pinned_tree VARCHAR(20);
DECLARE the_pinned_node_id MEDIUMINT(8) UNSIGNED;
-- DECLARE the_is_public_flag TINYINT(1) DEFAULT 0;
DECLARE the_matching_multitree_node_id MEDIUMINT(8) UNSIGNED;

-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- a cursor to fetch these fields
DECLARE pinned_node_cursor CURSOR FOR SELECT target_tree, target_node_id, pinned_tree, pinned_node_id FROM pinned_nodes;

DECLARE CONTINUE HANDLER FOR NOT FOUND 
  SET no_more_rows = TRUE;


--
-- clear staging table, then rebuild
--
TRUNCATE TABLE tmp_node_identity;

/*
TODO
. add auto-incrementing multitree_node_id OR find and re-use existing (pinned target) ID?
. START by adding NCBI nodes
. THEN all FCD trees and nodes
. THEN consolidate multitree_node_ids for all pinned nodes

v store return value from UUID() as what type?
	VARCHAR(32)  -- very slow to index, insert and retrieve
	BINARY(16)  -- MUCH faster and more compact; convert with UNHEX(REPLACE(UUID(),'-',''))
*/
-- use incrementing multitree node IDs, starting with 1
SET @nextID = 0;

--
-- add all nodes from NCBI taxonomy, with incrementing values for multitree_node_id
--   see http://stackoverflow.com/a/8678920
INSERT INTO tmp_node_identity (multitree_node_id, source_tree, source_node_id, comments, is_public_node)
SELECT
 @nextID := @nextID + 1,
 'NCBI',
 taxonid,
 'just another NCBI node',
 1     -- NCBI nodes are always public
FROM
 NCBI_nodes;

-- TODO: add nodes from PBDB, other reference trees?

--
-- add nodes from FCD trees, all with new IDs
--
INSERT INTO tmp_node_identity (multitree_node_id, source_tree, source_node_id, comments, is_public_node)
SELECT
 @nextID := @nextID + 1,
 'FCD',
 node_id,
 'just another FCD node',
 (SELECT MAX(is_public_tree) FROM FCD_trees   -- check for public|private tree? or reach waaay back to publication?
	WHERE tree_id = FCD_nodes.tree_id)
FROM
 FCD_nodes;

--
-- At this point, all nodes are present in node_identity. Walk the
-- pinned_nodes table, consolidating IDs among all pinned nodes. This will
-- result in duplicate values and "gaps" in column multitree_node_id.
-- 
-- TODO: boil this down to a single, complex SQL statement?
--

OPEN pinned_node_cursor;

  the_loop: LOOP
    FETCH pinned_node_cursor INTO the_target_tree, the_target_node_id, the_pinned_tree, the_pinned_node_id;

    IF no_more_rows THEN 
      CLOSE pinned_node_cursor;
      LEAVE the_loop;
    END IF;

    -- diagnostic display
    SELECT the_target_tree, the_target_node_id, the_pinned_tree, the_pinned_node_id;

    -- get the target node's multitree ID
    SET the_matching_multitree_node_id = (SELECT MIN(multitree_node_id)
      FROM tmp_node_identity 
      WHERE source_tree = the_target_tree AND source_node_id = the_target_node_id);

    -- diagnostic display
    SELECT the_matching_multitree_node_id;

    -- consolidate multitree_id for all pinned nodes
    -- (be careful to modify ALL matching nodes, even if 3+ are pinned)
    UPDATE tmp_node_identity
      SET multitree_node_id = the_matching_multitree_node_id, 
          -- only push public to TRUE, never to FALSE
          -- is_public_node = IF(the_is_public_flag = 1, 1, is_public_node),
          comment = CONCAT("PINNED! ", comment)
      WHERE source_tree = the_pinned_tree AND source_node_id = the_pinned_node_id;

  END LOOP;

CLOSE pinned_node_cursor;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  TRUNCATE TABLE node_identity;
  INSERT INTO node_identity SELECT * FROM tmp_node_identity;
END IF;

END #

DELIMITER ;


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
TRUNCATE TABLE tmp_multitree;

-- add nodes from NCBI taxonomy
INSERT INTO tmp_multitree (node_id, parent_node_id, node_source, source_node_id)
SELECT
 CONCAT('NCBI-', taxonid), 	-- OR two-part PK?
 CONCAT('NCBI-', parenttaxonid), -- allow "foreign" parent_source?
 'NCBI',
 taxonid
FROM
 NCBI_nodes;

-- TODO: add nodes from PBDB, other reference trees?

-- TODO: add nodes from FCD calibrations and account for pinning
INSERT INTO tmp_multitree (node_id, parent_node_id, node_source, source_node_id)
SELECT
 CONCAT('FCD-', node_id), 	-- OR two-part PK?
 CONCAT('FCD-', parent_node_id), -- allow "foreign" parent_source?
 'FCD',
 node_id
FROM
 FCD_nodes;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  TRUNCATE TABLE multitree;
  INSERT INTO multitree SELECT * FROM tmp_multitree;
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

