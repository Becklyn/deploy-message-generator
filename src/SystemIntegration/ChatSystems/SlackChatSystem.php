<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\Exception\IOException;
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
        if (!isset($this->context[self::ACCESS_TOKEN_ENV]))
        {
            throw new IOException("Cannot read required environment " . self::ACCESS_TOKEN_ENV);
        }

        if (!isset($this->config->getConfigFor($this->getName())['channel']))
        {
            throw new IOException("Cannot read configuration variable slack.channel");
        }

        $accessToken = $this->context[self::ACCESS_TOKEN_ENV];
        $channel = $this->config->getConfigFor($this->getName())['channel'];
        $transport = new SlackTransport($accessToken, $channel);

        if (!empty($this->context["SLACK_MOCK"]) && "mock" === $this->context["SLACK_MOCK"])
        {
            $transport = new NullTransport();
        }

        return new Chatter($transport);
    }
}
