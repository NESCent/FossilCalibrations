/*
 * stashPinnedLineages( BEFOREorAFTER )
 * 	where BEFOREorAFTER = 'BEFORE' | 'AFTER'
 *
 * This stored procedure detects calibrations that are affected by NCBI
 * taxonomy updates, by capturing the current NCBI lineage of all nodes pinned
 * from FCDB trees. 
 *
 * It should be called once with the 'BEFORE' argument just before updating the
 * NCBI taxonomy. This will create our comparison table (if needed) and store
 * all lineage information as comma-delimited strings in the BEFORE_lineage
 * column.
 *
 * After the NCBI update, rebuild all FCDB data, then run this script again to
 * see a list of calibrations that should be reviewed in the FCDB Browse tool.
 * 
 * For more explanation and instructions, see the README at
 *   https://github.com/NESCent/FossilCalibrations/tree/master/ncbi-update/README.md
 */
USE FossilCalibration;

DROP PROCEDURE IF EXISTS stashPinnedLineages;

DELIMITER #

CREATE PROCEDURE stashPinnedLineages(IN BEFOREorAFTER VARCHAR(20))
BEGIN

-- DECLARE any local vars here

-- DECLARE oldRecursionDepth INT;
DECLARE the_pinned_node_id MEDIUMINT(8) UNSIGNED;  -- NOTE that this is a positive ID from its *source* tree (not multitree)

-- a flag terminates the loop when no more records are found
DECLARE no_more_rows INT DEFAULT FALSE;

-- a cursor to fetch these fields
DECLARE pinned_node_cursor CURSOR FOR SELECT pinned_node_id FROM NCBI_lineage_comparison;

DECLARE CONTINUE HANDLER FOR NOT FOUND 
  SET no_more_rows = TRUE;

  -- create bare table to store "before and after" lineage for each pinned node
  -- NOTE that allow plenty of space for concatenated lineage strings!

  IF BEFOREorAFTER = 'BEFORE' THEN
    DROP TABLE IF EXISTS NCBI_lineage_comparison;
    CREATE TABLE NCBI_lineage_comparison (BEFORE_lineage TEXT, AFTER_lineage TEXT) AS
      SELECT pn.pinned_node_id, pn.calibration_id, pn.is_public_node
        FROM pinned_nodes AS pn;
  END IF;

  -- show some example output 
  -- SELECT 'Initial NCBI_lineage_comparison (first five records)' AS '';
  -- SELECT * FROM NCBI_lineage_comparison LIMIT 5;

  -- walk this table and stash the current lineage for each pinned node as a string
  OPEN pinned_node_cursor;

  the_loop: LOOP
    FETCH pinned_node_cursor INTO the_pinned_node_id;

    IF no_more_rows THEN 
      LEAVE the_loop;
    END IF;

    -- use ad-hoc variables to trace the pinned node through NCBI to the current multitree
    -- SELECT the_pinned_node_id;
    
    SELECT target_node_id FROM pinned_nodes WHERE target_tree = 'NCBI' AND pinned_node_id = the_pinned_node_id INTO @pinned_NCBI_id;
    -- SELECT @pinned_NCBI_id;
    
    -- find this NCBI node in the multitree
    SELECT getMultitreeNodeID('NCBI', @pinned_NCBI_id) INTO @pinned_multitree_id; 
    -- SELECT @pinned_multitree_id;
    
    -- now get its lineage (from NCBI only)
    -- SELECT 'Get all its NCBI ancestors...' as '';
    CALL getAllAncestors (@pinned_multitree_id, 'temp_ancestors', 'NCBI'); -- or 'ALL TREES'
    -- SELECT * FROM temp_ancestors;
    
    -- recover NCBI ids for these nodes
    CALL getFullNodeInfo("temp_ancestors", "temp_ancestors_info" );
    -- SELECT * FROM temp_ancestors_info;
    
    -- SELECT 'Recovering NCBI node IDs...' as '';
    -- SELECT * FROM temp_ancestors_info 
    -- 	    WHERE source_tree = "NCBI"
    -- 	      AND multitree_node_id IN (SELECT multitree_node_id FROM calibration_browsing_tree);
    
    -- SELECT 'Lineage as multitree ids...' as '';
    -- SELECT GROUP_CONCAT(node_id ORDER BY depth ASC SEPARATOR ',') FROM temp_ancestors INTO @lineage_string_OLD;
    -- SELECT @lineage_string_OLD;
    
    -- SELECT 'Lineage as NCBI ids...' as '';
    SELECT GROUP_CONCAT(source_node_id ORDER BY query_depth ASC SEPARATOR ',') 
      FROM temp_ancestors_info
      WHERE source_tree = "NCBI" 
        -- AND multitree_node_id IN (SELECT multitree_node_id FROM calibration_browsing_tree)
    INTO @lineage_string;
    -- SELECT @lineage_string;
    
    IF BEFOREorAFTER = 'BEFORE' THEN
      UPDATE NCBI_lineage_comparison
        SET BEFORE_lineage = @lineage_string
        WHERE pinned_node_id = the_pinned_node_id;
    ELSE
      UPDATE NCBI_lineage_comparison
        SET AFTER_lineage = @lineage_string
        WHERE pinned_node_id = the_pinned_node_id;
    END IF;

    SET no_more_rows = FALSE; -- in case this was bumped by call to stored procedure
  END LOOP;

  CLOSE pinned_node_cursor;
  SET no_more_rows = FALSE;

  -- SELECT 'Modified NCBI_lineage_comparison (first five records)' AS '';
  -- SELECT * FROM NCBI_lineage_comparison LIMIT 5;

  IF BEFOREorAFTER = 'AFTER' THEN
    SELECT 'REVIEW THE CALIBRATIONS BELOW based on changes to NCBI lineage:' AS '';
    SELECT DISTINCT calibration_id FROM NCBI_lineage_comparison
      WHERE BEFORE_lineage != AFTER_lineage;
  ELSE 
    SELECT 'CURRENT LINEAGE STASHED for all pinned FCD nodes!' AS '';
  END IF;

  -- clean up, just in case
  -- DROP TEMPORARY TABLE IF EXISTS hints;

END #

DELIMITER ;

