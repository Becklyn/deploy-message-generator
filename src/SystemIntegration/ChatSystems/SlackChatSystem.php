<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Exception\LogicException;
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
    public function getChatMessage (array $tickets, string $deploymentStatus, string $project) : ChatMessage
    {
        $message = (new ChatMessage("Deployment Info"))->transport("slack");

        $options = (new SlackOptions())
            ->block((new SlackSectionBlock())->text("`{$project}` has been deployed to `{$deploymentStatus}`"));
        $this->buildBlocks($options, $tickets);
        $options->block(new SlackDividerBlock());

        return $message->options($options);
    }


    /**
     * Creating the needed number of blocks with each block containing up to 3000 characters of text.
     *
     * @param TicketInfo[] $tickets
     */
    private function buildBlocks (SlackOptions $options, array $tickets) : void
    {
        $blockMessage = '';

        foreach ($this->buildMarkdownList($tickets) as $listItem)
        {
            // truncating each message
            if (\strlen($listItem) > 3000)
            {
                $listItem = \substr($listItem, 0, 3000 - 3) . '...';
            }

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

            $options->block((new SlackSectionBlock())->text($blockMessage));
            $blockMessage = $listItem;
        }

        $options->block((new SlackSectionBlock())->text($blockMessage));
    }

    /**
     * @param TicketInfo[] $tickets
     *
     * @return array<string>
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


    /**
     * @inheritdoc
     */
    public function isWithinRateLimit (array $tickets) : bool
    {
        try
        {
            $options = $this->getChatMessage($tickets, "", "")->getOptions()->toArray();
            return \count($options["blocks"]) < 50;
        }
        catch (LogicException)
        {
            return false;
        }
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
