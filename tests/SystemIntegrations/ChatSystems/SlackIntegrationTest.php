<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\ChatSystems;

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
        self::$slack = new SlackChatSystem($io);
    }


    /**
     * This test will actually send a message to a test channel.
     * If the message was sent without throwing exceptions the test will pass.
     * It is up to te tester to check if the message was correctly sent.
     *
     * @doesNotPerformAssertions
     */
    public function testMessageSending () : void
    {
        if (!isset($_ENV["SLACK_TEST_CHANNEL"]))
        {
            self::markTestSkipped("Environment variable SLACK_TEST_CHANNEL not set");
        }

        if (!isset($_ENV["SLACK_DEPLOYMENT_CHANNEL"]))
        {
            self::markTestSkipped("Environment variable SLACK_DEPLOYMENT_CHANNEL not set");
        }

        $env = $_ENV["SLACK_DEPLOYMENT_CHANNEL"];
        $_ENV["SLACK_DEPLOYMENT_CHANNEL"] = $_ENV["SLACK_TEST_CHANNEL"];

        try {
            $tickets = [
                new TicketInfo("FOO-1", "Goto google.com", "https://google.com"),
                new TicketInfo("FOO-2", "Goto github.com", "https://github.com")
            ];

            $message = self::$slack->getChatMessage($tickets, "Nowhere", "deploy-message-generator-test");
            self::$slack->sendMessage($message);

        }
        catch (\Exception $e)
        {
            self::fail("Threw Exception. ".$e->getMessage());
        }
        finally
        {
            $_ENV["SLACK_DEPLOYMENT_CHANNEL"] = $env;
        }
    }
}
