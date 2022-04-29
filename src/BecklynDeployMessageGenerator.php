<?php declare(strict_types=1);

use Becklyn\DeployMessageGenerator\Commands\SendDeployMessageCommand;
use Becklyn\DeployMessageGenerator\Commands\ShowTicketInformationCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Runtime\GenericRuntime;

// Regular project installation
if (\is_file($autoloader = \dirname(__DIR__) . "/vendor/autoload.php"))
{
    require_once $autoloader;
}
// Composer global install
elseif (\is_file($autoloader = \dirname(__DIR__, 3) . "/autoload.php"))
{
    require_once $autoloader;
}

$app = static function (array $context) : Application {
    $home = "Windows" === \PHP_OS_FAMILY ? $context["USERPROFILE"] : $context["HOME"];
    (new Dotenv())->load("{$home}/.deploy-message-generator.env");
    $context += $_ENV;

    $name = "becklyn/deploy-message-generator";
    $version = InstalledVersions::getVersion($name) ?? "UNKNOWN";
    $application = new Application($name, $version);
    $application->add(new SendDeployMessageCommand($context));
    $application->add(new ShowTicketInformationCommand($context));

    return $application;
};

$runtime = new GenericRuntime(($_SERVER['APP_RUNTIME_OPTIONS'] ?? $_ENV['APP_RUNTIME_OPTIONS'] ?? []) + [
    "disable_dotenv" => true,
    "project_dir" => \dirname(__DIR__, 1),
]);

[$app, $args] = $runtime
    ->getResolver($app)
    ->resolve();

$app = $app(...$args);

exit(
    $runtime
        ->getRunner($app)
        ->run()
);
