<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Runner;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use Becklyn\DeployMessageGenerator\ProjectInformation\ProjectInformationRenderer;
use Becklyn\DeployMessageGenerator\TicketExtractor\TicketExtractor;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShowTicketInformationRunner
{
    private SymfonyStyle $io;
    private ProjectInformationRenderer $projectInformationRenderer;
    private TicketExtractor $ticketExtractor;
    private array $context;


    public function __construct (
        SymfonyStyle $io,
        ProjectInformationRenderer $projectInformationRenderer,
        TicketExtractor $ticketExtractor,
        array $context
    )
    {
        $this->io = $io;
        $this->projectInformationRenderer = $projectInformationRenderer;
        $this->ticketExtractor = $ticketExtractor;
        $this->context = $context;
    }


    /**
     * Runs the deployment Message Generation Process
     */
    public function run (string $commitRange) : void
    {
        $configurator = new DeployMessageGeneratorConfigurator();

        $ticketSystem = $configurator->getTicketSystem($this->io, $this->context);
        $vcsSystem = $configurator->getVersionControlSystem($this->io, $this->context);

        $this->projectInformationRenderer->renderProjectInformation(
            $this->io,
            $configurator
        );

        $this->ticketExtractor->extractAndRenderTicketInformationFromCommitRange(
            $this->io,
            $vcsSystem,
            $ticketSystem,
            $commitRange
        );
    }
}
