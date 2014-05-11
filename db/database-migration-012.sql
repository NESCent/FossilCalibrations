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

/*
 * Adding new field for relative fossil location, and a set of values for this.
 */

ALTER TABLE Link_CalibrationFossil
  ADD COLUMN FossilLocationRelativeToNode int(11) DEFAULT NULL COMMENT 'Lookup Field' AFTER DateCreated;

-- create lookup table IF it is not already here
CREATE TABLE  IF NOT EXISTS  L_FossilRelativeLocation (
    RelLocationID  INT  NOT NULL  AUTO_INCREMENT,
    RelLocation  VARCHAR(50)  NOT NULL,
    PRIMARY KEY(RelLocationID)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- populate with initial values
INSERT INTO L_FossilRelativeLocation VALUES(1, 'Stem');
INSERT INTO L_FossilRelativeLocation VALUES(2, 'Crown');
INSERT INTO L_FossilRelativeLocation VALUES(3, 'Unknown');

