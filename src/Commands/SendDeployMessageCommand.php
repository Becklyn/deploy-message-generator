<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Commands;

use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSytem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\GitVersionControlSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Process\Process;

class SendDeployMessageCommand extends Command
{
    protected const COMMIT_RANGE_ARG_NAME = "commit-range";
    protected const DEPLOY_STATUS_ARG_NAME = "deployment-status";
    protected static $defaultName = "deployment:send-message";

    protected function configure () : void
    {
        parent::configure();
        $this->addArgument(self::DEPLOY_STATUS_ARG_NAME, InputArgument::REQUIRED, "The deployment status to be set. e.g. staging.");
        $this->addArgument(self::COMMIT_RANGE_ARG_NAME, InputArgument::REQUIRED, "The commit range that was deployed.");
        $this->setHelp("This command searches for all tickets that were deployed and creates a deploy message for them.");
    }

    protected function execute (InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);
        $commitRange = $input->getArgument(self::COMMIT_RANGE_ARG_NAME);
        $deploymentStatus = $input->getArgument(self::DEPLOY_STATUS_ARG_NAME);

        $ticketSystem = new JiraTicketSytem($io);
        $vcs = new GitVersionControlSystem($io);
        $chatSystem = new SlackChatSystem($io);

        $project = $this->getProjectName($io);
        $ticketRegex = $ticketSystem->getTicketIdRegex();
        $ticketIds = $vcs->getTicketIdsFromCommitRange($commitRange, $ticketRegex);
        $tickets = [];

        foreach ($ticketIds as $id) {
            $ticketSystem->changeDeploymentStatus($id, $deploymentStatus);

            $ticketInfo = $ticketSystem->getTicketInfo($id);
            $tickets[] = $ticketInfo;
        }

        $question = new ConfirmationQuestion("Should the deployment message be sent using {$chatSystem->getName()}?");
        $shouldSendMessageViaChatSystem = $io->askQuestion($question);

        if ($shouldSendMessageViaChatSystem)
        {
            try
            {
                $chatMessage = $chatSystem->getChatMessage($tickets, $deploymentStatus, $project);
                $chatSystem->sendMessage($chatMessage);
            }
            catch (TransportExceptionInterface $e)
            {
                $io->error("Could not send deploy message due. Generating message for copy/paste...");
                $this->generateMessageForCopyPaste($tickets, $project, $deploymentStatus, $io);
            }
        }
        else
        {
            $this->generateMessageForCopyPaste($tickets, $project, $deploymentStatus, $io);
        }

        return self::SUCCESS;
    }


    /**
     * @param TicketInfo[] $ticketInfos
     */
    private function generateMessageForCopyPaste(array $ticketInfos, string $project, string $deploymentStatus, SymfonyStyle $io) : void
    {
        $mdMessage = "`{$project}` has been deployed to `{$deploymentStatus}`.\n";

        foreach ($ticketInfos as $ticketInfo)
        {
            // Markdown: " - [JIRA-101](jira.com/browser/WALD-101) Some ticket"
            $mdMessage .= " - [{$ticketInfo->getId()}]({$ticketInfo->getUrl()}) {$ticketInfo->getTitle()}\n";
        }

        try {
            $io->info("Copying the following markdown message:");
            $io->write($mdMessage);
            $this->copyToClipboard($mdMessage);
            $io->success("Copied markdown message to clipboard");
        }
        catch (\Exception $e)
        {
            $io->error("Could not copy deployment message to clipboard. Please copy the markdown message above yourself.");
        }
    }

    private function copyToClipboard(string $message) : void
    {

        switch (\PHP_OS_FAMILY)
        {
            case "Windows":
                $executable = "clip";
                break;

            case "Darwin":
                $executable = "pbcopy";
                break;

            case "BSD":
            case "Solaris":
            case "Linux":
                $executable = "xclip";
                break;

            default:
                throw new \Exception();
        }

        $process = new Process(["echo", $message, "|", $executable]);
        $exitcode = $process->run();

        if (0 !== $exitcode || !empty($process->getErrorOutput()))
        {
            throw new \Exception();
        }
    }

    private function getProjectName(SymfonyStyle $io) : string
    {
        $cwd = \getcwd();

        if (false === $cwd)
        {
            $io->error("Cannot read the path of the current working directory.");
            throw new \Exception();
        }

        $dirname = \dirname($cwd);
        $composerFilePath = "{$cwd}/composer.json";

        if (!\is_file($composerFilePath))
        {
            $io->error("Cannot locate composer.json. Please run this command in the directory where the composer.json is located");
            throw new \Exception();
        }

        $composerJson = \file_get_contents($composerFilePath);
        $composer = \json_decode($composerJson, true, 512, \JSON_THROW_ON_ERROR);

        if (empty($composer["name"]))
        {
            $io->info("Cannot read name from composer.json");
            throw new \Exception();
        }

        $projectName = \explode("/", $composer["name"])[1];

        // if dirname is $projectName with a suffix in form of "/-[a-zA-Z0-9]+/"
        if (\strlen($dirname) > \strlen($projectName) && \str_starts_with($dirname, "{$projectName}-"))
        {
            $projectName = $dirname;
        }

        return $projectName;
    }
}
