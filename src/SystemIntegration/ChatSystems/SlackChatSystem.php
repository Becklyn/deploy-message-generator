<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackActionsBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackBlockInterface;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

class SlackChatSystem extends ChatSystem
{
    private string $token;
    private string $channel;

    public function __construct (SymfonyStyle $io, string $token, string $channel)
    {
        parent::__construct($io);
        $this->channel = $channel;
        $this->token = $token;
    }


    /**
     * @inheritdoc
     */
    public function getChatMessageThread (
        array $tickets,
        string $deploymentStatus,
        string $project,
        array $mentions,
        array $urls,
        string $deployUser
    ) : array
    {
        $deploymentHeaderPrefix = \implode(' ', $mentions);
        $deploymentHeader = \trim("{$deploymentHeaderPrefix}  `{$project}` has been deployed to `{$deploymentStatus}` by {$deployUser}");
        $extractText = static fn ($block) => $block->toArray()['text']['text'];
        $blocks = $this->buildBlocks($tickets);

        $ticketTexts = \array_map($extractText, $blocks);
        $messages = [];
        $currentOptions = new SlackOptions();
        $currentOptions->block((new SlackSectionBlock())->text($deploymentHeader));

        for ($i = 0; $i < \count($ticketTexts); ++$i)
        {
            $currentOptions->block((new SlackSectionBlock())->text($ticketTexts[$i]));

            if ($i > 0 && 0 === $i % 47)
            {
                $currentOptions->block(new SlackDividerBlock());
                $message = (new ChatMessage('Deployment Info'))->transport('slack')->options($currentOptions);
                $messages[] = $message;
                $currentOptions = new SlackOptions();
                $currentOptions->block((new SlackSectionBlock())->text('The following tickets were also deployed:'));
            }
        }

        if (0 < \count($urls))
        {
            $actionsBlock = new SlackActionsBlock();

            foreach ($urls as $url)
            {
                $actionsBlock->button($url, $url);
            }

            $currentOptions->block($actionsBlock);
        }

        $message = (new ChatMessage('Deployment Info'))->transport('slack')->options($currentOptions);
        $messages[] = $message;

        return $messages;
    }


    /**
     * Creating the needed number of blocks with each block containing up to 3000 characters of text.
     *
     * @param TicketInfo[] $tickets
     *
     * @return SlackBlockInterface[]
     */
    private function buildBlocks (array $tickets) : array
    {
        $blocks = [];
        $blockMessage = '';

        foreach ($this->buildMarkdownList($tickets) as $listItem)
        {
            // truncating ticket information line.
            if (\strlen($listItem) > 3000)
            {
                $listItem = \substr($listItem, 0, 3000 - 3) . '...';
            }

            // region check if ticket information line fits into current block
            if (empty($blockMessage))
            {
                $blockMessage = $listItem;
                continue;
            }


            if (\strlen($blockMessage . "\n" . $listItem) < 3000)
            {
                $blockMessage .= "\n" . $listItem;
                continue;
            }
            // endregion

            $blocks[] = (new SlackSectionBlock())->text($blockMessage);
            $blockMessage = $listItem;
        }

        $blocks[] = (new SlackSectionBlock())->text($blockMessage);
        return $blocks;
    }


    /**
     * @param TicketInfo[] $tickets
     *
     * @return string[]
     */
    private function buildMarkdownList(array $tickets) : array
    {
        $markdownList = [];

        foreach ($tickets as $ticket) {
            $markdownList[] = "• <{$ticket->getUrl()}|{$ticket->getId()}> {$ticket->getTitle()}";
        }

        if (empty($markdownList)) {
            $markdownList[] = "No ticket information available";
        }

        return $markdownList;
    }


    public function sendThread (array $messages, ?TransportInterface $transport = null) : array
    {
        $mainMessage = $messages[0];
        $mainMessageResponse = $this->sendMessage($mainMessage, $transport);
        $responses = [$mainMessageResponse];

        for ($i = 1; $i < \count($messages); ++$i)
        {
            $message = $messages[$i];
            /** @var SlackOptions $options */
            $options = $message->getOptions();

            if (null !== $mainMessageResponse->getMessageId())
            {
                $options->threadTs($mainMessageResponse->getMessageId());
            }
            $responses[] = $this->sendMessage($message, $transport);
        }

        return $responses;
    }


    /**
     * @inheritDoc
     */
    public function getName () : string
    {
        return "slack";
    }


    protected function getChatter (?TransportInterface $transport = null) : Chatter
    {
        $transport = $transport ?? new SlackTransport($this->token, $this->channel);

        return new Chatter($transport);
    }
}
