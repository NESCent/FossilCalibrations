-- BEFORE updating the NCBI tables, call the previously added procedure.
-- This will record the NCBI lineage of every pinned node in the system for
-- later comprison.
-- 
-- EXAMPLE: mysql -uroot -p < stash-before-NCBI-update.sql
-- 
-- For more explanation, see the README at
-- https://github.com/NESCent/FossilCalibrations/tree/master/ncbi-update/README.md
USE FossilCalibration;

CALL stashPinnedLineages('BEFORE');
