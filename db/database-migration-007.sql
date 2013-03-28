/*
 * Add a table for node-definition hints. These should help us to concisely 
 * locate a calibrated node within the NCBI tree (for easy discovery).
 *
 * This data will support the creation of an FCD tree, whose nodes will mostly
 * be pinned to NCBI nodes. But here we just want to preserve the arrangement
 * of taxon-names entered by the user, and the matching nodes (if any) for each.
 * In principle, these could come from any tree, but most likely they're 
 * directly matching NCBI nodes, either extant taxa or higher-order
 * classifications.
 */

DROP TABLE IF EXISTS node_placement_hints;
DROP TABLE IF EXISTS node_definitions;
CREATE TABLE node_definitions (
  calibration_id INT(11) NOT NULL,	-- CONFIRM
  definition_side ENUM('A','B') NOT NULL,
  matching_name VARCHAR(255) NOT NULL,	-- user-entered text AS MATCHED(?)
  -- specify the matching node (emulating the `node_identity` table)
  source_tree VARCHAR(20) NOT NULL,	-- eg, 'NCBI' or 'FCD-135' (or just 'FCD'?)
  source_node_id MEDIUMINT(8) UNSIGNED NOT NULL,
  operator ENUM('+','-') DEFAULT '+' NOT NULL,
  display_order TINYINT NOT NULL, 

  KEY (calibration_id, definition_side)
) ENGINE=InnoDB;

/*
 * Add a single set of rules to test. This points to the most recent common
 * ancestor (MRCA) of humans and carnivores (but not cats, presumably a
 * renegade taxonomist).
 */

INSERT INTO node_definitions VALUES (
  103,
  'A',
  'Homo sapiens', 
  'NCBI',
  9606, -- matching taxonid in NCBI
  '+',
  1
);
INSERT INTO node_definitions VALUES (
  103,
  'B',
  'Carnivora',
  'NCBI',
  33554,
  '+',
  1
);
INSERT INTO node_definitions VALUES (
  103,
  'B',
  'Felidae',
  'NCBI',
  9681,
  '-',
  2
);


