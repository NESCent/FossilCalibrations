/*
 * Needed improvements to some existing tabls
 *
 * Some of these changes have been copied to earlier scripts (as noted below)
 *
 */

ALTER TABLE NCBI_names ADD KEY (uniquename);
-- copied to database-migration-002.sql

-- add publication_status column directly to publications
ALTER TABLE publications
    ADD COLUMN PublicationStatus INT AFTER `DOI`;
ALTER TABLE publications 
    ADD CONSTRAINT  FOREIGN KEY (PublicationStatus) 
    REFERENCES L_PublicationStatus(PubStatusID);
-- set this field to default 'Published' (vs. initial NULL)
UPDATE publications SET PublicationStatus=4
    WHERE PublicationStatus IS NULL;

-- changes to this field should be mirrored in all related calibrations
DROP TRIGGER IF EXISTS push_pub_status;
CREATE TRIGGER push_pub_status 
	AFTER UPDATE ON publications 
	FOR EACH ROW 
	UPDATE calibrations SET PublicationStatus = NEW.PublicationStatus WHERE NodePub = NEW.PublicationID;




