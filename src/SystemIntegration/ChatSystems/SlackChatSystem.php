<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport\NullTransport;

class SlackChatSystem extends ChatSystem
{
    private const ACCESS_TOKEN_ENV = "SLACK_ACCESS_TOKEN";
    private const CHANNEL_ENV = "SLACK_DEPLOYMENT_CHANNEL";

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

        return $markdownList;
    }


    /**
     * @inheritDoc
     */
    public function getName () : string
    {
        return "slack";
    }


    protected function getChatter () : Chatter
    {
        if (!isset($_ENV[self::ACCESS_TOKEN_ENV]) || !isset($_ENV[self::CHANNEL_ENV]))
        {
            $this->io->error(\sprintf("Cannot read required environment variables. Are %s and %s defined?", self::ACCESS_TOKEN_ENV, self::CHANNEL_ENV));
            throw new \Exception();
        }

        $accessToken = $_ENV[self::ACCESS_TOKEN_ENV];
        $channel = $_ENV[self::CHANNEL_ENV];
        $transport = new SlackTransport($accessToken, $channel);

        if (!empty($_ENV['SLACK_MOCK']) && "mock" === $_ENV['SLACK_MOCK'])
        {
            $transport = new NullTransport();
        }

        return new Chatter($transport);
    }
}
