<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Runner;

use Becklyn\DeployMessageGenerator\Commands\SendDeployMessageCommand;
use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use Becklyn\DeployMessageGenerator\Exception\InvalidDeploymentEnvironmentException;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\ChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\VersionControlSystem;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Process\Process;
use function Symfony\Component\String\u;

class SendDeployMessageRunner
{
    private array $context;
    private SymfonyStyle $io;


    public function __construct (
        SymfonyStyle $io,
        array $context
    )
    {
        $this->io = $io;
        $this->context = $context;
    }


    /**
     * Runs the deployment Message Generation Process
     */
    public function run (
        string $commitRange,
        string $deploymentEnvironmentOrAlias,
        array $additionalMentions
    ) : void
    {
        $configurator = new DeployMessageGeneratorConfigurator();
        $environment = $configurator->resolveDeploymentEnvironment($deploymentEnvironmentOrAlias);

        if (null === $environment)
        {
            throw new InvalidDeploymentEnvironmentException($deploymentEnvironmentOrAlias, $configurator->getAllEnvironments());
        }

        $this->io->writeln("Project: <fg=green>{$configurator->getProjectName()}</>");
        $this->io->writeln("Environment: <fg=green>{$environment}</>");
        $this->io->newLine(2);

        $ticketSystem = $configurator->getTicketSystem($this->io, $this->context);
        $vcsSystem = $configurator->getVersionControlSystem($this->io, $this->context);
        $chatSystem = $configurator->getChatSystem($this->io, $this->context);

        $tickets = $this->extractTicketInformationFromCommitRange($vcsSystem, $ticketSystem, $commitRange);
        $this->updateDeploymentStatusForTickets($ticketSystem, $tickets, $environment);

        $this->sendDeploymentMessageOrCopyToClipboard(
            $chatSystem,
            $environment,
            $configurator->getProjectName(),
            $tickets,
            [...$configurator->getMentions(), ...$additionalMentions],
            $this->context[SendDeployMessageCommand::NON_INTERACTIVE_FLAG_NAME],
        );

        $this->generateTicketDeploymentInformation(
            $configurator,
            $vcsSystem,
            $ticketSystem,
            $environment,
            $tickets,
            $commitRange
        );
    }


    /**
     * @return array The key will be the Ticket Id itself and the value will be the {@see TicketInfo}, fetched from the {@see TicketSystem}.
     */
    private function extractTicketInformationFromCommitRange (
        VersionControlSystem $vcsSystem,
        TicketSystem $ticketSystem,
        string $commitRange
    ) : array
    {
        $this->io->writeln(" <fg=green>//</> Extracting Tickets from Commit Range and fetching TicketInfo.");
        $this->io->newLine();

        $tickets = [];
        $hadErrors = false;

        foreach ($vcsSystem->getTicketIdsFromCommitRange($commitRange, $ticketSystem->getTicketIdRegex()) as $ticketId)
        {
            try
            {
                $tickets[$ticketId] = $ticketSystem->getTicketInfo($ticketId);
            }
            catch (\Exception $e)
            {
                $this->io->warning("Could not fetch TicketInfo for '{$ticketId}' — skipping ticket. Typo or permissions problem?");
                $hadErrors = true;
            }
        }

        if ($hadErrors)
        {
            $this->io->newLine(2);
        }

        $this->io->writeln(\sprintf(
            "Found <fg=green>%d</> tickets:",
            \count($tickets)
        ));

        foreach ($tickets as $ticketInfo)
        {
            $this->io->writeln(\sprintf(
                "<fg=green>  · %s</>: %s",
                $ticketInfo->getId(),
                $ticketInfo->getTitle(),
            ));
        }

        $this->io->newLine(2);

        return $tickets;
    }


    private function updateDeploymentStatusForTickets (
        TicketSystem $ticketSystem,
        array $tickets,
        string $environment
    ) : void
    {
        $this->io->writeln(" <fg=green>//</> Updating deployed field in Tickets.");
        $this->io->newLine();

        foreach ($tickets as $ticketId => $ticketInfo)
        {
            try
            {
                $this->io->write(\sprintf(
                    "<fg=green>  · %s</>: %s… ",
                    $ticketInfo->getId(),
                    $ticketInfo->getTitle(),
                ));

                $ticketSystem->changeDeploymentStatus($ticketId, $environment);

                $this->io->write("<fg=green>done</>.");
                $this->io->newLine();
            }
            catch (\Exception $e)
            {
                $this->io->write("<fg=red>failed</>.");

                $this->io->warning("Failed to update Ticket '{$ticketId}': Could not find or access given ticket (typo or permissions problem?).");
                $this->io->newLine();
            }
        }

        $this->io->newLine(2);
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


    /**
     * @param string[]                  $mentions
     * @param array<string, TicketInfo> $tickets
     */
    private function sendDeploymentMessageOrCopyToClipboard (
        ChatSystem $chatSystem,
        string $deploymentEnvironment,
        string $project,
        array $tickets,
        array $mentions,
        bool $isNonInteractive
    ) : void
    {
        $shouldSendMessageViaChatSystem = $this->context[SendDeployMessageCommand::SEND_MESSAGE_FLAG_NAME];
        $shouldCopyMessageToClipboard = $this->context[SendDeployMessageCommand::COPY_MESSAGE_FLAG_NAME];
        $chatSystemName = u($chatSystem->getName())->title()->toString();

        $this->io->writeln(" <fg=green>//</> Sending Deployment Message for Tickets via <fg=green>{$chatSystemName}</>.");
        $this->io->newLine();

        // Interactive mode and no explicit flag whether to send or copy the message
        if (!$isNonInteractive && !$shouldSendMessageViaChatSystem && !$shouldCopyMessageToClipboard)
        {
            $this->io->newLine();

            $question = new ConfirmationQuestion("Should the deployment message be sent via <fg=green>{$chatSystemName}</>?", false);
            $shouldSendMessageViaChatSystem = $this->io->askQuestion($question);

            $this->io->newLine();
        }

        if ($shouldSendMessageViaChatSystem)
        {
            try
            {
                $this->io->write("Sending Deployment message… ");

                $thread = $chatSystem->getChatMessageThread($tickets, $deploymentEnvironment, $project, $mentions);
                $chatSystem->sendThread($thread);

                $this->io->write("<fg=green>done</>.");
                $this->io->newLine(2);
            }
            catch (TransportExceptionInterface $e)
            {
                $this->io->write("<fg=red>failed</>.");
                $this->io->newLine();

                $this->io->error("Could not send Deployment Message due to a transport error.");

                $this->io->write("Copying Deployment Message into Clipboard… ");
                $this->generateMessageForCopyPaste($tickets, $project, $deploymentEnvironment);

                $this->io->write("<fg=green>done</>.");
                $this->io->newLine(2);
            }
        }
        else
        {
            $this->io->write("Copying Deployment Message into Clipboard… ");
            $this->generateMessageForCopyPaste($tickets, $project, $deploymentEnvironment);

            $this->io->write("<fg=green>done</>.");
            $this->io->newLine(2);
        }
    }


    /**
     * @param string[] $tickets A List of Jira Issue keys, e.g. ABC-123
     */
    private function generateTicketDeploymentInformation (
        DeployMessageGeneratorConfigurator $configurator,
        VersionControlSystem $vcsSystem,
        TicketSystem $ticketSystem,
        string $environment,
        array $tickets,
        string $commitRange
    ) : void
    {
        $this->io->writeln(" <fg=green>//</> Creating new Jira Deployment/Release Information with Tickets.");
        $this->io->newLine();

        $urls = $configurator->isProductionEnvironment($environment)
            ? $configurator->getProductionUrls()
            : $configurator->getStagingUrls();
        $urls = 0 < \count($urls) ? $urls : [$vcsSystem->remoteOriginUrl()];

        $this->io->writeln("{$environment} URL(s):");

        foreach ($urls as $url)
        {
            $this->io->writeln(\sprintf(
                "<fg=green>  · %s</> ",
                $url,
            ));
        }

        $this->io->newLine();

        $this->io->write("Creating new Deployment/Release Information… ");

        $jiraDeploymentResponse = $ticketSystem->generateDeployments(
            $this->context,
            $environment,
            \array_keys($tickets),
            $urls,
            $commitRange
        );

        if ($jiraDeploymentResponse->isSuccessful())
        {
            $this->io->write("<fg=green>done</>.");
            $this->io->newLine();
        }
        else
        {
            $this->io->write("<fg=red>failed</>.");

            $this->io->warning("JIRA API Error - Adding Deployment Failed");
            $this->io->listing($jiraDeploymentResponse->getErrors());
        }
    }
}
