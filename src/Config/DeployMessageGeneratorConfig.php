<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Config;

use Becklyn\DeployMessageGenerator\Exception\FileNotFoundException;
use Becklyn\DeployMessageGenerator\Exception\FormatException;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\ChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSytem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\GitVersionControlSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\VersionControlSystem;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\String\u;
use Symfony\Component\Yaml\Yaml;

class DeployMessageGeneratorConfig
{
    private array $config;
    private String $file;
    /** @var Array<string, string> */
    private static array $environments = [];

    public function __construct (?string $configDir = null)
    {
        if (empty($configDir))
        {
            $configDir = \getcwd();
        }

        if (false === $configDir)
        {
            throw new \Exception("Cannot read the path of the current working directory.");
        }

        $configFile = "{$configDir}/.deploy-message-generator.yaml";

        if (!\is_file($configFile))
        {
            throw new FileNotFoundException("Cannot locate .deploy-message-generator.yaml. Please run this command from the root of your project");
        }

        $this->file = $configFile;
        $this->config = Yaml::parseFile($configFile, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }


    public function getProjectName () : string
    {
        if (!isset($this->config["name"]))
        {
            throw new FormatException("The key \"name\" is not defined in the project's config file in \"{$this->file}\".");
        }
        return $this->config["name"];
    }


    public function getTicketSystem (SymfonyStyle $io, array $context) : TicketSystem
    {
        $systemName = $this->config["ticket-system"] ?? "jira";

        if (isset($this->config[$systemName]))
        {
            $context[$systemName] = $this->config[$systemName];
        }

        return new JiraTicketSytem($io, $context, $this);
    }


    public function getVersionControlSystem (SymfonyStyle $io, array $context) : VersionControlSystem
    {
        $systemName = $this->config["vcs"] ?? "git";

        if (isset($this->config[$systemName]))
        {
            $context[$systemName] = $this->config[$systemName];
        }

        return new GitVersionControlSystem($io, $context, $this);
    }


    public function getChatSystem (SymfonyStyle $io, array $context) : ChatSystem
    {
        $systemName = $this->config["chat-system"] ?? "slack";

        if (isset($this->config[$systemName]))
        {
            $context[$systemName] = $this->config[$systemName];
        }

        return new SlackChatSystem($io, $context, $this);
    }


    /**
     * @internal
     */
    public function getConfigFor (string $systemName) : array
    {
        if (empty($this->config[$systemName]))
        {
            return [];
        }

        return $this->config[$systemName];
    }


    public function isValidDeploymentStatus (string $deploymentStatus) : bool
    {
        return null !== $this->getDeploymentEnvironmentFor($deploymentStatus);
    }

    public function getDeploymentEnvironmentFor (string $environmentOrAlias) : ?string
    {
        if (empty(self::$environments))
        {
            foreach ($this->config["server"] as $environment => $aliases)
            {
                $environment = u($environment)->camel()->title()->toString();
                self::$environments[$environment] = $environment;

                if (empty($aliases))
                {
                    continue;
                }

                foreach ($aliases as $alias)
                {
                    $alias = u($alias)->camel()->title()->toString();
                    self::$environments[$alias] = $environment;
                }

            }
        }

        $environmentOrAlias = u($environmentOrAlias)->camel()->title()->toString();

        if (isset(self::$environments[$environmentOrAlias]))
        {
            return self::$environments[$environmentOrAlias];
        }

        return null;
    }
}
