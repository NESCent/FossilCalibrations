/*
 * Test of current data and logic, adding and searching a calibration
 */

-- use existing publication
SET @pubID = 24;  -- Polly, 2009

-- use an existing fossil
SET @fossilID = 6; -- Canis extinctis
  -- NOTE: This appears to include age boundaries and justification!?
  -- Doesn't this represent a particular specimen? Is there an existing ID or
  --   serial number for these, maybe (fossil.CollectionAcro, fossil.CollectionNumber)?

-- add a new calibration with minimal data (clobber if already found)
DELETE FROM calibrations WHERE NodeName = 'Test Node' AND NodePub = @pubID;

INSERT INTO calibrations 
SET
  NodeName = 'Test Node',     -- CAN BE NULL  -- TODO: drop this, in favor of assigned tree > root node > name?
  -- HigherTaxon = 'Mammalia',   -- ignore/remove this?
--   MinAge = 13, 
--   MinAgeExplanation = 'Seems reasonable to me.', 
--   MaxAge = 17, 
--   MaxAgeExplanation = 'Another prime number...', 
  NodePub = @pubID,  -- from above
  PublicationStatus = 1   -- Private Draft (TODO: make this default!?)
--   ,CalibrationQuality = 1  -- Current
--   ,AdminComments = 'This is a test calibration.'
  -- ,DateCreated = DEFAULT
;

SET @calibrationID = LAST_INSERT_ID();

SELECT 'new calibration';
SELECT * FROM calibrations WHERE CalibrationID = @calibrationID;

-- add a new tree for this calibration (clobber if already found)
DELETE FROM FCD_trees WHERE comments LIKE '% test tree';

INSERT INTO FCD_trees
SET
  -- tree_id  -- TODO: make this AUTO_INCREMENT
   root_node_id = NULL
  ,calibration_id = @calibrationID
  ,comments = 'This is an empty test tree'
  ,is_public_tree = 0   -- (SELECT MAX(PublicationStatus) FROM publications WHERE PublicationID = @pubID)
;

SET @treeID = LAST_INSERT_ID();

SELECT 'new tree';
SELECT * FROM FCD_trees WHERE calibration_id = @calibrationID;

-- add nodes for this tree (remove all if already found)
DELETE FROM FCD_nodes WHERE comments LIKE '% node for test tree%';

INSERT INTO FCD_nodes
SET
   parent_node_id = NULL
  ,comments = CONCAT('root node for test tree ', @treeID)
  ,tree_id = @treeID
;

SET @rootNodeID = LAST_INSERT_ID();

-- assign this root node to the new tree
-- TODO: re-order operations w/ nodes first, then tree?
UPDATE FCD_trees
SET
   root_node_id = @rootNodeID
  ,comments = 'This is a populated test tree'
WHERE
  tree_id = @treeID
;

-- assign a name for the root node?
DELETE FROM FCD_names WHERE name LIKE '%Test ';

INSERT INTO FCD_names
SET
  node_id = @rootNodeID
 ,name = 'Test (root node)'
 ,uniquename = NULL
 ,class = ''   -- TODO: allow NULL here, or define acceptable values?
;

--
-- more nodes (and some names) for this tree
--

-- first-level child (a "deep" node)
INSERT INTO FCD_nodes
SET
   parent_node_id = @rootNodeID
  ,comments = CONCAT('1st-gen child, deep node for test tree ', @treeID)
  ,tree_id = @treeID
;
SET @deepChildID = LAST_INSERT_ID();
INSERT INTO FCD_names
SET
  node_id = @deepChildID
 ,name = 'Test (deep child)'
 ,uniquename = NULL
 ,class = ''   -- TODO: allow NULL here, or define acceptable values?
;

-- pin this to an existing NCBI node
DELETE FROM pinned_nodes WHERE comments LIKE '% test tree';

SET @NCBIDeepNodeID = 9443;  -- NCBI entry for Primates
-- CALL getCladeFromNode( @NCBIDeepNodeID, 'clade_node_test');
-- SELECT 'Clade from Primates';
-- CALL getFullNodeInfo('clade_node_test', 'clade_full_info');
-- SELECT * FROM clade_full_info;
SET @NCBISpeciesA_ID = 9606;   -- NCBI entry for Homo sapiens
SET @NCBISpeciesB_ID = 1159185;   -- NCBI entry for Gorilla beringei beringei

INSERT INTO pinned_nodes
SET
  target_tree = 'NCBI'  -- pinning a new node to one on this tree
 ,target_node_id = @NCBIDeepNodeID     -- pinning TO this existing node (Homo)
 ,pinned_tree = CONCAT('FCD-', @treeID)    -- TODO: standardize tree IDs?
 ,pinned_node_id = @deepChildID
 ,calibration_id = @calibrationID
 ,comments = 'pinned deep node from test tree'
 ,is_public_node = 0  -- refers to the pinned (newly submitted) node
;

-- extant species (child of deep node)
INSERT INTO FCD_nodes
SET
   parent_node_id = @deepChildID
  ,comments = CONCAT('2nd-gen child, extant species node for test tree ', @treeID)
  ,tree_id = @treeID
;
SET @extantSpeciesA_ID = LAST_INSERT_ID();

INSERT INTO pinned_nodes
SET
  target_tree = 'NCBI'  -- pinning a new node to one on this tree
 ,target_node_id = @NCBISpeciesA_ID      -- pinning TO this existing node (Homo sapiens)
 ,pinned_tree = CONCAT('FCD-', @treeID)    -- TODO: standardize tree IDs?
 ,pinned_node_id = @extantSpeciesA_ID
 ,calibration_id = @calibrationID
 ,comments = 'pinned extant species A from test tree'
 ,is_public_node = 0  -- refers to the pinned (newly submitted) node
;


-- extant species (child of root node)
INSERT INTO FCD_nodes
SET
   parent_node_id = @rootNodeID
  ,comments = CONCAT('1st-gen child, extant species node for test tree ', @treeID)
  ,tree_id = @treeID
;
SET @extantSpeciesB_ID = LAST_INSERT_ID();

INSERT INTO pinned_nodes
SET
  target_tree = 'NCBI'  -- pinning a new node to one on this tree
 ,target_node_id = @NCBISpeciesB_ID      -- pinning TO this existing node (Homo sapiens)
 ,pinned_tree = CONCAT('FCD-', @treeID)    -- TODO: standardize tree IDs?
 ,pinned_node_id = @extantSpeciesB_ID
 ,calibration_id = @calibrationID
 ,comments = 'pinned extant species B from test tree'
 ,is_public_node = 0  -- refers to the pinned (newly submitted) node
;

SELECT 'new nodes';
SELECT * FROM FCD_nodes WHERE tree_id = @treeID;

SELECT 'pinned nodes';
SELECT * FROM pinned_nodes WHERE pinned_tree = CONCAT('FCD-',@treeID);


-- TODO: LATER: pin to *other* FCD nodes (requires multiple calibrations)

-- rebuild all quick-search tables
system echo "=========================== BEFORE rebuilding node-identity table ==========================="
CALL refreshNodeIdentity('FINAL'); 
system echo "=========================== BEFORE rebuilding multitree table ==========================="
CALL refreshMultitree('FINAL');
system echo "=========================== AFTER rebuilding quick-search tables ==========================="

/*
-- test tip-taxa queries against the new multitree

-- SELECT CONCAT("=========================== CLADE TEST for Primates: ", @deepChildID ," ===========================") AS "";
-- CALL getCladeFromNode( @deepChildID, "clade_ids" );
-- SELECT * FROM clade_ids;

-- system echo "=========================== FULL INFO for clade members ==========================="
-- CALL getFullNodeInfo( "clade_ids", "clade_info" );
-- SELECT * FROM clade_info;


SELECT CONCAT("=========================== ALL ANCESTORS for Homo sapiens: ", @NCBISpeciesA_ID ," ===========================") AS "";

CALL getAllAncestors( @NCBISpeciesA_ID, "ancestorsA_ids" );
SELECT * FROM ancestorsA_ids;

system echo "=========================== FULL INFO for all ancestors ==========================="

CALL getFullNodeInfo( "ancestorsA_ids", "ancestorsA_info" );
SELECT * FROM ancestorsA_info;


system echo "=========================== COMMON ANCESTOR ==========================="

CALL getMostRecentCommonAncestor( @NCBISpeciesA_ID, @NCBISpeciesB_ID, "mostRecentCommonAncestor_ids" );
SELECT * FROM mostRecentCommonAncestor_ids;

system echo "=========================== FULL INFO for common ancestor ==========================="

CALL getFullNodeInfo( "mostRecentCommonAncestor_ids", "mostRecentCommonAncestor_info" );
SELECT * FROM mostRecentCommonAncestor_info;
*/

system echo "=========================== DONE ==========================="




