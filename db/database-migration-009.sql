/*
 * Adding a simle lookup table to track clades (NCBI taxa) and calibrations within each. This will 
 * dramatically speed up browsing and searching for calibrations by clade.
 */

/*
 * Build a simple xref table.
 */
DROP TABLE IF EXISTS calibrations_by_NCBI_clade;
CREATE TABLE calibrations_by_NCBI_clade (
  -- NOTE that this uses NCBI node IDs! vs. multitree IDs
  clade_root_NCBI_id MEDIUMINT(8) UNSIGNED NOT NULL,
  clade_root_multitree_id MEDIUMINT(8) NOT NULL,
  calibration_id INT(11) NOT NULL,
  is_direct_relationship TINYINT NOT NULL,  -- if false, it's in a descendant
  publication_status INT(11) DEFAULT NULL,

  KEY (clade_root_NCBI_id),
  KEY (clade_root_multitree_id)
) ENGINE=InnoDB;

/* 
 * Add new status flag and timestamp for this

ALTER TABLE site_status
    ADD COLUMN cladeCalibration_status VARCHAR(50) NOT NULL DEFAULT 'Up to date' AFTER `NCBI_status`;
ALTER TABLE site_status
    ADD COLUMN last_cladeCalibration_update TIMESTAMP NOT NULL AFTER `last_NCBI_update`;

 */


/*
 * refreshCalibrationsByClade( testOrFinal )
 * 	where testOrFinal = 'TEST' | 'FINAL'
 *
 * NOTE that this should always be run AFTER rebuilding the multitree, since we'll use those
 * fragile IDs here. (If we do these out of order, we'll have stale/funky IDs in this table.)
 */

DROP PROCEDURE IF EXISTS refreshCalibrationsByClade;

DELIMITER #

CREATE PROCEDURE refreshCalibrationsByClade (IN testOrFinal VARCHAR(20))
BEGIN

-- DECLARE any local vars here
DECLARE the_ncbi_id MEDIUMINT(8) UNSIGNED;
DECLARE the_multitree_id MEDIUMINT(8);
DECLARE the_calibration_id INT(11);
DECLARE the_publication_status INT(11);

-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- a cursor to fetch these fields
DECLARE direct_relationship_cursor CURSOR FOR 
  SELECT clade_root_NCBI_id, clade_root_multitree_id, calibration_id, publication_status 
  FROM tmp_calibrations_by_NCBI_clade WHERE is_direct_relationship = 1;

DECLARE CONTINUE HANDLER FOR NOT FOUND 
  SET no_more_rows = TRUE;


UPDATE site_status SET 
  cladeCalibration_status = 'Updating now';

--
-- clear staging tables for node names, then rebuild
--
DROP TEMPORARY TABLE IF EXISTS tmp_ancestors;
DROP TABLE IF EXISTS tmp_calibrations_by_NCBI_clade;
CREATE TABLE tmp_calibrations_by_NCBI_clade LIKE calibrations_by_NCBI_clade;

-- initial records will be on those clades that are *directly* related to each calibration
INSERT INTO tmp_calibrations_by_NCBI_clade (clade_root_NCBI_id, clade_root_multitree_id, calibration_id, is_direct_relationship, publication_status)
SELECT
  pinned_nodes.target_node_id,  -- NCBI ID for a node that is identical to FCD nodes in FCD-tree for a given calibration
  node_identity.multitree_node_id,  -- its corresponding multitree ID
  c.CalibrationID,
  1,  -- these are DIRECT relationships
  c.PublicationStatus
FROM
  calibrations AS c
JOIN FCD_trees ON FCD_trees.calibration_id = c.CalibrationID
JOIN FCD_nodes ON FCD_nodes.parent_node_id = FCD_trees.root_node_id
JOIN pinned_nodes ON pinned_nodes.target_tree = 'NCBI' AND pinned_nodes.pinned_node_id = FCD_nodes.node_id
JOIN node_identity ON node_identity.source_tree = 'NCBI' AND node_identity.source_node_id = pinned_nodes.target_node_id;

-- for each of the calibration/clade pairs above, add *indirect* relationships with all ancestor clades
-- this will give us tallies to show in a compact tree format
OPEN direct_relationship_cursor;

  the_loop: LOOP
    FETCH direct_relationship_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, the_publication_status;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    -- reckon its depth by counting its (source-tree) ancestors, and save to scratch hints
    -- (currently the source tree is always 'NCBI', since all taxa are chosen from there)
    CALL getAllAncestors( the_multitree_id, "v_ancestors", 'NCBI' );

    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_ancestors ENGINE=memory SELECT * FROM v_ancestors;
    /* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */

    TRUNCATE TABLE tmp_ancestors;
    -- copy all the ancestor IDs that are *not* already linked to this calibration
    INSERT INTO tmp_ancestors SELECT * FROM v_ancestors
      WHERE node_id NOT IN (SELECT clade_root_multitree_id FROM tmp_calibrations_by_NCBI_clade 
			    WHERE calibration_id = the_calibration_id);

  IF EXISTS (SELECT 1 FROM tmp_ancestors) THEN

    -- first pass, to gather indirect relationships on pinned nodes
    INSERT INTO tmp_calibrations_by_NCBI_clade (clade_root_NCBI_id, clade_root_multitree_id, calibration_id, is_direct_relationship, publication_status)
    SELECT
      node_identity.source_node_id,  -- NCBI ID for a node that is identical to FCD nodes in FCD-tree for a given calibration
      node_identity.multitree_node_id,  -- its corresponding multitree ID
      the_calibration_id,
      0,  -- these are INDIRECT relationships
      the_publication_status
    FROM
      node_identity 
    WHERE node_identity.source_tree = 'NCBI' 
      AND node_identity.multitree_node_id IN (SELECT node_id FROM tmp_ancestors) 
      AND node_identity.multitree_node_id != the_multitree_id;  -- DON'T repeat the direct node here!

    -- second pass, to gather indirect relationships on UN-pinned nodes
    INSERT INTO tmp_calibrations_by_NCBI_clade (clade_root_NCBI_id, clade_root_multitree_id, calibration_id, is_direct_relationship, publication_status)
    SELECT
      multitree.node_id,  -- NCBI ID for an un-pinned NCBI node
      multitree.node_id,  -- its corresponding multitree ID (unchanged for un-pinned nodes!)
      the_calibration_id,
      0,  -- these are INDIRECT relationships
      the_publication_status
    FROM
      multitree 
    WHERE multitree.node_id IN (SELECT node_id FROM tmp_ancestors) 
      AND multitree.node_id != the_multitree_id;  -- DON'T repeat the direct node here!

  END IF; -- if there are new ancestors, add them

    SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
  END LOOP;

CLOSE direct_relationship_cursor;
SET no_more_rows = FALSE;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  -- TRUNCATE TABLE AC_names_nodes;
  -- INSERT INTO AC_names_nodes SELECT * FROM tmp_AC_names_nodes;
  RENAME TABLE calibrations_by_NCBI_clade TO doomed, tmp_calibrations_by_NCBI_clade TO calibrations_by_NCBI_clade;
  DROP TABLE doomed;
END IF;

UPDATE site_status SET
  cladeCalibration_status = 'Up to date',
  last_cladeCalibration_update = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_ancestors;

END #

DELIMITER ;

