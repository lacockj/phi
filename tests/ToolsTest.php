<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Phi\Tools;

final class ToolsTest extends TestCase {

  public function testPathTo() {

    $this->assertIsString( Tools::pathTo('.') );
    $this->assertFalse( Tools::pathTo('fileNameThatDoesntExist') );

  }

  public function testStringPop() {

    $aString = "one two three";
    $aNumber = 123;
    $anArray = array('one', 'two', 'three');

    $this->assertEquals( "three", Tools::str_pop( $aString ) );
    $this->assertEquals( "one two", $aString );

    $this->assertEquals( "two", Tools::str_pop( $aString ) );
    $this->assertEquals( "one", $aString );

    $this->assertEquals( "one", Tools::str_pop( $aString ) );
    $this->assertEquals( "", $aString );

    $this->assertFalse( Tools::str_pop( $aString ) );
    $this->assertFalse( Tools::str_pop( $aNumber ) );
    $this->assertFalse( Tools::str_pop( $anArray ) );

  }

  public function testStringShift() {

    $aString = "one two three";
    $aNumber = 123;
    $anArray = array('one', 'two', 'three');

    $this->assertEquals( "one", Tools::str_shift( $aString ) );
    $this->assertEquals( "two three", $aString );

    $this->assertEquals( "two", Tools::str_shift( $aString ) );
    $this->assertEquals( "three", $aString );

    $this->assertEquals( "three", Tools::str_shift( $aString ) );
    $this->assertEquals( "", $aString );

    $this->assertFalse( Tools::str_shift( $aString ) );
    $this->assertFalse( Tools::str_shift( $aNumber ) );
    $this->assertFalse( Tools::str_shift( $anArray ) );

  }

  public function testArrayCopy() {

    $referencedArray = ["a" => "apple", "b" => "banana"];
    $original = [&$referencedArray];
    $copy = Tools::array_copy( $original );
    $copy[0]["b"] = "berries";

    $this->assertEquals( [["a" => "apple", "b" => "banana"]], $original );
    $this->assertEquals( [["a" => "apple", "b" => "berries"]], $copy );

  }

  public function testArrayIsListOnList() {

    $a = ['a', 'b', 'c'];

    $this->assertTrue( Tools::array_is_list( $a ) );

  }

  public function testArrayIsListOnAssoc() {

    $a = ['a' => 'a', 'b' => 'b', 'c' => 'c'];

    $this->assertFalse( Tools::array_is_list( $a ) );

  }

  public function testAllSet() {

    $uriParams = array(
      'user_id'   => "bob",
      'record_id' => 123,
      'empty_string' => "",
      'null_value' => null,
      'zero_value' => 0
    );

    $this->assertTrue(  Tools::all_set( $uriParams, 'user_id', 'record_id' ) );
    $this->assertTrue(  Tools::all_set( $uriParams ) );

    $this->assertFalse( Tools::all_set( $uriParams, 'user_id', 'record_id', 'missing_key' ) );
    $this->assertFalse( Tools::all_set( $uriParams, 'user_id', 'record_id', 'empty_string' ) );
    $this->assertFalse( Tools::all_set( $uriParams, 'user_id', 'record_id', 'null_value' ) );
    $this->assertFalse( Tools::all_set( $uriParams, 'user_id', 'record_id', 'zero_value' ) );
    
    $this->assertNull(  Tools::all_set( "not_an_array" ) );
    $this->assertNull(  Tools::all_set( ) );

  }

}

?>