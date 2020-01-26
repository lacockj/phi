<?php namespace Phi; class HTML {

  static public function table ( $data ) {
    echo '<table><tbody>', PHP_EOL;
    if ( get_class( $data ) === "Phi\Datastream" ) {
      $rowNum = 1;
      while ( $row = $data->fetch_assoc() ) {
        if ( $rowNum === 1 ) {
          echo '<tr>';
          foreach ( $row as $key => $value ) {
            echo '<th>', $key, '</th>';
          }
          echo '</tr>', PHP_EOL;
        }
        echo '<tr>';
        foreach ( $row as $key => $value ) {
          echo '<td>', $value, '</td>';
        }
        echo '</tr>', PHP_EOL;
        $rowNum++;
      }
    }
    elseif ( is_array( $data ) ) {
      if (! is_array($data[0]) ) $data = array( $data );
      $keys = array_keys( $data[0] );
      echo '<tr><th>' . implode('</th><th>', $keys) . '</th></tr>' . PHP_EOL;
      foreach ($data as $row) {
        echo '<tr><td>' . implode('</td><td>', $row) . '</td></tr>' . PHP_EOL;
      }
    }
    echo '</tbody></table>', PHP_EOL;
  }

}

?>