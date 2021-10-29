<?php declare(strict_types=1);

namespace Tests\Becklyn\DeployMessageGenerator\SystemIntegrations\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Becklyn\DeployMessageGenerator\Exception\InvalidDeploymentEnvironmentException;
use Becklyn\DeployMessageGenerator\Exception\IOException;
use Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems\JiraTicketSytem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Runtime\GenericRuntime;

class JiraIntegrationTest extends TestCase
{
    private const BECKLYN_JIRA_DOMAIN = 'becklyn.atlassian.net';
    private const DEPLOYMENT_FIELD = "Deployed?";
    private const TEST_TICKET = 'MAYD-552';

    static private ?JiraTicketSytem $jira = null;
    static private DeployMessageGeneratorConfig $config;
    static private SymfonyStyle $io;

    public static function setUpBeforeClass () : void
    {
        parent::setUpBeforeClass();

        self::$config = new DeployMessageGeneratorConfig(dirname(__DIR__, 3) . '/examples');
        $jiraConfig = self::$config->getConfigFor('jira');

        self::$io = new SymfonyStyle(new StringInput(""), new ConsoleOutput());

        $bootstrap = require \dirname(__DIR__, 2) . '/bootstrap.php';
        $context = $bootstrap($_ENV + $_SERVER);

        if (!empty($context['JIRA_USER']) && !empty($context['JIRA_ACCESS_TOKEN']))
        {
            $jiraUser = $context['JIRA_USER'];
            $token = $context['JIRA_ACCESS_TOKEN'];

            self::$jira = new JiraTicketSytem(self::$io, self::$config, self::DEPLOYMENT_FIELD, self::BECKLYN_JIRA_DOMAIN, $jiraUser, $token);
        }
    }


    public function testGetTicketInfo () : void
    {
        if (null === self::$jira) {
            self::markTestSkipped("This integration test can only be run with a valid credentials");
        }

        $ticketInfo = self::$jira->getTicketInfo(self::TEST_TICKET);
        $baseUrl = self::BECKLYN_JIRA_DOMAIN;

        self::assertEquals(self::TEST_TICKET, $ticketInfo->getId());
        self::assertEquals("TEST TICKET FOR deploy-message-generator", $ticketInfo->getTitle());
        self::assertEquals("https://$baseUrl/browse/{$ticketInfo->getId()}", $ticketInfo->getUrl());
    }


    public function testChangingDeploymentStatus () : void
    {
        if (null === self::$jira)
        {
            self::markTestSkipped('This integration test can only be run with a valid credentials');
        }

        $id = self::TEST_TICKET;
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
        $jira = new JiraTicketSytem(self::$io, self::$config, self::DEPLOYMENT_FIELD, self::BECKLYN_JIRA_DOMAIN, $user, $token);
        $jira->getTicketInfo(self::TEST_TICKET);
    }


    public function testInvalidTicket () : void
    {
        if (null === self::$jira)
        {
            self::markTestSkipped('This integration test can only be run with a valid credentials');
        }

        $id = "NULL";

        $this->expectException(\Exception::class);
        self::$jira->getTicketInfo($id);
    }


    public function testInvalidEnvironment () : void
    {
        if (null === self::$jira)
        {
            self::markTestSkipped('This integration test can only be run with a valid credentials');
        }

        $environment = "foo";

        $this->expectException(InvalidDeploymentEnvironmentException::class);
        self::$jira->changeDeploymentStatus(self::TEST_TICKET, $environment);
    }
}
