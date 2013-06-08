/* Add entries to geoltime table for general Periods and Epochs, using the
 * first matching StartAge in the current data.
 *
 * ASSUMES that all "source" records are present and fully resolved with
 * non-NULL Epoch and Age values
 */

-- remove any previous general records
DELETE FROM geoltime WHERE Age = NULL OR Age = '';

-- add entries with Period and Epoch only
INSERT INTO geoltime (Period, Epoch, Age, System, StartAge, EndAge, Timescale)
  SELECT DISTINCT
    Period,
    Epoch, 
    NULL, 
    System,
    MAX(StartAge),
    MIN(EndAge),
    Timescale
  FROM geoltime GROUP BY Period, Epoch;

-- add entries with Period only
INSERT INTO geoltime (Period, Epoch, Age, System, StartAge, EndAge, Timescale)
  SELECT DISTINCT
    Period,
    NULL, 
    NULL, 
    System,
    MAX(StartAge),
    MIN(EndAge),
    Timescale
  FROM geoltime GROUP BY Period;

