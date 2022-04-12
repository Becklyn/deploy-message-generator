<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Exception;

class InvalidDeploymentEnvironmentException extends \RuntimeException
{
    /**
     * @param string[] $allowedEnvironments
     */
    public function __construct (
        ?string $environment,
        array $allowedEnvironments,
        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct(
            \sprintf(
                "Invalid environment '%s' received." . \PHP_EOL . "Only the environments '%s' are defined in the  \".deploy-message-generator.yaml\" file?",
                $environment ?? "null",
                \implode("', '", $allowedEnvironments)
            ),
            $code,
            $previous
        );
    }
}
