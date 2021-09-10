<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\TicketSystems;

use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSytem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class JiraIntegrationTest extends TestCase
{
    static private JiraTicketSytem $jira;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        $io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());
        self::$jira = new JiraTicketSytem($io);
    }


    public function testGetTicketInfo () : void
    {
        if (!isset($_ENV["JIRA_TEST_TICKET"]))
        {
            self::markTestSkipped("Environment variable JIRA_TEST_TICKET not set");
        }

        try
        {
            $ticketInfo = self::$jira->getTicketInfo($_ENV["JIRA_TEST_TICKET"]);
            $baseUrl = $_ENV["JIRA_DOMAIN"]; // has to be defined or the above line would throw

            self::assertEquals($_ENV["JIRA_TEST_TICKET"], $ticketInfo->getId());
            self::assertEquals("TEST TICKET FOR deploy-message-generator", $ticketInfo->getTitle());
            self::assertEquals("https://$baseUrl/browse/{$ticketInfo->getId()}", $ticketInfo->getUrl());
        }
        catch (\Exception $e)
        {
            self::fail("Threw Exception. " . $e->getMessage());
        }
    }


    public function testChangingDeploymentStatus () : void
    {
        if (!isset($_ENV['JIRA_TEST_TICKET']))
        {
            self::markTestSkipped('Environment variable JIRA_TEST_TICKET not set');
        }

        try
        {
            $id = $_ENV['JIRA_TEST_TICKET'];
            $prevStatus = self::$jira->getDeploymentStatus($id);
            echo $prevStatus;

            self::$jira->changeDeploymentStatus($id, "Staging");
            $currentStatus = self::$jira->getDeploymentStatus($id);

            self::assertNotEquals($prevStatus, $currentStatus);

            self::$jira->changeDeploymentStatus($id, "");
        }
        catch (\Exception $e)
        {
            self::fail('Threw Exception. ' . $e->getMessage());
        }
    }
}
