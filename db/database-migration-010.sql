-- change recent undefined publications to Private Draft
UPDATE publications SET PublicationStatus=1
    WHERE PublicationStatus IS NULL;

-- prevent this from happening again (new default = Private Draft)
ALTER TABLE publications CHANGE PublicationStatus
  PublicationStatus INT NOT NULL DEFAULT 1;
