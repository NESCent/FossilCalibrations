/*
 * Add enumerated values for calibration status
 */

-- create lookup table IF it is not already here
CREATE TABLE  IF NOT EXISTS  L_PublicationStatus (
    PubStatusID  INT  NOT NULL  AUTO_INCREMENT,
    PubStatus  VARCHAR(50)  NOT NULL,
    PRIMARY KEY(PubStatusID)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- populate with initial values
INSERT INTO L_PublicationStatus VALUES(1, 'Private Draft');
INSERT INTO L_PublicationStatus VALUES(2, 'Under Revision');
INSERT INTO L_PublicationStatus VALUES(3, 'Ready for Publication');  
    -- data is ready, but main publication is not
INSERT INTO L_PublicationStatus VALUES(4, 'Published');



/*
 * Add enumerated values for calibration quality (allowing for some
 * combinations)
 */ 

-- create lookup table IF it's not already here
CREATE TABLE  IF NOT EXISTS  L_CalibrationQuality (
    QualityID  INT  NOT NULL  AUTO_INCREMENT,
    Quality  VARCHAR(50)  NOT NULL,
    PRIMARY KEY(QualityID)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- populate with initial values
INSERT INTO L_CalibrationQuality VALUES(1, 'Current');
INSERT INTO L_CalibrationQuality VALUES(2, 'Contains known factual errors');
INSERT INTO L_CalibrationQuality VALUES(3, 'Out of date');
INSERT INTO L_CalibrationQuality VALUES(4, 'Withdrawn');



/*
 * Add fields with these foreign-key constraints to the existing
 * 'calibration' table.
 */
ALTER TABLE calibrations 
    ADD COLUMN PublicationStatus INT AFTER NodePub;
ALTER TABLE calibrations 
    ADD CONSTRAINT  FOREIGN KEY (PublicationStatus) 
    REFERENCES L_PublicationStatus(PubStatusID);
-- set this field to default 'Published' (vs. initial NULL)
UPDATE calibrations SET PublicationStatus=4
    WHERE PublicationStatus IS NULL;

ALTER TABLE calibrations  
    ADD COLUMN CalibrationQuality INT AFTER PublicationStatus;
ALTER TABLE calibrations  
    ADD CONSTRAINT  FOREIGN KEY (CalibrationQuality) 
        REFERENCES L_CalibrationQuality(QualityID);
-- set this field to default 'Current' (vs. initial NULL)
UPDATE calibrations SET CalibrationQuality=1
    WHERE CalibrationQuality IS NULL;



/*
 * Add an open-ended text field for admin notes, esp. regarding changes 
 * to a calibration's status and quality.
 */
ALTER TABLE calibrations  
    ADD COLUMN AdminComments LONGTEXT AFTER CalibrationQuality;

