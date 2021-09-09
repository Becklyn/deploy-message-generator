<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\SystemIntegration;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

abstract class ChatSystem implements SystemIntegration
{
    protected SymfonyStyle $io;

    public function __construct (SymfonyStyle $io)
    {
        $this->io = $io;
    }

    abstract public function getChatMessage(array $tickets, string $deploymentStatus, string $project) : ChatMessage;

    abstract protected function getChatter() : Chatter;

    /**
     * @throws TransportExceptionInterface
     */
    final public function sendMessage(ChatMessage $message) : void
    {
        $this->getChatter()->send($message);
    }
}
