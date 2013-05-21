--
-- REVISED Structure for view `View_Calibrations`
-- This will allow us to include incomplete calibrations (ie, without a node publication assigned)
--
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `View_Calibrations` AS 
SELECT 	`c`.`CalibrationID` AS `CalibrationID`,
	`c`.`NodeName` AS `NodeName`,
	`c`.`HigherTaxon` AS `HigherTaxon`,
	`c`.`MinAge` AS `MinAge`,
	`c`.`MinAgeExplanation` AS `MinAgeExplanation`,
	`c`.`MaxAge` AS `MaxAge`,
	`c`.`MaxAgeExplanation` AS `MaxAgeExplanation`,
	`c`.`DateCreated` AS `DateCreated`,
	`p`.`PublicationID` AS `PublicationID`,
	`p`.`ShortName` AS `ShortName`,
	`p`.`FullReference` AS `FullReference`,
	`p`.`DOI` AS `DOI` 
FROM (`calibrations` `c` LEFT OUTER JOIN `publications` `p` ON `c`.`NodePub` = `p`.`PublicationID`);
