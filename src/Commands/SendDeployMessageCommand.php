<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Commands;

use Becklyn\DeployMessageGenerator\Exception\CommandAbortedException;
use Becklyn\DeployMessageGenerator\ProjectInformation\ProjectInformationRenderer;
use Becklyn\DeployMessageGenerator\Runner\SendDeployMessageRunner;
use Becklyn\DeployMessageGenerator\TicketExtractor\TicketExtractor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendDeployMessageCommand extends Command
{
    public const SEND_MESSAGE_FLAG_NAME = "send-message";
    public const COPY_MESSAGE_FLAG_NAME = "copy-message";
    public const NON_INTERACTIVE_FLAG_NAME = "no-interaction";
    public const ADDITIONAL_MENTIONS = "mentions";

    protected static $defaultName = "deployment:send-message";

    private const VCS_COMMIT_RANGE_ARG_NAME = "vcs-commit-range";
    private const DEPLOYMENT_ENVIRONMENT_ARG_NAME = "deployment-environment";

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
            ->addArgument(self::DEPLOYMENT_ENVIRONMENT_ARG_NAME, InputArgument::REQUIRED, "The deployment environment to be set. E.g. 'staging', 'production', etc.")
            ->addArgument(self::VCS_COMMIT_RANGE_ARG_NAME, InputArgument::REQUIRED, "The commit range that was deployed.")
            ->addArgument(self::ADDITIONAL_MENTIONS, InputArgument::IS_ARRAY, "A list of Slack users that will be mentioned additional to those, that have been configured for this project.")
            ->addOption(self::SEND_MESSAGE_FLAG_NAME, "m", InputOption::VALUE_NONE, "Will skip confirmation and send the message using the configured ChatSystem.")
            ->addOption(self::COPY_MESSAGE_FLAG_NAME, "c", InputOption::VALUE_NONE, "Will skip confirmation and copy the message to the clipboard.")
            ->setHelp("This command searches for all tickets that have been included in the given commit range, updates their ticket status, creates a deployment and sends a deploy message for them.");
    }


    /**
     * @inheritDoc
     */
    protected function execute (InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Sending Deployment Message");

        $commitRange = $input->getArgument(self::VCS_COMMIT_RANGE_ARG_NAME);
        $deploymentStatus = $input->getArgument(self::DEPLOYMENT_ENVIRONMENT_ARG_NAME);
        $mentions = $input->getArgument(self::ADDITIONAL_MENTIONS);

        $this->context[self::SEND_MESSAGE_FLAG_NAME] = $input->getOption(self::SEND_MESSAGE_FLAG_NAME);
        $this->context[self::COPY_MESSAGE_FLAG_NAME] = $input->getOption(self::COPY_MESSAGE_FLAG_NAME);
        $this->context[self::NON_INTERACTIVE_FLAG_NAME] = $input->getOption(self::NON_INTERACTIVE_FLAG_NAME);

        try
        {
            $runner = new SendDeployMessageRunner(
                $io,
                new ProjectInformationRenderer(),
                new TicketExtractor(),
                $this->context
            );

            $runner->run(
                $commitRange,
                $deploymentStatus,
                $mentions
            );

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
