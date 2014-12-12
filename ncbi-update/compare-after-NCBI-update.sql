-- AFTER updating the NCBI tables, call the previously added procedure.
-- This should show a "watch list" of calibrations whose nodes are pinned to
-- changing areas of the NCBI taxonomy.
-- 
-- EXAMPLE: mysql -uroot -p < compare-after-NCBI-update.sql
-- 
-- For more explanation, see the README at
-- https://github.com/NESCent/FossilCalibrations/tree/master/ncbi-update/README.md
USE FossilCalibration;

CALL stashPinnedLineages('AFTER');
