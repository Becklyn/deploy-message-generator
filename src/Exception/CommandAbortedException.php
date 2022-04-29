<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Exception;

class CommandAbortedException extends \RuntimeException implements DeployMessageGeneratorExceptionInterface
{
}
