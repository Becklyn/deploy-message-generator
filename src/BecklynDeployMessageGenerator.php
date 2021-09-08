<?php declare(strict_types=1);

// This file will probably not be called from within symfony therefore we need to manually import the autoload
require_once __DIR__ . "/vendor/autoload.php";

use Composer\InstalledVersions;
use Symfony\Component\Console\Application;

$name = "becklyn/deploy-message-generator";
$version = InstalledVersions::getVersion($name) ?? "UNKNOWN";

$application = new Application($name, $version);

// TODO register commands

$application->run();
