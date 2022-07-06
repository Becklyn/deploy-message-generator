<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\TicketExtractor;

use Becklyn\DeployMessageGenerator\Exception\CommandAbortedException;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems\VersionControlSystem;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class TicketExtractor
{
    /**
     * @return array The key will be the Ticket Id itself and the value will be the {@see TicketInfo}, fetched from the {@see TicketSystem}.
     */
    public function extractAndRenderTicketInformationFromCommitRange (
        SymfonyStyle $io,
        VersionControlSystem $vcsSystem,
        TicketSystem $ticketSystem,
        string $commitRange,
        ?string $environment = null,
        ?bool $isNonInteractive = null
    ) : array
    {
        $io->writeln(" <fg=green>//</> Extracting Tickets from Commit Range and fetching TicketInfo.");
        $io->newLine();

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
                $io->warning("Could not fetch TicketInfo for '{$ticketId}' — skipping ticket. Typo or permissions problem?");
                $hadErrors = true;
            }
        }

        if ($hadErrors)
        {
            $io->newLine(2);
        }

        $io->writeln(\sprintf(
            "Found <fg=green>%d</> tickets:",
            \count($tickets)
        ));

        foreach ($tickets as $ticketInfo)
        {
            $io->writeln(\sprintf(
                "<fg=green>  · %s</>: %s (%s)",
                $ticketInfo->getId(),
                $ticketInfo->getTitle(),
                $ticketInfo->getUrl(),
            ));
        }

        // Interactive mode and no explicit flag whether to send or copy the message
        if (null !== $environment && null !== $isNonInteractive && !$isNonInteractive)
        {
            $io->newLine();

            if (!$io->askQuestion(new ConfirmationQuestion("Continue deployment to <fg=green>{$environment}</>?", true)))
            {
                throw new CommandAbortedException();
            }
        }

        $io->newLine(2);

        return $tickets;
    }
}
