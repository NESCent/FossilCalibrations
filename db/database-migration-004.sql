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
  -- timestamps for each long-running task
  last_autocomplete_update TIMESTAMP NOT NULL,
  last_multitree_update TIMESTAMP NOT NULL,
  last_NCBI_update TIMESTAMP NOT NULL,
  -- status for each long-running task ('Up to date', 'Updating now', 'Needs update')
  autocomplete_status VARCHAR(50) NOT NULL DEFAULT 'Up to date',
  multitree_status VARCHAR(50) NOT NULL DEFAULT 'Up to date',
  NCBI_status VARCHAR(50) NOT NULL DEFAULT 'Up to date',
  -- flags to flip when underlying data changes
  needs_autocomplete_build TINYINT(1) DEFAULT 0,
  needs_multitree_build TINYINT(1) DEFAULT 0,
  -- scratch-pad for admin comments
  last_update_comment VARCHAR(1024),
  -- current site-wide announcement
  announcement_title VARCHAR(255),
  announcement_body VARCHAR(1024)
) ENGINE=InnoDB;

-- insert the singleton record with sensible defaults
INSERT INTO site_status SET
  last_autocomplete_update = CURRENT_TIMESTAMP,
  last_multitree_update = CURRENT_TIMESTAMP,
  last_NCBI_update = CURRENT_TIMESTAMP,
  last_update_comment = '',
  announcement_title = '',
  announcement_body = ''
;
