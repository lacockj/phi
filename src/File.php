<?php namespace Phi; class File {

public static function getCSV ( $filename, $hasHeadings ) {

  # Open file for reading. #
  if ( ( $fileHandle = fopen( $filename, "r" ) ) === false ) {
    throw new \Exception( "Could not open $filename for reading." );
  }

  # Vars #
  $data = array();
  $firstLine = true;
  $row = 0;
  $headings = null;
  $countHeadings = 0;

  # Read data. #
  while ( ( $line = fgetcsv( $fileHandle ) ) !== false ) {

    # Headings in first line? #
    if ( $hasHeadings && $firstLine ) {
      $headings = $line;
      $countHeadings = count($headings);
      $firstLine = false;
    }

    # Data Lines #
    else {

      # Use headings as array keys. #
      if ( $hasHeadings ) {
        for ($col=0; $col<$countHeadings; $col++) {
          $data[$row][$headings[$col]] = $line[$col];
        }
      }

      # No headings? Indexed array. #
      else {
        for ($col=0; $col<$countHeadings; $col++) {
          $data[$row][$col] = $line[$col];
        }
      }

      $row++;
    }

  }

  fclose( $fileHandle );
  return $data;

} # end of getCSV

} # end of class
