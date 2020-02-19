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

}

?>