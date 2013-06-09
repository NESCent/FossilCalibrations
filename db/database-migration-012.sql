/*
 * Adding new values for min/max date type (for fossils), and a new field to
 * describe other types (or multiple types)
 */

INSERT INTO `L_agetypes` VALUES(3, 'paleomagnetism');
INSERT INTO `L_agetypes` VALUES(4, 'cyclostratigraphy');
INSERT INTO `L_agetypes` VALUES(5, 'other (please specify)');

ALTER TABLE Link_CalibrationFossil
  ADD COLUMN MinAgeTypeOtherDetails varchar(300) DEFAULT '' AFTER MinAgeType;

ALTER TABLE Link_CalibrationFossil
  ADD COLUMN MaxAgeTypeOtherDetails varchar(300) DEFAULT '' AFTER MaxAgeType;

ALTER TABLE Link_CalibrationFossil
  ADD COLUMN TieDatesToGeoTimeScaleBoundary tinyint(1) DEFAULT 0 AFTER MaxAgeTypeOtherDetails;
