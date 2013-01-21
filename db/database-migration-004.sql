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
  'OK, this is not really an arthropod. :( '
);
 */


/*
 * Add a "singleton" table for site status, mostly regarding admin operations
 */
DROP TABLE IF EXISTS site_status;
CREATE TABLE site_status (
  last_build_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  needs_build TINYINT(1) DEFAULT 0,
  build_comment VARCHAR(255),
  last_NCBI_update_time TIMESTAMP NOT NULL,
  announcement_title VARCHAR(255),
  announcement_body VARCHAR(1024)
) ENGINE=InnoDB;
