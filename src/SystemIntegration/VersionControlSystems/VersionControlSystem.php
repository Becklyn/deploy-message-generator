<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class VersionControlSystem implements SystemIntegration
{
    protected SymfonyStyle $io;

    public function __construct (SymfonyStyle $io)
    {
        $this->io = $io;
    }

    /**
     * Returns a string containing the data from the changes.
     * E.g. The output from "git log $commitRange"
     */
    abstract protected function getChangelogFromCommitRange(string $commitRange) : string;


    /**
     * Extracts a list of tickets from the changelog and returns it
     */
    protected function extractTicketsFromChangeLog(string $changelog, string $ticketRegex) : array
    {
        $matches = [];
        \preg_match($ticketRegex, $changelog, $matches);
        return \array_unique($matches);
    }


    /**
     * Returns a list of tickets based on the changelog obtained from the commit range
     */
    final public function getTicketIdsFromCommitRange(string $commitRange, string $ticketRegex) : array
    {
        $changeLog = $this->getChangelogFromCommitRange($commitRange);
        return $this->extractTicketsFromChangeLog($changeLog, $ticketRegex);
    }
}
