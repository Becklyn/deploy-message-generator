<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
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
    public function getChatMessage (array $tickets, string $deploymentStatus, string $project) : ChatMessage
    {
        $message = (new ChatMessage("Deployment Info"))->transport("slack");

        $options = (new SlackOptions())
            ->block((new SlackSectionBlock())->text("`{$project}` has been deployed to `{$deploymentStatus}`"))
            ->block((new SlackSectionBlock())->text($this->buildMarkdownList($tickets)))
            ->block(new SlackDividerBlock());

        return $message->options($options);
    }


    /**
     * @param TicketInfo[] $tickets
     */
    private function buildMarkdownList(array $tickets) : string
    {
        $markdownList = "";

        foreach ($tickets as $ticket) {
            $markdownList .= "• <{$ticket->getUrl()}|{$ticket->getId()}> {$ticket->getTitle()}  \n";
        }

        if (empty($markdownList)) {
            return "No ticket information available";
        }

        return $markdownList;
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
