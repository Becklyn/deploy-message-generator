<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Commands;

use Becklyn\DeployMessageGenerator\Exception\CommandAbortedException;
use Becklyn\DeployMessageGenerator\ProjectInformation\ProjectInformationRenderer;
use Becklyn\DeployMessageGenerator\Runner\ShowTicketInformationRunner;
use Becklyn\DeployMessageGenerator\TicketExtractor\TicketExtractor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShowTicketInformationCommand extends Command
{
    protected static $defaultName = "ticket:show-ticket-information";

    private const VCS_COMMIT_RANGE_ARG_NAME = "vcs-commit-range";

    private array $context;


    /**
     * @inheritDoc
     */
    public function __construct (array $context, ?string $name = null)
    {
        $this->context = $context;
        parent::__construct($name);
    }


    /**
     * @inheritDoc
     */
    protected function configure () : void
    {
        parent::configure();

        $this
            ->addArgument(self::VCS_COMMIT_RANGE_ARG_NAME, InputArgument::REQUIRED, "The commit range that was deployed.")
            ->setHelp("This command searches for all tickets that have been included in the given commit range.");
    }


    /**
     * @inheritDoc
     */
    protected function execute (InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Sending Deployment Message");

        $commitRange = $input->getArgument(self::VCS_COMMIT_RANGE_ARG_NAME);

        try
        {
            $runner = new ShowTicketInformationRunner(
                $io,
                new ProjectInformationRenderer(),
                new TicketExtractor(),
                $this->context
            );

            $runner->run($commitRange);

            $io->newLine(2);
            $io->success("Done.");

            return self::SUCCESS;
        }
        catch (CommandAbortedException $e)
        {
            return self::FAILURE;
        }
    }
}
