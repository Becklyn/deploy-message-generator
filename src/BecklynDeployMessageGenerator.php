#!/usr/bin/env php
<?php declare(strict_types=1);

// Manually bootstrap to ensure all environment variables can be read correctly
// Also includes the autoloading
include_once '../bootstrap.php';

use Becklyn\DeployMessageGenerator\Commands\SendDeployMessageCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Application;

$name = "becklyn/deploy-message-generator";
$version = InstalledVersions::getVersion($name) ?? "UNKNOWN";

$application = new Application($name, $version);

$application->add(new SendDeployMessageCommand());

$application->run();
