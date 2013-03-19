/*
 * Separating mutable properties of a fossil from immutable (permanent) ones.
 */

-- duplicate existing columns from `fossils` to `Link_CalibrationFossil`
ALTER TABLE Link_CalibrationFossil
  ADD COLUMN (
    Species varchar(50) DEFAULT NULL COMMENT 'Lookup Field',
    MinAge double DEFAULT NULL,
    MinAgeType int(11) DEFAULT NULL COMMENT 'Lookup Field',
    MaxAge double DEFAULT NULL,
    MaxAgeType int(11) DEFAULT NULL COMMENT 'Lookup Field',
    PhyJustificationType int(11) DEFAULT NULL COMMENT 'Lookup Field',
    PhyJustification longtext,
    PhyloPub int(11) DEFAULT NULL COMMENT 'Lookup Field'
  );

-- migrate values from each fossil record to all its associated links
/*
INSERT INTO Link_CalibrationFossil (
  Species, 
  MinAge, MinAgeType, 
  MaxAge, MaxAgeType, 
  PhyJustificationType, PhyJustification, PhyloPub
)
SELECT 
  Species, 
  MinAge, MinAgeType, 
  MaxAge, MaxAgeType, 
  PhyJustificationType, PhyJustification, PhyloPub
FROM fossils
WHERE fossils.FossilID = Link_CalibrationFossil.FossilID;
*/
UPDATE Link_CalibrationFossil AS link, fossils AS fossil
SET 
  link.Species = fossil.Species,
  link.MinAge = fossil.MinAge,
  link.MinAgeType = fossil.MinAgeType,
  link.MaxAge = fossil.MaxAge,
  link.MaxAgeType = fossil.MaxAgeType,
  link.PhyJustificationType = fossil.PhyJustificationType,
  link.PhyJustification = fossil.PhyJustification,
  link.PhyloPub = fossil.PhyloPub
WHERE fossil.FossilID = link.FossilID;
  
-- clear the fields from `fossils`
ALTER TABLE fossils DROP COLUMN Species; 
ALTER TABLE fossils DROP COLUMN MinAge; 
ALTER TABLE fossils DROP COLUMN MinAgeType; 
ALTER TABLE fossils DROP COLUMN MaxAge; 
ALTER TABLE fossils DROP COLUMN MaxAgeType; 
ALTER TABLE fossils DROP COLUMN PhyJustificationType; 
ALTER TABLE fossils DROP COLUMN PhyJustification; 
ALTER TABLE fossils DROP COLUMN PhyloPub; 

-- add a uniqueness constraint to `fossils` on (CollectionAcro, CollectionNumber)
-- NOTE that this requires manual cleanup of any existing rows with duplicate values!
ALTER TABLE fossils ADD CONSTRAINT UNIQUE INDEX (CollectionAcro, CollectionNumber);

-- modify views that depend on these fossil-related columns

-- View_Fossils should omit values that vary by calibration
-- TODO: ...OR should we duplicate rows to show all values?
DROP VIEW IF EXISTS `View_Fossils`;
-- adapted from original definition in 'FossilCalibration.sql'
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `View_Fossils` AS 
SELECT 
    `f`.`FossilID` AS `FossilID`
   ,`f`.`CollectionAcro` AS `CollectionAcro`
   ,`f`.`CollectionNumber` AS `CollectionNumber`
   ,`f`.`FossilPub` AS `FossilPub`
   -- ,'?SPECIES?' AS `Species`
   -- ,'?MINAGE?' AS `FossilMinAge`
   -- ,'?MINAGETYPE?' AS `MinAgeType`
   -- ,'?MAXAGE?' AS `FossilMaxAge`
   -- ,'?MAXAGETYPE?' AS `MaxAgeType`
   -- ,'?PHYJUSTIFICATIONTYPE?' AS `PhyJustificationType`
   -- ,'?PHYJUSTIFICATION?' AS `PhyJustification`
   -- ,'?PHYLOPUB?' AS `PhyloPub`
   ,`VL`.`LocalityID` AS `LocalityID`
   ,`VL`.`LocalityName` AS `LocalityName`
   ,`VL`.`Country` AS `Country`
   ,`VL`.`LocalityNotes` AS `LocalityNotes`
   ,`VL`.`Stratum` AS `Stratum`
   ,`VL`.`StratumMinAge` AS `StratumMinAge`
   ,`VL`.`StratumMaxAge` AS `StratumMaxAge`
   ,`VL`.`PBDBCollectionNum` AS `PBDBCollectionNum`
   ,`VL`.`Age` AS `Age`
   ,`VL`.`Epoch` AS `Epoch`
   ,`VL`.`Period` AS `Period`
   ,`VL`.`System` AS `System`
   ,`VL`.`StartAge` AS `StartAge`
   ,`VL`.`EndAge` AS `EndAge`
   ,`VL`.`ShortName` AS `ShortName`
   ,`VL`.`FullReference` AS `FullReference` 
FROM (`fossils` `f` join `View_Localities` `VL`) WHERE (`f`.`LocalityID` = `VL`.`LocalityID`);


