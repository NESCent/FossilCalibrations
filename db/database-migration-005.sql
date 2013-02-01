/*
 * Needed improvements to some existing tabls
 *
 * Some of these changes have been copied to earlier scripts (as noted below)
 *
 */

ALTER TABLE NCBI_names ADD KEY (uniquename);
-- copied to database-migration-002.sql

