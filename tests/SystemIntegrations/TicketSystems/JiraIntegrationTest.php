<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\Exception\InvalidDeploymentEnvironmentException;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class JiraIntegrationTest extends TestCase
{
    static private ?JiraTicketSystem $jira = null;
    static private DeployMessageGeneratorConfig $config;
    static private SymfonyStyle $io;

    static private String $jiraDomain;
    static private String $deploymentField;
    static private String $testTicket;
    static private String $testTicketTitle;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();

        self::$config = new DeployMessageGeneratorConfig(dirname(__DIR__, 3) . '/examples');
        $jiraConfig = self::$config->getConfigFor('jira');

        self::$io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());

        $bootstrap = require \dirname(__DIR__, 2) . '/bootstrap.php';
        $context = $bootstrap(['APP_ENV' => 'test'] + $_ENV + $_SERVER);

        self::$jiraDomain = $context["JIRA_DOMAIN"] ?? null;
        self::$deploymentField = $context["DEPLOYMENT_FIELD"] ?? null;
        self::$testTicket = $context["TEST_TICKET"] ?? null;
        self::$testTicketTitle = $context["TEST_TICKET_TITLE"] ?? null;

        $jiraUser = $context['JIRA_USER_EMAIL'] ?? null;
        $token = $context['JIRA_ACCESS_TOKEN'] ?? null;

        self::$jira = new JiraTicketSystem(self::$io, self::$config, self::$deploymentField, self::$jiraDomain, $jiraUser, $token);
    }


    public function testGetTicketInfo () : void
    {
        $ticketInfo = self::$jira->getTicketInfo(self::$testTicket);
        $baseUrl = self::$jiraDomain;

        self::assertEquals(self::$testTicket, $ticketInfo->getId());
        self::assertEquals(self::$testTicketTitle, $ticketInfo->getTitle());
        self::assertEquals("https://$baseUrl/browse/{$ticketInfo->getId()}", $ticketInfo->getUrl());
    }


    public function testChangingDeploymentStatus () : void
    {
        $id = self::$testTicket;
        $prevStatus = self::$jira->getDeploymentStatus($id);

        self::$jira->changeDeploymentStatus($id, "Staging");
        $currentStatus = self::$jira->getDeploymentStatus($id);

        self::assertNotEquals($prevStatus, $currentStatus);

        self::$jira->changeDeploymentStatus($id, null);
    }


    public function testInvalidCredentials () : void
    {
        $user = "not-a-user@becklyn.com";
        $token = "notAValidToken";

        $this->expectException(\Exception::class);
        $jira = new JiraTicketSystem(self::$io, self::$config, self::$deploymentField, self::$jiraDomain, $user, $token);
        $jira->getTicketInfo(self::$testTicket);
    }


    public function testInvalidTicket () : void
    {
        $id = "NULL";

        $this->expectException(\Exception::class);
        self::$jira->getTicketInfo($id);
    }


    public function testInvalidEnvironment () : void
    {
        $environment = "foo";

        $this->expectException(InvalidDeploymentEnvironmentException::class);
        self::$jira->changeDeploymentStatus(self::$testTicket, $environment);
    }
}
