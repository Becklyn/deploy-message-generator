<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class TicketSystem implements SystemIntegration
{
    protected SymfonyStyle $io;


    public function __construct (SymfonyStyle $io)
    {
        $this->io = $io;
    }

    /**
     * The names for the deployment status that are recognised as production systems
     */
    protected const PRODUCTION_STATUS_NAMES = ["live", "production"];

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
        $currentDeploymentStatus = $this->getDeploymentStatus($id);

        $isDeployedToProduction = \in_array(\strtolower($currentDeploymentStatus), self::PRODUCTION_STATUS_NAMES, true);
        $wantToDeployProduction = \in_array(\strtolower($deploymentStatus), self::PRODUCTION_STATUS_NAMES, true);

        if ($isDeployedToProduction && !$wantToDeployProduction) {
            $this->io->warning("Cannot set deployment status to {$deploymentStatus} after it was deployed to Production");
            return;
        }

        $this->setDeploymentStatus($id, $deploymentStatus);
        $status = empty($deploymentStatus) ? 'null' : $deploymentStatus;
        $this->io->info("Deployment staus for {$id} was set to {$status}");
    }


    /**
     * Returns a Regex string that can be used by the VersionControlSystem to find the ids in the commit messages
     */
    abstract public function getTicketIdRegex() : string;
}
