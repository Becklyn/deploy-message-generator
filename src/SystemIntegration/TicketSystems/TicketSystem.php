<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class TicketSystem implements SystemIntegration
{
    protected SymfonyStyle $io;
    protected array $context;
    protected DeployMessageGeneratorConfig $config;


    public function __construct (SymfonyStyle $io, array $context, DeployMessageGeneratorConfig $config)
    {
        $this->config = $config;
        $this->context = $context;
        $this->io = $io;
    }

    /**
     * Fetches the ticket infos of the ticket with the given id.
     */
    abstract public function getTicketInfo(string $id) : TicketInfo;

    /**
     * Fetches the current deployment status of the ticket with the given id
     */
    abstract protected function getDeploymentStatus(string $id) : string;


    /**
     * Changes the deployment status in the ticket system to the provided deployment status
     */
    abstract protected function setDeploymentStatus(string $id, ?string $deploymentStatus) : void;


    /**
     * Checks if the deployment status may be changed before attempting to change it.
     */
    final public function changeDeploymentStatus(string $id, ?string $deploymentStatus) : void
    {
        $deploymentStatus = $this->config->getDeploymentEnvironmentFor($deploymentStatus);

        if (!empty($deploymentStatus) && !$this->config->isValidDeploymentStatus($deploymentStatus))
        {
            throw new \InvalidArgumentException("The deployment status \"{$deploymentStatus}\" is invalid. Is it defined in the configuration file \".deploy-message-generator.yaml\"?");
        }

        $this->setDeploymentStatus($id, $deploymentStatus);
        $status = empty($deploymentStatus) ? "null" : $deploymentStatus;
        $this->io->info("Deployment staus for {$id} was set to {$status}");
    }


    /**
     * Returns a Regex string that can be used by the VersionControlSystem to find the ids in the commit messages
     */
    abstract public function getTicketIdRegex() : string;
}
