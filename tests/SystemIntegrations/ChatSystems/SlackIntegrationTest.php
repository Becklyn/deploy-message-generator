<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\ChatSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Transport\NullTransport;

class SlackIntegrationTest extends TestCase
{
    static private SlackChatSystem $slack;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        $io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());
        self::$slack = new SlackChatSystem($io, "My-Token", "My-Deployment-Channel");
    }


    /**
     * @doesNotPerformAssertions
     */
    public function testMessageSending () : void
    {
        $tickets = [
            new TicketInfo("FOO-1", "Goto google.com", "https://google.com"),
            new TicketInfo("FOO-2", "Goto github.com", "https://github.com")
        ];

        $message = self::$slack->getChatMessage($tickets, "Nowhere", "deploy-message-generator-test");
        self::$slack->sendMessage($message, new NullTransport());
    }
}
