<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Config;

use Becklyn\DeployMessageGenerator\Exception\FileNotFoundException;
use Becklyn\DeployMessageGenerator\Exception\IOException;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\ChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\GitVersionControlSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\VersionControlSystem;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\String\u;
use Symfony\Component\Yaml\Yaml;

class DeployMessageGeneratorConfigurator
{
    private array $config;

    /** @var array<string, string> */
    private array $environments;
    private ?TicketSystem $ticketSystem = null;
    private ?VersionControlSystem $versionControlSystem = null;
    private ?ChatSystem $chatSystem = null;


    /**
     * @throws \RuntimeException
     * @throws FileNotFoundException
     */
    public function __construct (?string $configDir = null)
    {
        $configFilePath = $this->getConfigFilePath($configDir);

        $processedConfiguration = (new Processor())->processConfiguration(
            new DeployMessageGeneratorConfig(),
            [DeployMessageGeneratorConfig::ROOT_NODE_NAME => Yaml::parseFile($configFilePath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)]
        );

        $this->config = $processedConfiguration;
        $this->environments = $this->fetchDeploymentEnvironments($this->config["server"]);
    }


    private function getConfigFilePath (?string $configDir) : string
    {
        $configDirectory = $configDir ?? \getcwd();

        if (false === $configDirectory)
        {
            throw new \RuntimeException("Cannot read the path of the current working directory.");
        }

        $configFile = "{$configDirectory}/.deploy-message-generator.yaml";

        if (!\is_file($configFile))
        {
            throw new FileNotFoundException("Cannot locate .deploy-message-generator.yaml. Please run this command from the root of your project");
        }

        return $configFile;
    }


    public function getProjectName () : string
    {
        return $this->config["name"];
    }


    /**
     * @throws \Exception
     */
    public function getTicketSystem (SymfonyStyle $io, array $context) : TicketSystem
    {
        if (null === $this->ticketSystem)
        {
            $ticketSystem = $this->config["ticket-system"];
            $ticketSystemConfig = $this->config[$ticketSystem] ?? [];

            if (empty($context["JIRA_USER_EMAIL"]))
            {
                throw new IOException("Environment variable JIRA_USER_EMAIL is not set");
            }

            if (empty($context["JIRA_ACCESS_TOKEN"]))
            {
                throw new IOException("Environment variable JIRA_ACCESS_TOKEN is not set");
            }

            $domain = $ticketSystemConfig["domain"];
            $deploymentField = $ticketSystemConfig["field"];
            $jiraUser = $context["JIRA_USER_EMAIL"];
            $jiraAccessToken = $context["JIRA_ACCESS_TOKEN"];

            $this->ticketSystem = new JiraTicketSystem($io, $this, $deploymentField, $domain, $jiraUser, $jiraAccessToken);
        }

        return $this->ticketSystem;
    }


    public function getVersionControlSystem (SymfonyStyle $io, array $context) : VersionControlSystem
    {
        return $this->versionControlSystem ?? ($this->versionControlSystem = new GitVersionControlSystem($io));
    }


    public function getChatSystem (SymfonyStyle $io, array $context) : ChatSystem
    {
        if (null === $this->chatSystem)
        {
            $chatSystem = $this->config["chat-system"];
            $chatSystemConfig = $this->config[$chatSystem] ?? [];

            if (empty($context["SLACK_ACCESS_TOKEN"]))
            {
                throw new IOException("Environment variable SLACK_ACCESS_TOKEN is not set");
            }

            $channel = $chatSystemConfig["channel"];
            $token = $context["SLACK_ACCESS_TOKEN"];

            $this->chatSystem = new SlackChatSystem($io, $token, $channel);
        }

        return $this->chatSystem;
    }


    public function getAllEnvironments () : array
    {
        return \array_keys($this->environments);
    }


    public function isValidDeploymentStatus (?string $deploymentStatus) : bool
    {
        return null !== $this->resolveDeploymentEnvironment($deploymentStatus);
    }


    public function resolveDeploymentEnvironment (?string $environmentOrAlias) : ?string
    {
        $normalizedEnvironmentOrAlias = $this->normalizeEnvironmentName($environmentOrAlias);

        return $this->environments[$normalizedEnvironmentOrAlias] ?? null;
    }


    public function getMentions () : array
    {
        return $this->config["mentions"] ?? [];
    }


    public function getStagingUrls () : array
    {
        return $this->config["urls"]["staging"] ?? [];
    }


    public function getProductionUrls () : array
    {
        return $this->config["urls"]["production"] ?? [];
    }


    /**
     * @param array<string, array> $servers The key is the Environment name and the values is a list of Aliases for this Environment,
     *
     * @return string[]
     */
    private function fetchDeploymentEnvironments (array $servers) : array
    {
        $environments = [];

        foreach ($servers as $environment => $aliases)
        {
            $environment = $this->normalizeEnvironmentName($environment);
            $environments[$environment] = $environment;

            if (empty($aliases))
            {
                continue;
            }

            foreach ($aliases as $alias)
            {
                $alias = $this->normalizeEnvironmentName($alias);
                $environments[$alias] = $environment;
            }
        }

        return $environments;
    }


    private function normalizeEnvironmentName (?string $environment) : string
    {
        return u($environment)->camel()->title()->toString();
    }


    public function isProductionEnvironment (?string $environment) : bool
    {
        switch (u($environment)->lower())
        {
            case "live":
            case "prod":
            case "production":
                return true;

            default:
                return false;
        }
    }
}
