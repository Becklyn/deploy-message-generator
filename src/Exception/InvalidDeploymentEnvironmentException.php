<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Exception;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;

class InvalidDeploymentEnvironmentException extends \RuntimeException
{
    public function __construct (?string $deploymentStatus, DeployMessageGeneratorConfig $config, $code = 0, ?\Throwable $previous = null)
    {
        $deploymentStatus ??= "null";
        /// Fetch all environments as formatted string: 'env1', 'env2', 'env3'
        $environments = \implode(', ', \array_map(fn($e) => "'{$e}'", $config->getAllEnvironments()));
        $message = "Invalid environment '{$deploymentStatus}' received." . \PHP_EOL . "Only the environments {$environments} are defined in the  \".deploy-message-generator.yaml\" file?";

        parent::__construct($message, $code, $previous);
    }
}
