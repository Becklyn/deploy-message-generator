<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Commands;

use Becklyn\DeployMessageGenerator\Runner\SendDeployMessageRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendDeployMessageCommand extends Command
{
    private const COMMIT_RANGE_ARG_NAME = "commit-range";
    private const DEPLOY_STATUS_ARG_NAME = "deployment-status";
    public const SEND_MESSAGE_FLAG_NAME = "send-message";
    public const NON_INTERACTIVE_FLAG_NAME = "no-interaction";
    protected static $defaultName = "deployment:send-message";

    private array $context;

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
        $this->addArgument(self::DEPLOY_STATUS_ARG_NAME, InputArgument::REQUIRED, "The deployment status to be set. e.g. staging.");
        $this->addArgument(self::COMMIT_RANGE_ARG_NAME, InputArgument::REQUIRED, "The commit range that was deployed.");
        $this->addOption(self::SEND_MESSAGE_FLAG_NAME, "m", InputOption::VALUE_NONE, "Will skip confirmation and send the message using the configured ChatSystem.");
        $this->addOption(self::NON_INTERACTIVE_FLAG_NAME, "i", InputOption::VALUE_NONE, "Will prevent any interactions via the cli and just use default values");
        $this->setHelp("This command searches for all tickets that were deployed and creates a deploy message for them.");
    }


    /**
     * @inheritDoc
     */
    protected function execute (InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);
        $commitRange = $input->getArgument(self::COMMIT_RANGE_ARG_NAME);
        $deploymentStatus = $input->getArgument(self::DEPLOY_STATUS_ARG_NAME);
        $this->context[self::SEND_MESSAGE_FLAG_NAME] = $input->hasOption(self::SEND_MESSAGE_FLAG_NAME);
        $this->context[self::NON_INTERACTIVE_FLAG_NAME] = !$input->hasOption(self::NON_INTERACTIVE_FLAG_NAME);

        (new SendDeployMessageRunner($io, $this->context))->run($commitRange, $deploymentStatus);

        return self::SUCCESS;
    }



}
