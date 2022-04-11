<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\Exception\InvalidDeploymentEnvironmentException;
use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class TicketSystem implements SystemIntegration
{
    protected SymfonyStyle $io;
    protected DeployMessageGeneratorConfig $config;


    public function __construct (SymfonyStyle $io, DeployMessageGeneratorConfig $config)
    {
        $this->config = $config;
        $this->io = $io;
    }

    /**
     * Fetches the ticket infos of the ticket with the given id.
     */
    abstract public function getTicketInfo (string $id) : TicketInfo;

    /**
     * Fetches the current deployment status of the ticket with the given id
     */
    abstract protected function getDeploymentStatus (string $id) : string;


    /**
     * Changes the deployment status in the ticket system to the provided deployment status
     */
    abstract protected function setDeploymentStatus (string $id, ?string $deploymentStatus) : void;


    /**
     * Checks if the deployment status may be changed before attempting to change it.
     * Passing null as deploymentStatus Argument will unset the deployment status.
     */
    final public function changeDeploymentStatus (string $id, ?string $deploymentStatus) : void
    {
        if (null !== $deploymentStatus)
        {
            $deploymentStatus = $this->config->getDeploymentEnvironmentFor($deploymentStatus);

            if (!$this->config->isValidDeploymentStatus($deploymentStatus)) {
                throw new InvalidDeploymentEnvironmentException($deploymentStatus, $this->config);
            }
        }

        $this->setDeploymentStatus($id, $deploymentStatus);
        $status = empty($deploymentStatus) ? "null" : $deploymentStatus;
        $this->io->info("Deployment status for {$id} was set to {$status}");
    }


    /**
     * Returns a Regex string that can be used by the VersionControlSystem to find the ids in the commit messages
     */
    abstract public function getTicketIdRegex () : string;


    /**
     * Generates a Deployment for the given Issues under the Field Releases in the Ticket
     */
    abstract public function generateDeployments (
        array $context,
        string $deploymentStatus,
        array $issueKeys,
        string $url
    ) : JiraDeploymentResponse;
}
