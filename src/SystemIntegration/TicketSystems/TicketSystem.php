<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class TicketSystem implements SystemIntegration
{
    protected SymfonyStyle $io;
    protected DeployMessageGeneratorConfigurator $config;


    public function __construct (
        SymfonyStyle $io,
        DeployMessageGeneratorConfigurator $config
    )
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
    abstract public function getDeploymentStatus (string $id) : string;


    /**
     * Changes the deployment status in the ticket system to the provided deployment status
     */
    abstract public function setDeploymentStatus (string $id, ?string $deploymentStatus) : void;


    /**
     * Checks if the deployment status may be changed before attempting to change it.
     * Passing null as deploymentStatus Argument will unset the deployment status.
     */
    final public function changeDeploymentStatus (string $id, ?string $deploymentStatus) : void
    {
        $this->setDeploymentStatus($id, $deploymentStatus);
        $status = empty($deploymentStatus) ? "null" : $deploymentStatus;
    }


    /**
     * Returns a Regex string that can be used by the VersionControlSystem to find the ids in the commit messages
     */
    abstract public function getTicketIdRegex () : string;


    /**
     * Generates a Deployment for the given Issues under the Field Releases in the Ticket
     *
     * @param string[] $issueKeys A List of Jira Issue keys, e.g. ABC-123
     */
    abstract public function generateDeployments (
        array $context,
        string $environment,
        array $issueKeys,
        array $urls,
        string $commitRange
    ) : JiraDeploymentResponse;
}
