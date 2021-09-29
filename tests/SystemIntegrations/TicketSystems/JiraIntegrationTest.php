<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSytem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class JiraIntegrationTest extends TestCase
{
    static private JiraTicketSytem $jira;
    static private ?string $domain;
    static private array $context;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();
        $config = new DeployMessageGeneratorConfig(dirname(__DIR__, 3));

        self::$context = ["JIRA_TEST_TICKET" => "MAYD-552"] + $_SERVER + $_ENV;
        self::$domain = $config->getConfigFor('jira')['domain'];

        $io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());
        self::$jira = new JiraTicketSytem($io, self::$context, $config);
    }


    public function testGetTicketInfo () : void
    {
        try
        {
            $ticketInfo = self::$jira->getTicketInfo(self::$context["JIRA_TEST_TICKET"]);
            $baseUrl = self::$domain;

            self::assertEquals(self::$context["JIRA_TEST_TICKET"], $ticketInfo->getId());
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
        try
        {
            $id = self::$context['JIRA_TEST_TICKET'];
            $prevStatus = self::$jira->getDeploymentStatus($id);

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
