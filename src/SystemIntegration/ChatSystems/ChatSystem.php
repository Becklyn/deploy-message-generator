<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

abstract class ChatSystem implements SystemIntegration
{
    protected SymfonyStyle $io;


    public function __construct (SymfonyStyle $io)
    {
        $this->io = $io;
    }


    /**
     * @param TicketInfo[] $tickets
     * @param string[]     $mentions
     * @param string[]     $urls
     *
     * @return ChatMessage[]
     */
    abstract public function getChatMessageThread (
        array $tickets,
        string $deploymentStatus,
        string $project,
        array $mentions,
        array $urls,
        string $deployUser
    ) : array;


    /**
     * Return the {@see Chatter} instance that is being used by this ChatSystem.
     */
    abstract protected function getChatter (?TransportInterface $transport = null) : Chatter;


    /**
     * Sends all Messages as thread of messages.
     * The default implementation will send multiple messages into the configured chat.
     *
     * @param ChatMessage[] $messages
     *
     * @throws TransportExceptionInterface
     *
     * @return SentMessage[]
     */
    public function sendThread (array $messages, ?TransportInterface $transport = null) : array
    {
        $responses = [];

        foreach ($messages as $message)
        {
            $responses[] = $this->sendMessage($message, $transport);
        }

        return $responses;
    }


    /**
     * @throws TransportExceptionInterface
     */
    final public function sendMessage (ChatMessage $message, ?TransportInterface $transport = null) : ?SentMessage
    {
        return $this->getChatter($transport)->send($message);
    }
}
