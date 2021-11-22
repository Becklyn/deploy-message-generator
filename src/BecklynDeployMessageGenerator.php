<?php declare(strict_types=1);

use Becklyn\DeployMessageGenerator\Commands\SendDeployMessageCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Application;

require \dirname(__DIR__) . "/vendor/autoload_runtime.php";

return function (array $context)
{
    $home = "Windows" === \PHP_OS_FAMILY ? $context["USERPROFILE"] : $context["HOME"];
    (new \Symfony\Component\Dotenv\Dotenv())->load("{$home}/.deploy-message-generator.env");
    $context += $_ENV;

    $name = "becklyn/deploy-message-generator";
    $version = InstalledVersions::getVersion($name) ?? "UNKNOWN";
    $application = new Application($name, $version);
    $application->add(new SendDeployMessageCommand($context));

    return $application;
};
