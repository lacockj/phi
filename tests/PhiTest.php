<?php

class PhiTest extends PHPUnit_Framework_TestCase {

  public function testGetters() {

    global $phi;

    $this->assertEquals( "/com.lakehawksolutions.Phi", $phi->tempDir );

  }

  public function testStrpop() {

    global $phi;
    $aString = "one two three";
    $aNumber = 123;
    $anArray = array('one','two');

    $this->assertEquals( "one", $phi->strpop( $aString ) );
    $this->assertEquals( "two three", $aString );

    $this->assertEquals( "two", $phi->strpop( $aString ) );
    $this->assertEquals( "three", $aString );

    $this->assertEquals( "three", $phi->strpop( $aString ) );
    $this->assertEquals( "", $aString );

    $this->assertFalse( $phi->strpop( $aString ) );
    $this->assertFalse( $phi->strpop( $aNumber ) );
    $this->assertFalse( $phi->strpop( $anArray ) );

  }

  public function testArrayCopy() {

    global $phi;

    $referencedArray = array( "a" => "apple", "b" => "banana" );
    $original = array( &$referencedArray );
    $copy = $phi->array_copy( $original );
    $copy[0]["b"] = "berries";

    $this->assertEquals( array( array( "a" => "apple", "b" => "banana" ) ), $original );
    $this->assertEquals( array( array( "a" => "apple", "b" => "berries" ) ), $copy );

  }

  public function testAllSet() {

    global $phi;

    $uriParams = array(
      'user_id'   => "bob",
      'record_id' => 123,
      'empty_string' => "",
      'null_value' => null,
      'zero_value' => 0
    );

    $this->assertTrue(  $phi->all_set( $uriParams, 'user_id', 'record_id' ) );
    $this->assertTrue(  $phi->all_set( $uriParams ) );

    $this->assertFalse( $phi->all_set( $uriParams, 'user_id', 'record_id', 'missing_key' ) );
    $this->assertFalse( $phi->all_set( $uriParams, 'user_id', 'record_id', 'empty_string' ) );
    $this->assertFalse( $phi->all_set( $uriParams, 'user_id', 'record_id', 'null_value' ) );
    $this->assertFalse( $phi->all_set( $uriParams, 'user_id', 'record_id', 'zero_value' ) );
    
    $this->assertNull(  $phi->all_set( "not_an_array" ) );
    $this->assertNull(  $phi->all_set( ) );

  }

}

?>