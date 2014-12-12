/*
 * refreshCalibrationsByClade( testOrFinal )
 * 	where testOrFinal = 'TEST' | 'FINAL'
 *
 * NOTE that this should always be run AFTER rebuilding the multitree, since we'll use those
 * fragile IDs here. (If we do these out of order, we'll have stale/funky IDs in this table.)
 */
USE FossilCalibration;
DROP PROCEDURE IF EXISTS refreshCalibrationsByClade;

DELIMITER #

CREATE PROCEDURE refreshCalibrationsByClade (IN testOrFinal VARCHAR(20))
BEGIN

-- DECLARE any local vars here
DECLARE the_ncbi_id MEDIUMINT(8) UNSIGNED;
DECLARE the_multitree_id MEDIUMINT(8);
DECLARE the_calibration_id INT(11);
DECLARE the_publication_status INT(11);

DECLARE the_landmark_ncbi_id MEDIUMINT(8) UNSIGNED;
DECLARE the_landmark_multitree_id MEDIUMINT(8);
DECLARE the_landmark_parent_multitree_id MEDIUMINT(8);

-- extra vars for calibration_info_cursor
DECLARE this_is_direct_relationship TINYINT;
DECLARE this_is_custom_child_node TINYINT;
-- two multitree IDs to find MRCA for custom-node parent
DECLARE the_custom_tree_id MEDIUMINT(8);
DECLARE the_custom_tree_name VARCHAR(20);
DECLARE the_lowest_related_multitree_id MEDIUMINT(8);
DECLARE the_highest_related_multitree_id MEDIUMINT(8);
-- TODO: replace this with a more thorough (tedious) procedure, to compare ALL related nodes?

DECLARE the_node_id MEDIUMINT(8);
DECLARE the_parent_node_id MEDIUMINT(8);
DECLARE the_depth INT(11);
DECLARE ancestor_NCBI_id MEDIUMINT(8) UNSIGNED;

DECLARE related_calibration_count INT(11);
DECLARE last_calibration_count INT(11);

DECLARE related_landmark_count INT(11);
DECLARE last_landmark_count INT(11);

DECLARE last_interesting_multitree_node_id MEDIUMINT(8);
DECLARE is_a_landmark_node TINYINT;
DECLARE has_multiple_NCBI_parents TINYINT;


-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- temporary flag for selective diagnostics
DECLARE debug INT DEFAULT FALSE;

-- a cursor to fetch these fields
DECLARE calibration_info_cursor CURSOR FOR
  SELECT
    pinned_nodes.target_node_id,  -- NCBI ID for a node that is identical to FCD nodes in FCD-tree for a given calibration
    node_identity.multitree_node_id,  -- its corresponding multitree ID
    c.CalibrationID,
    1,  -- these are DIRECT relationships
    (SELECT COUNT(*) FROM FCD_nodes WHERE parent_node_id = FCD_trees.root_node_id) > 1,
    c.PublicationStatus
  FROM
    calibrations AS c
  JOIN FCD_trees ON FCD_trees.calibration_id = c.CalibrationID
  JOIN FCD_nodes ON FCD_nodes.parent_node_id = FCD_trees.root_node_id
  JOIN pinned_nodes ON pinned_nodes.target_tree = 'NCBI' AND pinned_nodes.pinned_node_id = FCD_nodes.node_id
  JOIN node_identity ON node_identity.source_tree = 'NCBI' AND node_identity.source_node_id = pinned_nodes.target_node_id;


DECLARE landmark_node_cursor CURSOR FOR 
  SELECT node_NCBI_id FROM NCBI_browsing_landmarks;

DECLARE direct_relationship_cursor CURSOR FOR 
  SELECT clade_root_NCBI_id, clade_root_multitree_id, calibration_id AS test_cal_id, publication_status,
    ((SELECT COUNT(*) FROM tmp_calibrations_by_NCBI_clade WHERE is_direct_relationship AND calibration_id = test_cal_id) > 1) AS has_multiple_parents
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

DROP TABLE IF EXISTS tmp_landmarks_by_NCBI_clade;
CREATE TABLE tmp_landmarks_by_NCBI_clade LIKE landmarks_by_NCBI_clade;

DROP TABLE IF EXISTS tmp_calibrations_by_NCBI_clade;
CREATE TABLE tmp_calibrations_by_NCBI_clade LIKE calibrations_by_NCBI_clade;

DROP TABLE IF EXISTS tmp_calibration_browsing_tree;
CREATE TABLE tmp_calibration_browsing_tree LIKE calibration_browsing_tree;

-- start by build up the table of landmark tallies
SET no_more_rows = FALSE;
OPEN landmark_node_cursor;

  the_loop: LOOP
    FETCH landmark_node_cursor INTO the_landmark_ncbi_id;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    -- walk its ancestors and add a record for each (marking the first "ancestor" as immediate-child relationship)
    SET the_landmark_multitree_id = IFNULL((SELECT multitree_node_id FROM node_identity WHERE source_node_id = the_landmark_ncbi_id AND source_tree = 'NCBI'), the_landmark_ncbi_id);
    CALL getAllAncestors( the_landmark_multitree_id, "v_ancestors", 'NCBI' );
    SET no_more_rows = FALSE; -- just in case

    DROP TABLE IF EXISTS tmp_ancestors2;
    CREATE TEMPORARY TABLE tmp_ancestors2 ENGINE=memory SELECT * FROM v_ancestors;
    /* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */

    SET the_landmark_parent_multitree_id = (SELECT parent_node_id FROM tmp_ancestors2 WHERE depth = 0);

    IF EXISTS (SELECT 1 FROM tmp_ancestors2) THEN

      -- first pass, to gather indirect relationships on pinned nodes
      INSERT INTO tmp_landmarks_by_NCBI_clade  (clade_root_NCBI_id, clade_root_multitree_id, landmark_NCBI_id, is_immediate_child)
      SELECT
        node_identity.source_node_id,
        node_identity.multitree_node_id,
        the_landmark_ncbi_id, 
        (node_identity.multitree_node_id = the_landmark_parent_multitree_id)
      FROM
        node_identity
      WHERE node_identity.source_tree = 'NCBI' 
        AND node_identity.multitree_node_id IN (SELECT node_id FROM tmp_ancestors2);

      -- second pass, to gather indirect relationships on UN-pinned nodes
      INSERT INTO tmp_landmarks_by_NCBI_clade  (clade_root_NCBI_id, clade_root_multitree_id, landmark_NCBI_id, is_immediate_child)
      SELECT
        multitree.node_id,  -- NCBI ID for an un-pinned NCBI node
        multitree.node_id,  -- its corresponding multitree ID (unchanged for un-pinned nodes!)
        the_landmark_ncbi_id, 
        (multitree.node_id = the_landmark_parent_multitree_id)
      FROM
        multitree 
      WHERE multitree.node_id IN (SELECT node_id FROM tmp_ancestors2);

    END IF;

    SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
  END LOOP;

CLOSE landmark_node_cursor;
SET no_more_rows = FALSE;



-- initial records will be on the node/clade that is *directly* related to each calibration (or a custom node under the MRCA, if there's more than one)

-- For a cleaner tree, consolidate each calibration into a single context, under the clade of its MRCA
-- find the MRCA of all related NCBI nodes (incl. landmarks) and designate this as the browsing "parent"
-- create a new entry for this calibration, under a custom node (node_NCBI_id = NULL, multitree_node_id = ??)
-- decrement tallies on its ancestors (remove if tally drops to zero)
-- NOTE that we need to modify both calibrations_by_NCBI_clade and calibration_browsing_tree!
SET no_more_rows = FALSE;
OPEN calibration_info_cursor;

  the_loop: LOOP
    FETCH calibration_info_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, this_is_direct_relationship, this_is_custom_child_node, the_publication_status;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    IF EXISTS(SELECT 1 FROM tmp_calibrations_by_NCBI_clade WHERE calibration_id = the_calibration_id) THEN
      -- this calibration is already here, probably a basal-split
      ITERATE the_loop;
    END IF;

    IF this_is_custom_child_node THEN
      -- this is the result of a basal split, modify parent IDs before adding it
      SET the_custom_tree_id = (SELECT MIN(tree_id) FROM FCD_trees WHERE calibration_id = the_calibration_id);
      SET the_custom_tree_name = CONCAT('FCD-', the_custom_tree_id);
      SET the_lowest_related_multitree_id = (SELECT MIN(node_id) FROM FCD_nodes WHERE tree_id = the_custom_tree_id AND parent_node_id IS NOT NULL);
      SET the_highest_related_multitree_id = (SELECT MAX(node_id) FROM FCD_nodes WHERE tree_id = the_custom_tree_id AND parent_node_id IS NOT NULL);
      -- convert these to proper multitree IDs
      SET the_lowest_related_multitree_id = getMultitreeNodeID( the_custom_tree_name, the_lowest_related_multitree_id);
      SET the_highest_related_multitree_id = getMultitreeNodeID( the_custom_tree_name, the_highest_related_multitree_id);
      CALL getMostRecentCommonAncestor( the_lowest_related_multitree_id, the_highest_related_multitree_id, "mostRecentCommonAncestor_ids", 'NCBI' );
      CALL getFullNodeInfo( "mostRecentCommonAncestor_ids", "mostRecentCommonAncestor_info" );
      -- SELECT * FROM mostRecentCommonAncestor_info;
      SET the_ncbi_id = (SELECT MIN(source_node_id) FROM mostRecentCommonAncestor_info WHERE source_tree = 'NCBI');
      -- SELECT the_ncbi_id AS ">>>>>>>>> the_ncbi_id";
      SET the_multitree_id = (SELECT MIN(multitree_node_id) FROM mostRecentCommonAncestor_info);
      -- SELECT the_multitree_id AS ">>>>>>>>> the_multitree_id";
    END IF;

    -- SELECT the_ncbi_id, the_multitree_id, the_calibration_id;
    IF the_ncbi_id IS NULL THEN
      -- this calibration is probably pinned to an NCBI node that has been deleted! report this and skip
      SELECT 'PLEASE REFRESH THE NODE LOCATION for calibration ', the_calibration_id;
      SET no_more_rows = FALSE; -- reset if needed
      ITERATE the_loop;
    END IF;

    INSERT INTO tmp_calibrations_by_NCBI_clade SET
      clade_root_NCBI_id = the_ncbi_id, 
      clade_root_multitree_id = the_multitree_id, 
      calibration_id = the_calibration_id, 
      is_direct_relationship = this_is_direct_relationship, 
      is_custom_child_node = this_is_custom_child_node, 
      publication_status = the_publication_status;

    SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
  END LOOP;

CLOSE calibration_info_cursor;
SET no_more_rows = FALSE;

-- for each of the calibration/clade pairs above, add *indirect* relationships with all ancestor clades
-- this will give us tallies to show in a compact tree format
-- SELECT '=== before direct_relationship_cursor (outer loop)';

SET no_more_rows = FALSE;
OPEN direct_relationship_cursor;

  the_loop: LOOP
    FETCH direct_relationship_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, the_publication_status, has_multiple_NCBI_parents;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    -- pick a test calibration and debug its ancestors
    SET debug = FALSE; -- (the_calibration_id = '123');
    -- SET debug = (the_multitree_id = '117571'); -- Sarcopterygii
    
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
    -- SET debug = FALSE;  -- (the_multitree_id = '123');
    -- SET debug = (the_multitree_id = '117571'); -- Sarcopterygii

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
    SET last_landmark_count = 0;
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

	-- ... or if it contains more than one interesting node, whether multiple landmarks or one landmark + populated node
        SET related_landmark_count = (SELECT COUNT(*) FROM tmp_landmarks_by_NCBI_clade WHERE clade_root_multitree_id = the_node_id);

        IF debug THEN
          SELECT "=== related_calibration_count ===";
          SELECT related_calibration_count;

          SELECT "=== last_calibration_count (should skip if NULL) ===";
          SELECT last_calibration_count;

          SELECT "=== related_landmark_count ===";
          SELECT related_landmark_count;

          SELECT "=== last_landmark_count ===";
          SELECT last_landmark_count;
        END IF;

        IF last_calibration_count IS NOT NULL THEN
          -- skip this for the first ancestor (has direct relationship, already "interesting")

          IF debug THEN
            SELECT "=== comparing (related_calibration_count, last_calibration_count, not-equals?)  ===";
            SELECT related_calibration_count, last_calibration_count, (related_calibration_count != last_calibration_count);
          END IF;

          IF (related_calibration_count != last_calibration_count) OR (related_landmark_count != last_landmark_count) OR is_a_landmark_node THEN
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
        SET last_landmark_count = related_landmark_count;

        IF debug THEN
          SELECT "=== NEW last_calibration_count ===";
          SELECT last_calibration_count;

          SELECT "=== NEW last_landmark_count ===";
          SELECT last_landmark_count;
        END IF;

        SET no_more_rows = FALSE;  -- just in case it's been corrupted by procedure calls
      END LOOP;
    CLOSE ancestor_cursor;
    SET no_more_rows = FALSE;

  END LOOP;

CLOSE landmark_node_cursor;
SET no_more_rows = FALSE;


-- sweep again with the same cursor, adding direct relationships?
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

    FETCH direct_relationship_cursor INTO the_ncbi_id, the_multitree_id, the_calibration_id, the_publication_status, has_multiple_NCBI_parents;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    IF EXISTS (SELECT * FROM tmp_calibration_browsing_tree WHERE node_NCBI_id = the_ncbi_id) THEN
      -- this node is already here (added by another directly related calibration), ignore it and move on
      SET no_more_rows = FALSE; -- reset if needed
      ITERATE the_loop;
    END IF;

    -- pick a test calibration and debug its ancestors
    -- SET debug = FALSE;  -- (the_calibration_id = '123');
    -- SET debug = (the_multitree_id = '117571'); -- Sarcopterygii

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
    SET last_landmark_count = 0;
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

	-- ... or if it contains more than one interesting node, whether multiple landmarks or one landmark + populated node
        SET related_landmark_count = (SELECT COUNT(*) FROM tmp_landmarks_by_NCBI_clade WHERE clade_root_multitree_id = the_node_id);

        IF debug THEN
          SELECT "=== related_calibration_count ===";
          SELECT related_calibration_count;

          SELECT "=== last_calibration_count (should skip if NULL) ===";
          SELECT last_calibration_count;

          SELECT "=== related_landmark_count ===";
          SELECT related_landmark_count;

          SELECT "=== last_landmark_count ===";
          SELECT last_landmark_count;
        END IF;

        IF last_calibration_count IS NOT NULL THEN
          -- skip this for the first ancestor (has direct relationship, already "interesting")

          IF debug THEN
            SELECT "=== comparing (related_calibration_count, last_calibration_count, not-equals?)  ===";
            SELECT related_calibration_count, last_calibration_count, (related_calibration_count != last_calibration_count);
          END IF;

          IF (related_calibration_count != last_calibration_count) OR (related_landmark_count != last_landmark_count) OR is_a_landmark_node THEN
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
        SET last_landmark_count = related_landmark_count;

        IF debug THEN
          SELECT "=== NEW last_calibration_count ===";
          SELECT last_calibration_count;

          SELECT "=== NEW last_landmark_count ===";
          SELECT last_landmark_count;
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

  RENAME TABLE landmarks_by_NCBI_clade TO doomed, tmp_landmarks_by_NCBI_clade TO landmarks_by_NCBI_clade;
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
