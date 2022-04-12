<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class JiraTicketSystem extends TicketSystem
{
    private string $domain;
    private string $jiraUser;
    private string $token;
    private string $deploymentFieldKey;


    /**
     * @throws \Exception
     */
    public function __construct (
        SymfonyStyle $io,
        DeployMessageGeneratorConfigurator $config,
        string $deploymentFieldName,
        string $domain,
        string $jiraUser,
        string $token
    )
    {
        parent::__construct($io, $config);

        $this->domain = $domain;
        $this->token = $token;
        $this->jiraUser = $jiraUser;
        $this->deploymentFieldKey = $this->getDeploymentFieldKeyByFieldName($deploymentFieldName);
    }


    /**
     * @inheritDoc
     */
    public function getName () : string
    {
        return "jira";
    }


    /**
     * @inheritDoc
     */
    public function getTicketInfo (string $id) : TicketInfo
    {
        try
        {
            $response = $this->sendRequest("GET", "https://{$this->domain}/rest/api/2/issue/{$id}?fields=summary");
            $data = $response->toArray();

            $title = $data["fields"]["summary"];
            $url = "https://{$this->domain}/browse/{$id}";

            return new TicketInfo($id, $title, $url);
        }
        catch (\Throwable $e)
        {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    /**
     * @inheritDoc
     */
    public function getDeploymentStatus (string $id) : string
    {
        try
        {
            $response = $this->sendRequest("GET", "https://{$this->domain}/rest/api/2/issue/{$id}?fields={$this->deploymentFieldKey}");
            $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $field = $data["fields"][$this->deploymentFieldKey];
            $status = "";

            if (!empty($field["value"]))
            {
                $status = $field["value"];
            }

            return $status;
        }
        catch (\Throwable $e)
        {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    private function getDeploymentFieldKeyByFieldName (string $deploymentFieldName) : string
    {
        try
        {
            $fieldEndpointResponse = $this->sendRequest("GET", "https://{$this->domain}/rest/api/2/field");
            $fields = \json_decode($fieldEndpointResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field)
            {
                $name = $field["name"] ?? null;
                $untranslatedName = $field["untranslatedName"] ?? null;

                if ($deploymentFieldName === $name || $deploymentFieldName === $untranslatedName)
                {
                    return $field["key"];
                }
            }

            throw new \InvalidArgumentException("The field \"{$deploymentFieldName}\" does not exist for your jira installation.");
        }
        catch (\RuntimeException|RedirectionExceptionInterface|ClientExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e)
        {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \RuntimeException
     */
    protected function sendRequest (
        string $method,
        string $endpoint,
        array $options = [],
        ?string $authorizationCode = null
    ) : ResponseInterface
    {
        if (null === $authorizationCode)
        {
            $authorizationCode = "Basic " . \base64_encode("{$this->jiraUser}:{$this->token}");
        }

        $client = HttpClient::create([
            "headers" => [
                "Authorization" => $authorizationCode,
                "Content-Type" => "application/json",
            ],
        ]);

        $response = $client->request($method, $endpoint, $options);

        if ("AUTHENTICATION_DENIED" === ($response->getHeaders(false)["X-Seraph-LoginReason"][0] ?? null))
        {
            throw new \RuntimeException("Cannot make API call to Jira. Captcha required");
        }

        return $response;
    }


    /**
     * @inheritDoc
     */
    public function setDeploymentStatus (string $id, ?string $deploymentStatus) : void
    {
        try
        {
            $status = !empty($deploymentStatus)
                ? ["value" => $deploymentStatus]
                : null;

            $response = $this->sendRequest("PUT", "https://{$this->domain}/rest/api/2/issue/{$id}", [
                "json" => [
                    "fields" => [
                        $this->deploymentFieldKey => $status,
                    ],
                ],
            ]);

            if (204 !== $response->getStatusCode())
            {
                throw new TransportException($response->getContent(false), $response, 1);
            }
        }
        catch (\Throwable $e)
        {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    /**
     * @inheritDoc
     */
    public function getTicketIdRegex () : string
    {
        return "/[A-Z]+-[0-9]+/";
    }


    /**
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Exception
     *
     * @inheritDoc
     */
    public function generateDeployments (
        array $context,
        string $environment,
        array $issueKeys,
        array $urls,
        string $commitRange
    ) : JiraDeploymentResponse
    {
        if (0 === \count($issueKeys))
        {
            return JiraDeploymentResponse::fromErrors(["No Jira Issues have been provided."]);
        }

        if (0 === \count($urls))
        {
            return JiraDeploymentResponse::fromErrors(["No Deployment URL(s) have been provided."]);
        }

        $payload = [
            "deployments" => $this->prepareDeploymentInformationPayload(
                $environment,
                $urls,
                $issueKeys,
                $commitRange
            ),
        ];

        $jiraCloudId = $this->getJiraCloudId($context);
        $jwtToken = $this->fetchJwtToken($context);

        if (null === $jwtToken)
        {
            throw new \RuntimeException("Failed to connect to Atlassian API. Could not find existing 'JIRA_JWT_TOKEN' in environment, or generate a new JWT Token.");
        }

        $response = $this->sendDeploymentInformation($jiraCloudId, $payload, $jwtToken);

        // If the previous request has failed due to an invalid/expired JWT Token, then generate a new Token and try again.
        if (401 === $response->getStatusCode())
        {
            $response = $this->sendDeploymentInformation($jiraCloudId, $payload, $this->generateJiraJwt($context));
        }

        return JiraDeploymentResponse::fromResponse($response);
    }


    private function fetchJwtToken (array $context) : ?string
    {
        return $context["JIRA_JWT"] ?? $this->generateJiraJwt($context);
    }


    /**
     * Generate a JWT Token from the Jira API with Client Credentials.
     */
    private function generateJiraJwt (array $context) : ?string
    {
        $clientId = $context["JIRA_CLIENT_ID"] ?? null;
        $secret = $context["JIRA_CLIENT_SECRET"] ?? null;

        if (null === $clientId || null === $secret)
        {
            throw new \RuntimeException("Could not generate JWT Token for Jira API without 'JIRA_CLIENT_ID' and 'JIRA_CLIENT_SECRET' present in the environment configuration.");
        }

        $body = [
            "audience" => "api.atlassian.com",
            "grant_type" => "client_credentials",
            "client_id" => $clientId,
            "client_secret" => $secret,
        ];

        try
        {
            $tokenResponse = $this->sendRequest("POST", "https://api.atlassian.com/oauth/token", ["json" => $body]);
        }
        catch (\Exception $e)
        {
            return null;
        }

        return $tokenResponse->toArray()["access_token"];
    }


    /**
     * @param string[] $urls
     * @param string[] $issueKeys
     */
    private function prepareDeploymentInformationPayload (
        string $environment,
        array $urls,
        array $issueKeys,
        string $commitRange
    ) : array
    {
        $pipelineIdPrefix = \uniqid("deployment-{$commitRange}-", true);
        $environmentIdPrefix = \uniqid("environment-{$commitRange}-", true);
        $deployments = [];

        foreach ($urls as $index => $url)
        {
            $hashedUrl = \sha1($url);
            $pipelineId = "{$pipelineIdPrefix}-{$hashedUrl}";
            $environmentId = "{$environmentIdPrefix}-{$hashedUrl}";

            $deployments[] = [
                "deploymentSequenceNumber" => $index + 1,
                "updateSequenceNumber" => $index + 1,
                "issueKeys" => $issueKeys,
                "displayName" => $pipelineId,
                "description" => "Autogenerated Deployment with 'becklyn/deploy-message-generator' for Commit Range ‘{$commitRange}‘.",
                "lastUpdated" => (new \DateTimeImmutable("now"))->format("Y-m-d\\TH:i:sP"),
                "label" => $pipelineId,
                "state" => "successful",
                "url" => $url,
                "pipeline" => [
                    "id" => $pipelineId,
                    "displayName" => $environment,
                    "url" => $url,
                ],
                "environment" => [
                    "id" => $environmentId,
                    "displayName" => $environment,
                    "type" => $this->convertToJiraDeploymentEnvironment($environment),
                ],
            ];
        }

        return $deployments;
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function sendDeploymentInformation (
        string $cloudId,
        array $payload,
        string $jwtToken
    ) : ResponseInterface
    {
        return $this->sendRequest(
            "POST",
            "https://api.atlassian.com/jira/deployments/0.1/cloud/{$cloudId}/bulk",
            [
                "json" => $payload,
            ],
            "Bearer {$jwtToken}"
        );
    }


    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function getJiraCloudId (array $context) : string
    {
        $cloudId = $context["JIRA_CLOUD_ID"] ?? null;

        if (null !== $cloudId)
        {
            return $cloudId;
        }

        $response = $this
            ->sendRequest("GET", "https://{$this->domain}/_edge/tenant_info")
            ->toArray();

        $cloudId = $response["cloudId"] ?? null;

        if (null === $cloudId)
        {
            throw new \RuntimeException("Could not get a Jira Cloud Id from 'https://{$this->domain}/_edge/tenant_info'.");
        }

        return $cloudId;
    }


    private function convertToJiraDeploymentEnvironment (string $environment) : string
    {
        switch ($environment)
        {
            case "Live":
                return "production";

            default:
                return "staging";
        }
    }
}
