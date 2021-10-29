<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
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
     */
    abstract public function  getChatMessage(array $tickets, string $deploymentStatus, string $project) : ChatMessage;

    abstract protected function getChatter(?TransportInterface $transport = null) : Chatter;

    /**
     * @throws TransportExceptionInterface
     */
    final public function sendMessage(ChatMessage $message, ?TransportInterface $transport = null) : void
    {
        $this->getChatter($transport)->send($message);
    }
}
