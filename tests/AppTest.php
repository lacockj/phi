<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase {

  protected $app;

  protected function setUp(): void
  {
    $this->app = new \Phi\App();
  }

  public function testAppExists()
  {
    $this->assertIsObject( $this->app );
  }

  public function testTempDirectory()
  {
    $this->assertIsString( $this->app->tempDir );
  }

  public function testAutoloadDirectories()
  {
    $this->assertIsArray( $this->app->autoloadDirs );
  }

  public function testRoutesBase()
  {
    $this->assertIsString( $this->app->routeBase );
    $this->assertEquals( $this->app->routeBase, "" );
  }

  public function testRoutesIni()
  {
    $this->assertIsString( $this->app->routesINI );
    $this->assertEquals( $this->app->routesINI, "" );
  }

  public function testAllowedOrigins()
  {
    $this->assertNull( $this->app->allowedOrigins );
  }

  // Magic Tools Convenience Functions //

  public function testPathTo() {

    $this->assertIsString( $this->app->pathTo('.') );
    $this->assertFalse( $this->app->pathTo('fileNameThatDoesntExist') );

  }

  public function testStringPop() {

    $aString = "one two three";
    $aNumber = 123;
    $anArray = array('one', 'two', 'three');

    $this->assertEquals( "three", $this->app->str_pop( $aString ) );
    $this->assertEquals( "one two", $aString );

    $this->assertEquals( "two", $this->app->str_pop( $aString ) );
    $this->assertEquals( "one", $aString );

    $this->assertEquals( "one", $this->app->str_pop( $aString ) );
    $this->assertEquals( "", $aString );

    $this->assertFalse( $this->app->str_pop( $aString ) );
    $this->assertFalse( $this->app->str_pop( $aNumber ) );
    $this->assertFalse( $this->app->str_pop( $anArray ) );

  }

}

?>