/*
 * Add a simple, isolated table for publication images
 */

DROP TABLE IF EXISTS publication_images;
CREATE TABLE publication_images (
  PublicationID int(11) NOT NULL,
  image MEDIUMBLOB NOT NULL,
  caption VARCHAR(255) NOT NULL,

  PRIMARY KEY (PublicationID)
) ENGINE=InnoDB;

/*
 * Add a single test image

INSERT INTO publication_images VALUES (
  61,
  LOAD_FILE('/opt/lampp/htdocs/fossil-calibration/images/archosauria.jpeg'),
  'I suspect this is not really an arthropod. :('
);
 */
