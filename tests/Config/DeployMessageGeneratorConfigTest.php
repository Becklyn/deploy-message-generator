<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\Config;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use PHPUnit\Framework\TestCase;

class DeployMessageGeneratorConfigTest extends TestCase
{

    private static DeployMessageGeneratorConfigurator $config;
    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        self::$config = new DeployMessageGeneratorConfigurator(dirname(__DIR__, 2) . '/examples');
    }


    public function testProjectName () : void
    {
        $projectName = "name-of-project";
        self::assertEquals($projectName, self::$config->getProjectName());
    }


    public function testValidEnvironment () : void
    {
        $environment = 'Staging';
        self::assertTrue(self::$config->isValidDeploymentStatus($environment));
        self::assertEquals($environment, self::$config->resolveDeploymentEnvironment($environment));
    }


    public function testValidAlias () : void
    {
        $environment = 'Staging';
        $alias = 'test';

        self::assertTrue(self::$config->isValidDeploymentStatus($alias));
        self::assertEquals($environment, self::$config->resolveDeploymentEnvironment($alias));
    }


    public function testInvalidEnvironmentOrAlias () : void
    {
        self::assertFalse(self::$config->isValidDeploymentStatus('foo'));
    }
}
