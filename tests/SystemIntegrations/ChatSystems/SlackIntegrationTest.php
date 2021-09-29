<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\ChatSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\SystemIntegration\ChatSystems\SlackChatSystem;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\TicketInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class SlackIntegrationTest extends TestCase
{
    static private SlackChatSystem $slack;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        $io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());
        $context = ['SLACK_MOCK' => 'mock'] + $_SERVER + $_ENV;
        self::$slack = new SlackChatSystem($io, $context, new DeployMessageGeneratorConfig(\dirname(__DIR__, 3)));
    }


    /**
     * @doesNotPerformAssertions
     */
    public function testMessageSending () : void
    {
        try {
            $tickets = [
                new TicketInfo("FOO-1", "Goto google.com", "https://google.com"),
                new TicketInfo("FOO-2", "Goto github.com", "https://github.com")
            ];

            $message = self::$slack->getChatMessage($tickets, "Nowhere", "deploy-message-generator-test");
            self::$slack->sendMessage($message);

        }
        catch (\Throwable $e)
        {
            self::fail("Threw Exception. ".$e->getMessage());
        }
    }
}
