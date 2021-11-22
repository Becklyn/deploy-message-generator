<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Runner;

use Becklyn\DeployMessageGenerator\Commands\SendDeployMessageCommand;
use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\Exception\InvalidDeploymentEnvironmentException;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Process\Process;

class SendDeployMessageRunner
{
    private array $context;

    private SymfonyStyle $io;

    public function __construct (SymfonyStyle $io, array $context)
    {
        $this->io = $io;
        $this->context = $context;
    }

    /**
     * Runs the deployment Message Generation Process
     */
    public function run (string $commitRange, string $deploymentStatus) : void
    {
        $config = new DeployMessageGeneratorConfig();

        if (!$config->isValidDeploymentStatus($deploymentStatus))
        {
            throw new InvalidDeploymentEnvironmentException($deploymentStatus, $config);
        }


        $ticketSystem = $config->getTicketSystem($this->io, $this->context);
        $vcs = $config->getVersionControlSystem($this->io, $this->context);
        $chatSystem = $config->getChatSystem($this->io, $this->context);

        $project = $config->getProjectName();
        $ticketRegex = $ticketSystem->getTicketIdRegex();
        $deploymentStatus = $config->getDeploymentEnvironmentFor($deploymentStatus);

        $ticketIds = $vcs->getTicketIdsFromCommitRange($commitRange, $ticketRegex);
        $tickets = [];

        foreach ($ticketIds as $id)
        {
            $ticketSystem->changeDeploymentStatus($id, $deploymentStatus);
            $ticketInfo = $ticketSystem->getTicketInfo($id);
            $tickets[] = $ticketInfo;
        }

        $shouldSendMessageViaChatSystem = $this->context[SendDeployMessageCommand::SEND_MESSAGE_FLAG_NAME];
        $shouldCopyMessageToClipboard = $this->context[SendDeployMessageCommand::COPY_MESSAGE_FLAG_NAME];

        // Interactive mode and no explicit flag whether to send or copy the message
        if (!$this->context[SendDeployMessageCommand::NON_INTERACTIVE_FLAG_NAME] && !$shouldSendMessageViaChatSystem && !$shouldCopyMessageToClipboard)
        {
            $question = new ConfirmationQuestion("Should the deployment message be sent using {$chatSystem->getName()}?", false);
            $shouldSendMessageViaChatSystem = $this->io->askQuestion($question);
        }

        if ($shouldSendMessageViaChatSystem)
        {
            try
            {
                $chatMessage = $chatSystem->getChatMessage($tickets, $deploymentStatus, $project);
                $chatSystem->sendMessage($chatMessage);
            }
            catch (TransportExceptionInterface $e)
            {
                $this->io->error("Could not send deploy message due to a transport error. Generating message for copy/paste...");
                $this->generateMessageForCopyPaste($tickets, $project, $deploymentStatus);
            }
        }
        else
        {
            $this->generateMessageForCopyPaste($tickets, $project, $deploymentStatus);
        }
    }


    /**
     * @param TicketInfo[] $ticketInfos
     */
    private function generateMessageForCopyPaste (
        array $ticketInfos,
        string $project,
        string $deploymentStatus
    ) : void
    {
        $mdMessage = "`{$project}` has been deployed to `{$deploymentStatus}`." . \PHP_EOL;

        foreach ($ticketInfos as $ticketInfo)
        {
            // Markdown: " - [JIRA-101](jira.com/browser/JIRA-101) Some ticket"
            $mdMessage .= " - [{$ticketInfo->getId()}]({$ticketInfo->getUrl()}) {$ticketInfo->getTitle()}" . \PHP_EOL;
        }

        if (empty($ticketInfos)) {
            $mdMessage .= "No ticket information available";
        }

        try
        {
            $this->io->info("Copying the following markdown message:");
            $this->io->write($mdMessage);
            $this->copyToClipboard($mdMessage);
            $this->io->success("Copied markdown message to clipboard");
        }
        catch (\Exception $e)
        {
            $this->io->error("Could not copy deployment message to clipboard. Please copy the markdown message above yourself.");
        }
    }


    private function copyToClipboard (string $message) : void
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

        $process = new Process(["echo", \escapeshellarg($message), " |", $executable]);
        $process->run();

        if (!$process->isSuccessful())
        {
            throw new \Exception();
        }
    }
}
