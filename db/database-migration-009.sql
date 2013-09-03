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
 * Add a table that lists some NCBI nodes as familiar "landmark" taxa, so that they
 * always appear in the Browsing UI.
 */
DROP TABLE IF EXISTS NCBI_browsing_landmarks;
CREATE TABLE NCBI_browsing_landmarks (
  node_NCBI_id MEDIUMINT(8) UNSIGNED
);
INSERT INTO NCBI_browsing_landmarks ( node_NCBI_id ) VALUES 
  -- ADD MORE HERE
  (8782),  -- Aves
  (40674), -- Mammalia
  (9255)   -- Monotremata
;
  

/*
 * Add a second table with abbrieviated hierarchy of "interesting" NCBI taxa that will actually appear
 * in the browse UI. These are taxa that either
 *  - have at least one directly related calibration
 *  - OR have more than one sub-clade with calibrations inside
 *  - OR have been marked as an always-visible "landmark" in NCBI_nodes
 */
DROP TABLE IF EXISTS calibration_browsing_tree;
CREATE TABLE calibration_browsing_tree (
  -- for now, track both NCBI and multitree node IDs
  node_NCBI_id MEDIUMINT(8) UNSIGNED,
  multitree_node_id MEDIUMINT(8),
  parent_node_NCBI_id MEDIUMINT(8) UNSIGNED,
  parent_multitree_node_id MEDIUMINT(8),

  is_immediate_NCBI_child TINYINT,  -- if false, it's in a descendant
  clade_includes_published_calibrations TINYINT,  -- if false and NOT a landmark, show this only to admins
  is_browsing_landmark TINYINT,  -- these taxa should ALWAYS appear in the browsing UI, even if empty

  KEY (parent_node_NCBI_id),
  KEY (parent_multitree_node_id)
) ENGINE=InnoDB;

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

DECLARE the_node_id MEDIUMINT(8);
DECLARE the_parent_node_id MEDIUMINT(8);
DECLARE the_depth INT(11);
DECLARE ancestor_NCBI_id MEDIUMINT(8) UNSIGNED;

DECLARE related_calibration_count INT(11);
DECLARE last_calibration_count INT(11);
DECLARE last_interesting_multitree_node_id MEDIUMINT(8);
DECLARE is_a_landmark_node TINYINT;

-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- temporary flag for selective diagnostics
DECLARE debug INT DEFAULT FALSE;

-- a cursor to fetch these fields
DECLARE landmark_node_cursor CURSOR FOR 
  SELECT node_NCBI_id FROM NCBI_browsing_landmarks;

DECLARE direct_relationship_cursor CURSOR FOR 
  SELECT clade_root_NCBI_id, clade_root_multitree_id, calibration_id, publication_status 
  FROM tmp_calibrations_by_NCBI_clade WHERE is_direct_relationship = 1;  

DECLARE ancestor_cursor CURSOR FOR
  SELECT node_id, parent_node_id, depth
  FROM v_ancestors
  ORDER BY depth DESC;

DECLARE CONTINUE HANDLER FOR NOT FOUND 
  SET no_more_rows = TRUE;

-- default parent ID in all cases is the root node ('life') 
SET @root_node_NCBI_id = 1;  -- this never changes
SET @root_multitree_node_id = getMultitreeNodeID( 'NCBI', @root_node_NCBI_id  );

UPDATE site_status SET 
  cladeCalibration_status = 'Updating now';

--
-- clear staging tables for node names, then rebuild
--
DROP TEMPORARY TABLE IF EXISTS tmp_ancestors;

DROP TABLE IF EXISTS tmp_calibrations_by_NCBI_clade;
CREATE TABLE tmp_calibrations_by_NCBI_clade LIKE calibrations_by_NCBI_clade;

DROP TABLE IF EXISTS tmp_calibration_browsing_tree;
CREATE TABLE tmp_calibration_browsing_tree LIKE calibration_browsing_tree;

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
-- SELECT '=== before direct_relationship_cursor (outer loop)';

SET no_more_rows = FALSE;
OPEN direct_relationship_cursor;

  the_loop: LOOP
    FETCH direct_relationship_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, the_publication_status;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    -- pick a test calibration and debug its ancestors
    SET debug = TRUE; -- (the_calibration_id = '123');
    
    -- reckon its depth by counting its (source-tree) ancestors, and save to scratch hints
    -- (currently the source tree is always 'NCBI', since all taxa are chosen from there)
    CALL getAllAncestors( the_multitree_id, "v_ancestors", 'NCBI' );

    IF debug THEN
      SELECT "=== debugging calibration 123, v_ancestors (A) ===";
      SELECT * FROM v_ancestors;
    END IF;

    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_ancestors ENGINE=memory SELECT * FROM v_ancestors;
    /* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */

    TRUNCATE TABLE tmp_ancestors;
    -- copy all the ancestor IDs that are *not* already linked to this calibration
    INSERT INTO tmp_ancestors SELECT * FROM v_ancestors
      WHERE node_id NOT IN (SELECT clade_root_multitree_id FROM tmp_calibrations_by_NCBI_clade 
			    WHERE calibration_id = the_calibration_id);

    IF debug THEN
      SELECT "=== tmp_ancestors (A) ===";
      SELECT * FROM tmp_ancestors;
    END IF;

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


/* Now that we have tallies of indirectly-related calibrations, we can build a
 * tree of "interesting" nodes for browsing. Let's work "root-ward" from each node 
 * that has a directly related calibration, adding any interesting nodes found
 * along the way.
 */

-- add the root ('life') node in any case, as a starting point for browsing
INSERT INTO tmp_calibration_browsing_tree SET 
  node_NCBI_id = @root_node_NCBI_id, 
  multitree_node_id = @root_multitree_node_id, 
  parent_node_NCBI_id = @root_node_NCBI_id, 
  parent_multitree_node_id = @root_multitree_node_id, 
  is_immediate_NCBI_child = FALSE, 
  clade_includes_published_calibrations = FALSE,
  is_browsing_landmark = TRUE;

-- use a cursor to add "landmark" nodes and their interesting ancestors, as in the block below
SET no_more_rows = FALSE;
OPEN landmark_node_cursor;
  IF debug THEN
    SELECT "=== before (L)  loop ===";
  END IF;
  SET no_more_rows = FALSE;

  the_loop: LOOP
    IF debug THEN
      SELECT "=== inside (L)  loop ===";
    END IF;

    FETCH landmark_node_cursor INTO the_ncbi_id; 
    -- TODO: reckon values for:  the_calibration_id, the_publication_status?
    SET the_multitree_id = IFNULL((SELECT multitree_node_id FROM node_identity WHERE source_node_id = the_ncbi_id AND source_tree = 'NCBI'), the_ncbi_id);
    SET the_publication_status = FALSE;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    IF EXISTS (SELECT * FROM tmp_calibration_browsing_tree WHERE node_NCBI_id = the_ncbi_id) THEN
      -- this node is already here (added by another directly related calibration), ignore it and move on
      SET no_more_rows = FALSE; -- reset if needed
      ITERATE the_loop;
    END IF;

    -- pick a test calibration and debug its ancestors
    SET debug = FALSE;  -- (the_multitree_id = '123');

    INSERT INTO tmp_calibration_browsing_tree SET 
      node_NCBI_id = the_ncbi_id, 
      multitree_node_id = the_multitree_id, 
      parent_node_NCBI_id = @root_node_NCBI_id, 
      parent_multitree_node_id = @root_multitree_node_id, 
      is_immediate_NCBI_child = NULL, 
      clade_includes_published_calibrations = FALSE,
      is_browsing_landmark = TRUE;

    -- reckon its depth by counting its (source-tree) ancestors, and save to scratch hints
    -- (currently the source tree is always 'NCBI', since all taxa are chosen from there)
    CALL getAllAncestors( the_multitree_id, "v_ancestors", 'NCBI' );

    IF debug THEN
      SELECT "=== v_ancestors (L) ===";
      SELECT * FROM v_ancestors;

      SELECT "=== cursor preview (sorted) ===";
      SELECT node_id, parent_node_id, depth
        FROM v_ancestors
        ORDER BY depth DESC;
    END IF;

    -- walk its ancestors to find the nearest one that has interesting
    -- information (a directly related calibration, or others in its clade, or landmark status)
    SET last_calibration_count = NULL;
    SET last_interesting_multitree_node_id = the_multitree_id;
    SET no_more_rows = FALSE;
    OPEN ancestor_cursor;
      ancestor_loop: LOOP

        IF debug THEN
          SELECT "=== ... looping, try to fetch ancestor_cursor (forcing no_more_rows to FALSE) ===";
        END IF;
        SET no_more_rows = FALSE;

        FETCH ancestor_cursor INTO the_node_id, the_parent_node_id, the_depth;
        IF no_more_rows THEN 
          LEAVE ancestor_loop;
        END IF;

        IF debug THEN
          SELECT "=== testing this ancestor (the_node_id, the_parent_node_id, the_depth)===";
          SELECT the_node_id, the_parent_node_id, the_depth;
        END IF;

        -- does this ancestor have a different number of related calibrations than its child? if so, it's interesting!
        SET related_calibration_count = (SELECT COUNT(*) FROM tmp_calibrations_by_NCBI_clade WHERE clade_root_multitree_id = the_node_id);

	-- it's also interesting if it's a "landmark" node
	SET is_a_landmark_node = EXISTS (SELECT 1 FROM tmp_calibration_browsing_tree WHERE multitree_node_id = the_node_id AND is_browsing_landmark = 1);

        IF debug THEN
          SELECT "=== related_calibration_count ===";
          SELECT related_calibration_count;

          SELECT "=== last_calibration_count (should skip if NULL) ===";
          SELECT last_calibration_count;
        END IF;

        IF last_calibration_count IS NOT NULL THEN
          -- skip this for the first ancestor (has direct relationship, already "interesting")

          IF debug THEN
            SELECT "=== comparing (related_calibration_count, last_calibration_count, not-equals?)  ===";
            SELECT related_calibration_count, last_calibration_count, (related_calibration_count != last_calibration_count);
          END IF;

          IF related_calibration_count != last_calibration_count OR is_a_landmark_node THEN
            -- voila! it's interesting
            IF debug THEN
              SELECT "=== it's INTERESTING! ===";
            END IF;

            -- is this node pinned or un-pinned? either way, figure out its NCBI and multitree IDs
            SET ancestor_NCBI_id = (SELECT source_node_id FROM node_identity WHERE source_tree = 'NCBI' AND multitree_node_id = the_node_id);
            IF ancestor_NCBI_id IS NULL THEN
              SET ancestor_NCBI_id = the_node_id;
            END IF;

            -- IF this ancestor isn't already listed as "interesting", add it now
            IF EXISTS (SELECT 1 FROM tmp_calibration_browsing_tree WHERE multitree_node_id = the_node_id) THEN
              -- it's already here! just tweak the publication-status flag if the current calibration is public
              UPDATE tmp_calibration_browsing_tree
                SET clade_includes_published_calibrations = clade_includes_published_calibrations OR the_publication_status
                WHERE multitree_node_id = the_node_id;

            ELSE
              -- add a new record for this interesting ancestor
              INSERT INTO tmp_calibration_browsing_tree SET 
                node_NCBI_id = ancestor_NCBI_id,
                multitree_node_id = the_node_id, 
                parent_node_NCBI_id = @root_node_NCBI_id, 
                parent_multitree_node_id = @root_multitree_node_id, 
                is_immediate_NCBI_child = 0, 
                clade_includes_published_calibrations = the_publication_status;

            END IF;

            -- update the current calibrated node to point to this record as its ancestor
            UPDATE tmp_calibration_browsing_tree SET 
              parent_node_NCBI_id = ancestor_NCBI_id,
              parent_multitree_node_id = the_node_id,
              is_immediate_NCBI_child = (the_depth = '-1')
            WHERE multitree_node_id = last_interesting_multitree_node_id;

            -- keep going through all ancestors! adding any other interesting nodes we find along the way...
            SET last_interesting_multitree_node_id = the_node_id;

          ELSE 

            IF debug THEN
              SELECT "=== it's NOT interesting! ===";
            END IF;

          END IF; -- if count has changed

        ELSE 

            IF debug THEN
              SELECT "=== SKIPPING (previous count was NULL) ===";
            END IF;

        END IF; -- if last count is not NULL

        SET last_calibration_count = related_calibration_count;

        IF debug THEN
          SELECT "=== NEW last_calibration_count ===";
          SELECT last_calibration_count;
        END IF;

        SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
      END LOOP;
    CLOSE ancestor_cursor;
    SET no_more_rows = FALSE;

  END LOOP;

CLOSE landmark_node_cursor;
SET no_more_rows = FALSE;









SET no_more_rows = FALSE;
OPEN direct_relationship_cursor;
  IF debug THEN
    SELECT "=== before (B)  loop ===";
  END IF;
  SET no_more_rows = FALSE;

  the_loop: LOOP
    IF debug THEN
      SELECT "=== inside (B)  loop ===";
    END IF;

    FETCH direct_relationship_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, the_publication_status;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    IF EXISTS (SELECT * FROM tmp_calibration_browsing_tree WHERE node_NCBI_id = the_ncbi_id) THEN
      -- this node is already here (added by another directly related calibration), ignore it and move on
      SET no_more_rows = FALSE; -- reset if needed
      ITERATE the_loop;
    END IF;

    -- pick a test calibration and debug its ancestors
    SET debug = FALSE;  -- (the_calibration_id = '123');

    INSERT INTO tmp_calibration_browsing_tree SET 
      node_NCBI_id = the_ncbi_id, 
      multitree_node_id = the_multitree_id, 
      parent_node_NCBI_id = @root_node_NCBI_id, 
      parent_multitree_node_id = @root_multitree_node_id, 
      is_immediate_NCBI_child = NULL, 
      clade_includes_published_calibrations = the_publication_status,  -- TODO: correct this?
      is_browsing_landmark = EXISTS (SELECT 1 FROM NCBI_browsing_landmarks WHERE node_NCBI_id = the_ncbi_id);

    -- reckon its depth by counting its (source-tree) ancestors, and save to scratch hints
    -- (currently the source tree is always 'NCBI', since all taxa are chosen from there)
    CALL getAllAncestors( the_multitree_id, "v_ancestors", 'NCBI' );

    IF debug THEN
      SELECT "=== v_ancestors (B) ===";
      SELECT * FROM v_ancestors;

      SELECT "=== cursor preview (sorted) ===";
      SELECT node_id, parent_node_id, depth
        FROM v_ancestors
        ORDER BY depth DESC;
    END IF;

    -- walk its ancestors to find the nearest one that has interesting
    -- information (a directly related calibration, or others in its clade)
    SET last_calibration_count = NULL;
    SET last_interesting_multitree_node_id = the_multitree_id;
    SET no_more_rows = FALSE;
    OPEN ancestor_cursor;
      ancestor_loop: LOOP

        IF debug THEN
          SELECT "=== ... looping, try to fetch ancestor_cursor (forcing no_more_rows to FALSE) ===";
        END IF;
        SET no_more_rows = FALSE;

        FETCH ancestor_cursor INTO the_node_id, the_parent_node_id, the_depth;
        IF no_more_rows THEN 
          LEAVE ancestor_loop;
        END IF;

        IF debug THEN
          SELECT "=== testing this ancestor (the_node_id, the_parent_node_id, the_depth)===";
          SELECT the_node_id, the_parent_node_id, the_depth;
        END IF;

        -- does this ancestor have a different number of related calibrations than its child? if so, it's interesting!
        SET related_calibration_count = (SELECT COUNT(*) FROM tmp_calibrations_by_NCBI_clade WHERE clade_root_multitree_id = the_node_id);

	-- it's also interesting if it's a "landmark" node
	SET is_a_landmark_node = EXISTS (SELECT 1 FROM tmp_calibration_browsing_tree WHERE multitree_node_id = the_node_id AND is_browsing_landmark = 1);

        IF debug THEN
          SELECT "=== related_calibration_count ===";
          SELECT related_calibration_count;

          SELECT "=== last_calibration_count (should skip if NULL) ===";
          SELECT last_calibration_count;
        END IF;

        IF last_calibration_count IS NOT NULL THEN
          -- skip this for the first ancestor (has direct relationship, already "interesting")

          IF debug THEN
            SELECT "=== comparing (related_calibration_count, last_calibration_count, not-equals?)  ===";
            SELECT related_calibration_count, last_calibration_count, (related_calibration_count != last_calibration_count);
          END IF;

          IF related_calibration_count != last_calibration_count OR is_a_landmark_node THEN
            -- voila! it's interesting
            IF debug THEN
              SELECT "=== it's INTERESTING! ===";
            END IF;

            -- is this node pinned or un-pinned? either way, figure out its NCBI and multitree IDs
            SET ancestor_NCBI_id = (SELECT source_node_id FROM node_identity WHERE source_tree = 'NCBI' AND multitree_node_id = the_node_id);
            IF ancestor_NCBI_id IS NULL THEN
              SET ancestor_NCBI_id = the_node_id;
            END IF;

            -- IF this ancestor isn't already listed as "interesting", add it now
            IF EXISTS (SELECT 1 FROM tmp_calibration_browsing_tree WHERE multitree_node_id = the_node_id) THEN
              -- it's already here! just tweak the publication-status flag if the current calibration is public
              UPDATE tmp_calibration_browsing_tree
                SET clade_includes_published_calibrations = clade_includes_published_calibrations OR the_publication_status
                WHERE multitree_node_id = the_node_id;

            ELSE
              -- add a new record for this interesting ancestor
              INSERT INTO tmp_calibration_browsing_tree SET 
                node_NCBI_id = ancestor_NCBI_id,
                multitree_node_id = the_node_id, 
                parent_node_NCBI_id = @root_node_NCBI_id, 
                parent_multitree_node_id = @root_multitree_node_id, 
                is_immediate_NCBI_child = 0, 
                clade_includes_published_calibrations = the_publication_status;

            END IF;

            -- update the current calibrated node to point to this record as its ancestor
            UPDATE tmp_calibration_browsing_tree SET 
              parent_node_NCBI_id = ancestor_NCBI_id,
              parent_multitree_node_id = the_node_id,
              is_immediate_NCBI_child = (the_depth = '-1')
            WHERE multitree_node_id = last_interesting_multitree_node_id;

            -- keep going through all ancestors! adding any other interesting nodes we find along the way...
            SET last_interesting_multitree_node_id = the_node_id;

          ELSE 

            IF debug THEN
              SELECT "=== it's NOT interesting! ===";
            END IF;

          END IF; -- if count has changed

        ELSE 

            IF debug THEN
              SELECT "=== SKIPPING (previous count was NULL) ===";
            END IF;

        END IF; -- if last count is not NULL

        SET last_calibration_count = related_calibration_count;

        IF debug THEN
          SELECT "=== NEW last_calibration_count ===";
          SELECT last_calibration_count;
        END IF;

        SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
      END LOOP;
    CLOSE ancestor_cursor;
    SET no_more_rows = FALSE;

  END LOOP;

CLOSE direct_relationship_cursor;
SET no_more_rows = FALSE;


-- if we're serious, replace/rename the real tables
IF testOrFinal = 'FINAL' THEN
  -- TRUNCATE TABLE AC_names_nodes;
  -- INSERT INTO AC_names_nodes SELECT * FROM tmp_AC_names_nodes;
  RENAME TABLE calibrations_by_NCBI_clade TO doomed, tmp_calibrations_by_NCBI_clade TO calibrations_by_NCBI_clade;
  DROP TABLE doomed;

  RENAME TABLE calibration_browsing_tree TO doomed, tmp_calibration_browsing_tree TO calibration_browsing_tree;
  DROP TABLE doomed;
END IF;

UPDATE site_status SET
  cladeCalibration_status = 'Up to date',
  last_cladeCalibration_update = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_ancestors;

END #

DELIMITER ;


/*
 * buildCalibrationTree( the_calibration_id )
 *
 * Builds multitree "inputs" for the specified calibration:
 *   FCD_trees
 *   FCD_nodes
 *   FCD_names
 *   pinned_nodes
 */

DROP PROCEDURE IF EXISTS buildCalibrationTree;

DELIMITER #

CREATE PROCEDURE buildCalibrationTree(IN the_calibration_id INT(11))
BEGIN

-- DECLARE any local vars here

/* 
 * Custom tree (re)generation, adapted from 'update_calibration.php'
 */

SELECT CONCAT ("...(re)building tree for calibration", the_calibration_id);

-- describe this calibration's new tree, based on the updated node definition (hints)
DROP TEMPORARY TABLE IF EXISTS updateHints;

CREATE TEMPORARY TABLE updateHints ENGINE=memory AS 
    (SELECT * FROM node_definitions WHERE calibration_id = the_calibration_id);

CALL buildTreeDescriptionFromNodeDefinition( "updateHints", "updateTreeDef" );

-- store the resulting tree, pinned to NCBI or other FCD nodes as needed
CALL updateTreeFromDefinition( the_calibration_id, "updateTreeDef" );

DROP TEMPORARY TABLE IF EXISTS updateHints;
DROP TEMPORARY TABLE IF EXISTS updateTreeDef;

END #

DELIMITER ;

/*
 * rebuildAllCalibrationTrees( )
 *
 * NOTE that this should always be run BEFORE rebuilding the multitree, since
 * this is only builds multitree "inputs" with source IDs for trees and nodes:
 *   FCD_trees
 *   FCD_nodes
 *   FCD_names
 *   pinned_nodes
 */

DROP PROCEDURE IF EXISTS rebuildAllCalibrationTrees;

DELIMITER #

CREATE PROCEDURE rebuildAllCalibrationTrees ()
BEGIN

-- DECLARE any local vars here
DECLARE the_calibration_id INT(11);

-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- a cursor to fetch all calibration IDs
DECLARE calibration_cursor CURSOR FOR 
  SELECT CalibrationID FROM calibrations;

DECLARE CONTINUE HANDLER FOR NOT FOUND 
  SET no_more_rows = TRUE;

OPEN calibration_cursor;

  the_loop: LOOP
    FETCH calibration_cursor INTO the_calibration_id;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    CALL buildCalibrationTree( the_calibration_id ); 

    SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
  END LOOP;

CLOSE calibration_cursor;
SET no_more_rows = FALSE;

END #

DELIMITER ;

