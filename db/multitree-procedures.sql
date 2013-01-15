/*
 * Here's a family of stored procedures that probe the full multitree (the
 * super-set of all reference and user-defined trees). These calls can be
 * chained together to handle common use cases. Adapted from the NCBI-only
 * procedures defined previously.
 * 
 * NOTE that in all cases, we're using temporary tables to store our results.
 * This is the recommended alternative to CTEs in MySQL, and should support
 * easy implementation of the same behavior for different clients (the main FCD
 * website, a mobile version, non-web interfaces and APIs).
 */


/*
 * getFullNodeInfo( sourceTableName, destinationTableName )
 *
 * This takes a minimal table (eg, the result set from a multitree query):
 *   node_id
 *   parent_node_id
 *   is_public_path
 * and adds supplemental information about source nodes, node names, etc:
 *   multitree_node_id  -- NOTE that this will be duplicated for pinned nodes!
 *   source_tree
 *   source_node_id
 *   parent_node_id
 *   depth -- optional, if provided (specific to query target)
 *   name  -- TODO: different names based on source
 *   uniquename
 *   class -- refers to name type (eg, 'scientific name')
 *   node_status | is_public_node | is_public_path
 */

DROP PROCEDURE IF EXISTS getFullNodeInfo;

DELIMITER #

CREATE PROCEDURE getFullNodeInfo (IN sourceTableName VARCHAR(80), IN destinationTableName VARCHAR(80))
BEGIN

-- DECLARE any local vars here

DROP TEMPORARY TABLE IF EXISTS src;
DROP TEMPORARY TABLE IF EXISTS tmp;

-- we'll use prepared statements (dynamic SQL) to copy data between specified tables and "local" scratch tables
SET @sql = CONCAT('CREATE TEMPORARY TABLE src ENGINE=memory AS (SELECT * FROM ', sourceTableName ,');');
SELECT @sql as "";
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;

-- put resulting values into 'tmp' so we can rename+preserve them
/* TODO TODO TODO TODO TODO */
CREATE TEMPORARY TABLE tmp AS 
SELECT 
 src.node_id AS multitree_node_id,
 src.parent_node_id AS parent_multitree_node_id,
 src.depth AS query_depth,
 names.name,
 names.uniquename,
 names.class
FROM 
 src
LEFT OUTER JOIN NCBI_names AS names ON src.taxonid = names.taxonid && names.class = 'scientific name';
-- TODO: mix in FCD_names based on source_tree?
-- ORDER BY
--  src.depth, src.taxonid;

-- PREPARE statement to create/replace named temp table, copy contents into it?
-- SET @sqlRenameTable = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, '; ALTER TABLE tmp RENAME ', destinationTableName, ';');
SET @sql = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;
-- NOTE: just one statement allowed at a time! breaking this up...
SET @sql = CONCAT('ALTER TABLE tmp RENAME ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;

DROP TEMPORARY TABLE IF EXISTS hier;
DROP TEMPORARY TABLE IF EXISTS tmp; -- just in case...

END #

DELIMITER ;



/*
 * getAllAncestors( p_taxonid, destinationTableName )
 */

DROP PROCEDURE IF EXISTS getAllAncestors;

DELIMITER #

CREATE PROCEDURE getAllAncestors (IN p_taxonid MEDIUMINT(8) UNSIGNED, IN destinationTableName VARCHAR(80))
BEGIN

-- Declarations must be at the very top of BEGIN/END block!
DECLARE v_done TINYINT UNSIGNED DEFAULT 0;
DECLARE v_depth SMALLINT DEFAULT 0;
DECLARE v_testNodeID MEDIUMINT(8) default p_taxonid;

DROP TEMPORARY TABLE IF EXISTS hier;
DROP TEMPORARY TABLE IF EXISTS tmp;

-- let's try counting depth "backwards" from 0
CREATE TEMPORARY TABLE hier (
 taxonid MEDIUMINT(8) UNSIGNED, 
 parenttaxonid MEDIUMINT(8) UNSIGNED, 
 depth SMALLINT(6) DEFAULT 0
) ENGINE = memory;

INSERT INTO hier SELECT taxonid, parenttaxonid, v_depth FROM NCBI_nodes WHERE taxonid = p_taxonid;

/* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */

CREATE TEMPORARY TABLE tmp ENGINE=memory SELECT * FROM hier;

WHILE NOT v_done DO

    -- SELECT v_testNodeID AS "... LOOPING ... v_testNodeID", v_depth as "A. v_depth";

    TRUNCATE TABLE tmp;
    INSERT INTO tmp SELECT * FROM hier;

    -- SELECT * FROM hier; -- WHERE depth = v_depth;

    -- SELECT p.taxonid, p.parenttaxonid, v_depth FROM NCBI_nodes p 
      -- INNER JOIN tmp ON p.taxonid = tmp.parenttaxonid AND tmp.depth = v_depth;

    INSERT INTO hier 
	SELECT p.taxonid, p.parenttaxonid, (v_depth - 1) FROM NCBI_nodes p 
	INNER JOIN tmp ON p.taxonid = tmp.parenttaxonid AND tmp.depth = v_depth;

    -- SELECT * FROM hier; -- WHERE depth = v_depth;

    SET v_depth:= v_depth - 1;          

    -- SELECT taxonid as "multiple IDs?" FROM hier WHERE depth = v_depth;

    SET v_testNodeID = (SELECT taxonid FROM hier WHERE depth = v_depth LIMIT 1);  
	-- TODO; find a more graceful allowance for multiple hits here

    IF v_testNodeID = 1 then   -- IS NULL THEN
        SET v_done = 1;
    END IF;
    -- SET v_done = 1;

END WHILE;

-- PREPARE statement to create/replace named temp table, copy contents into it?
SET @sql = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;
-- NOTE: just one statement allowed at a time! breaking this up...
SET @sql = CONCAT('ALTER TABLE hier RENAME ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;

DROP TEMPORARY TABLE IF EXISTS hier; -- just in case...
DROP TEMPORARY TABLE IF EXISTS tmp;

END #

DELIMITER ;


/*
 * getMostRecentCommonAncestor( p_taxonBid, p_taxonAid, destinationTableName )
 */

DROP PROCEDURE IF EXISTS getMostRecentCommonAncestor;

DELIMITER #

CREATE PROCEDURE getMostRecentCommonAncestor (IN p_taxonAid MEDIUMINT(8) UNSIGNED, IN p_taxonBid MEDIUMINT(8) UNSIGNED, IN destinationTableName VARCHAR(80))
BEGIN

-- Declarations must be at the very top of BEGIN/END block!
DECLARE v_done TINYINT UNSIGNED DEFAULT 0;
DECLARE v_depth SMALLINT DEFAULT 0;
DECLARE v_testNodeID MEDIUMINT(8) default p_taxonAid;

-- clear "local" temp tables for the ancestor paths of each node
DROP TEMPORARY TABLE IF EXISTS tmp;
DROP TEMPORARY TABLE IF EXISTS tmp2;
DROP TEMPORARY TABLE IF EXISTS v_pathA;
DROP TEMPORARY TABLE IF EXISTS v_pathB;

-- gather both paths for analysis
CALL getAllAncestors( p_taxonAid, "v_pathA" );
			SELECT * FROM v_pathA;

CALL getAllAncestors( p_taxonBid, "v_pathB" );
			SELECT * FROM v_pathB;

-- walk the paths "backwards" in depth (from 0) to get the most recent common ancestor
-- use a simple, one-shot query if possible
-- NOTE: either path works as the source table, since we're looking for an
--  element in common and depth is always relative
CREATE TEMPORARY TABLE tmp AS
    SELECT * FROM v_pathA  as a
	WHERE a.taxonid IN (SELECT taxonid FROM v_pathB);

CREATE TEMPORARY TABLE tmp2 ENGINE=memory SELECT * FROM tmp;
/* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */
DELETE FROM tmp WHERE depth < (SELECT MAX(depth) FROM tmp2);

-- PREPARE statement to create/replace named temp table, copy contents into it?
SET @sql = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;
-- NOTE: just one statement allowed at a time! breaking this up...
SET @sql = CONCAT('ALTER TABLE tmp RENAME ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;

DROP TEMPORARY TABLE IF EXISTS tmp; -- just in case
DROP TEMPORARY TABLE IF EXISTS tmp2;

-- clear other "local" temp tables to avoid confusion
DROP TEMPORARY TABLE IF EXISTS v_pathA;
DROP TEMPORARY TABLE IF EXISTS v_pathB;

END #

DELIMITER ;


/*
 * getMultitreeNodeID( p_node_id, p_source_tree )
 *
 * Translates any node ID (reckoned from its source tree) to its corresponding
 * node ID in the current multitree.
 */

DROP FUNCTION IF EXISTS getMultitreeNodeID;

DELIMITER #

CREATE FUNCTION getMultitreeNodeID (p_source_tree VARCHAR(20), p_source_node_id MEDIUMINT(8) UNSIGNED)
RETURNS MEDIUMINT(8)
NOT DETERMINISTIC
BEGIN
  -- Declarations must be at the very top of BEGIN/END block!
  DECLARE response MEDIUMINT(8);

  SELECT multitree_node_id 
    FROM node_identity 
    WHERE source_tree = p_source_tree AND source_node_id = p_source_node_id
    INTO response;

  -- fall back to NCBI node ID for unpinned nodes
  RETURN IFNULL( response, p_source_node_id );
END #

DELIMITER ;


/*
 * getCladeFromNode( p_multitree_node_id, destinationTableName, treeFilter )
 *
 * NOTE that this requires a multitree ID for the target node!
 *
 * TODO: treeFilter is optional, expecting values like
 *  'ALL_TREES'
 *  'ALL_PUBLIC'
 *  '{tree-id}[,{another_tree_id} ...]'
 */

DROP PROCEDURE IF EXISTS getCladeFromNode;

DELIMITER #

CREATE PROCEDURE getCladeFromNode (IN p_multitree_node_id MEDIUMINT(8), IN destinationTableName VARCHAR(80), IN treeFilter VARCHAR(200))
BEGIN

-- Declarations must be at the very top of BEGIN/END block!
DECLARE v_done TINYINT UNSIGNED DEFAULT 0;
DECLARE v_depth SMALLINT UNSIGNED DEFAULT 0;

DROP TEMPORARY TABLE IF EXISTS hier;
DROP TEMPORARY TABLE IF EXISTS tmp;

CREATE TEMPORARY TABLE hier (
 node_id MEDIUMINT(8), 
 parent_node_id MEDIUMINT(8), 
 depth SMALLINT(6) UNSIGNED DEFAULT 0
) ENGINE = memory;

INSERT INTO hier SELECT node_id, parent_node_id, v_depth FROM multitree WHERE node_id = p_multitree_node_id;

/* see http://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html */

CREATE TEMPORARY TABLE tmp ENGINE=memory SELECT * FROM hier;

WHILE NOT v_done DO


SELECT '======== v_depth ========';
SELECT v_depth;
SELECT '======== hier ========';
SELECT * FROM hier;


    IF EXISTS( SELECT 1 FROM multitree p INNER JOIN hier ON p.parent_node_id = hier.node_id AND hier.depth = v_depth) THEN

        INSERT INTO hier 
            SELECT p.node_id, p.parent_node_id, v_depth + 1 FROM multitree p 
            INNER JOIN tmp ON p.parent_node_id = tmp.node_id AND tmp.depth = v_depth;

        SET v_depth = v_depth + 1;          

        TRUNCATE TABLE tmp;
        INSERT INTO tmp SELECT * FROM hier WHERE depth = v_depth;

    ELSE
        SET v_done = 1;
    END IF;

    -- TODO: remove this!
    -- IF v_depth = 9 THEN SET v_done := 1; END IF;

END WHILE;
 
-- 
-- SELECT v_depth;
-- SELECT '======== tmp ========';
-- SELECT * FROM tmp;
-- SELECT '======== hier ========';
-- SELECT * FROM hier;
-- 

-- put resulting values into 'tmp' so we can rename+preserve them
INSERT INTO tmp SELECT 
 p.node_id,
 b.node_id AS parent_node_id,
 hier.depth
FROM 
 hier
INNER JOIN multitree p ON hier.node_id = p.node_id
LEFT OUTER JOIN multitree b ON hier.parent_node_id = b.node_id
ORDER BY
 hier.depth, hier.node_id;

-- PREPARE statement to create/replace named temp table, copy contents into it?
-- SET @sqlRenameTable = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, '; ALTER TABLE tmp RENAME ', destinationTableName, ';');
SET @sql = CONCAT('DROP TABLE IF EXISTS ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;
-- NOTE: just one statement allowed at a time! breaking this up...
SET @sql = CONCAT('ALTER TABLE tmp RENAME ', destinationTableName, ';');
PREPARE cmd FROM @sql;
EXECUTE cmd;
DEALLOCATE PREPARE cmd;

DROP TEMPORARY TABLE IF EXISTS hier;
DROP TEMPORARY TABLE IF EXISTS tmp; -- just in case...

END #

DELIMITER ;




/* 
 * Here's a typical query session, usable from the mysql command-line
 *
 * NOTE that in all cases, we can create a temporary table by passing its
 * (desired) name to a stored procedure. These can be disposed of explicitly,
 * or they'll disappear when we close the database session.
 */ 

-- normally we'd get these from user input, of course
SET @nodeA_id = 9606;	-- Homo sapiens
SET @nodeB_id = 349050;	-- Ficus casapiensis


-- SELECT will show variables, but requires a simple 'header' label or it looks dumb
SELECT CONCAT("=========================== MULTITREE ID for pinned NCBI node: ", @nodeA_id ," ===========================") AS "";

SET @multitreeID = getMultitreeNodeID( 'NCBI', @nodeA_id  );
SELECT @multitreeID;

SELECT CONCAT("=========================== MULTITREE ID for UN-pinned NCBI node: ", @nodeB_id ," ===========================") AS "";

SET @multitreeID = getMultitreeNodeID( 'NCBI', @nodeB_id  );
SELECT @multitreeID;

SELECT CONCAT("=========================== CLADE TEST for NCBI node: ", @nodeA_id ," ===========================") AS "";

SET @multitreeID = getMultitreeNodeID( 'NCBI', @nodeA_id  );

-- TODO: add tree_filter argument? eg, 'ALL_TREES', 'ALL_PUBLIC', or '{tree-id}[,{another_tree_id} ...]'
CALL getCladeFromNode( @multitreeID, "cladeA_ids", 'ALL_TREES' );
SELECT * FROM cladeA_ids;

/*

system echo "=========================== FULL INFO for clade members ==========================="

CALL getFullNodeInfo( "cladeA_ids", "cladeA_info" );
SELECT * FROM cladeA_info;


SELECT CONCAT("=========================== ALL ANCESTORS for node ID: ", @nodeA_id ," ===========================") AS "";

CALL getAllAncestors( @nodeA_id, "ancestorsA_ids" );
SELECT * FROM ancestorsA_ids;

system echo "=========================== FULL INFO for all ancestors ==========================="

CALL getFullNodeInfo( "ancestorsA_ids", "ancestorsA_info" );
SELECT * FROM ancestorsA_info;


system echo "=========================== COMMON ANCESTOR ==========================="

CALL getMostRecentCommonAncestor( @nodeA_id, @nodeB_id, "mostRecentCommonAncestor_ids" );
SELECT * FROM mostRecentCommonAncestor_ids;

system echo "=========================== FULL INFO for common ancestor ==========================="

CALL getFullNodeInfo( "mostRecentCommonAncestor_ids", "mostRecentCommonAncestor_info" );
SELECT * FROM mostRecentCommonAncestor_info;
*/
system echo "=========================== DONE ==========================="


