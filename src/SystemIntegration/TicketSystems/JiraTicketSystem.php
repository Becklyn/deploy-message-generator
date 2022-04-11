<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
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
    private String $deploymentFieldName;
    private String $domain;
    private string $jiraUser;
    private string $token;


    public function __construct (
        SymfonyStyle $io,
        DeployMessageGeneratorConfig $config,
        string $deploymentFieldName,
        string $domain,
        string $jiraUser,
        string $token
    )
    {
        parent::__construct($io, $config);
        $this->deploymentFieldName = $deploymentFieldName;
        $this->domain = $domain;
        $this->token = $token;
        $this->jiraUser = $jiraUser;

        if (!$this->isDeploymentStatusFieldNameValid())
        {
            throw new \InvalidArgumentException("The field \"{$deploymentFieldName}\" does not exist for your jira installation.");
        }
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
            $response = $this->sendRequest("GET", "https://{$this->domain}/rest/api/2/issue/{$id}?fields={$this->deploymentFieldName}");
            $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $field = $data["fields"][$this->deploymentFieldName];
            $status = "";

            if (!empty($field["value"]))
            {
                $status = $field["value"];
            }

            return $status;
        } catch (\Throwable $e) {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    protected function isDeploymentStatusFieldNameValid() : bool
    {
        try
        {
            $fieldEndpointResponse = $this->sendRequest("GET", "https://{$this->domain}/rest/api/2/field");
            $fields = \json_decode($fieldEndpointResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field)
            {
                if ($this->deploymentFieldName === ($field["name"] ?? null) || $this->deploymentFieldName === ($field["untranslatedName"] ?? null))
                {
                    $this->deploymentFieldName = $field["key"];
                    return true;
                }
            }

            return false;
        }
        catch (\Throwable $e)
        {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }


    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Exception
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
            throw new \Exception("Cannot make API call to Jira. Captcha required");
        }

        return $response;
    }


    /**
     * @inheritDoc
     */
    protected function setDeploymentStatus (string $id, ?string $deploymentStatus) : void
    {
        try
        {
            $status = null;

            if (!empty($deploymentStatus))
            {
                $status = [
                    "value" => $deploymentStatus,
                ];
            }

            $data = [
                "json" => [
                    "fields" => [
                        $this->deploymentFieldName => $status,
                    ],
                ],
            ];

            $response = $this->sendRequest("PUT", "https://{$this->domain}/rest/api/2/issue/{$id}", $data);

            if (204 !== $response->getStatusCode()) {
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
     * Generate a JWT Token from the Jira API with Client Credentials.
     */
    public function generateJiraJwt (array $context) : ?string
    {
        $clientId = $context["JIRA_CLIENT_ID"];
        $secret = $context["JIRA_CLIENT_SECRET"];

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
        catch (\Exception)
        {
            return null;
        }

        return $tokenResponse->toArray()["access_token"];
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
        string $deploymentStatus,
        array $issueKeys,
        string $url
    ) : JiraDeploymentResponse
    {
        $jiraDeploymentResponse = new JiraDeploymentResponse();

        $pipelineId = \uniqid("deployment-", true);
        $environmentId = \uniqid("environment-", true);

        if (0 === \count($issueKeys))
        {
            $jiraDeploymentResponse->setErrors(["no Issue Keys where Provided."]);
            return $jiraDeploymentResponse;
        }

        if (empty($context["JIRA_CLOUD_ID"]))
        {
            $response = $this
                ->sendRequest("GET", "https://becklyn.atlassian.net/_edge/tenant_info")
                ->toArray();

            if (empty($response["cloudId"]))
            {
                throw new \RuntimeException("Could not get a Jira Cloud Id from 'https://becklyn.atlassian.net/_edge/tenant_info'.");
            }

            $cloudId = $response["cloudId"];
        }
        else
        {
            $cloudId = $context["JIRA_CLOUD_ID"];
        }

        $body = [
            "deployments" => [[
                "deploymentSequenceNumber" => 1,
                "updateSequenceNumber" => 1,
                "issueKeys" => $issueKeys,
                "displayName" => $pipelineId,
                "description" => "Autogenerated Deployment with 'becklyn/deploy-message-generator'",
                "lastUpdated" => (new \DateTimeImmutable("now"))->format("Y-m-d\\TH:i:sP"),
                "label" => $pipelineId,
                "state" => "successful",
                "url" => $url,
                "pipeline" => [
                    "id" => $pipelineId,
                    "displayName" => $deploymentStatus,
                    "url" => $url,
                ],
                "environment" => [
                    "id" => $environmentId,
                    "displayName" => $deploymentStatus,
                    "type" => $this->convertDeploymentStatus($deploymentStatus),
                ],
            ]],
        ];

        if (!empty($context["JIRA_JWT"]))
        {
            $jwt = $context["JIRA_JWT"];

            // try to send via env JWT
            $response = $this->sendRequest(
                "POST",
                "https://api.atlassian.com/jira/deployments/0.1/cloud/{$cloudId}/bulk",
                ["json" => $body],
                "Bearer {$jwt}"
            );

            // generate a new JWT and try again
            if (401 === $response->getStatusCode())
            {
                $jwt = $this->generateJiraJwt($context);

                // abort if JWT generation fails
                if (null === $jwt)
                {
                    throw new \Exception("Unable to generate a JWT.");
                }

                $response = $this->sendRequest(
                    "POST",
                    "https://api.atlassian.com/jira/deployments/0.1/cloud/{$cloudId}/bulk",
                    ["json" => $body],
                    "Bearer {$jwt}"
                );
            }

            $jiraDeploymentResponse->setCode($response->getStatusCode());
            $result = $response->toArray();

            if (!empty($result["rejectedDeployments"]))
            {
                $errors = [];

                foreach ($result["rejectedDeployments"] as $rejected)
                {
                    foreach ($rejected["errors"] as $error)
                    {
                        $errors[] = $error["message"];
                    }
                }

                $jiraDeploymentResponse->setErrors($errors);
            }
        }

        return $jiraDeploymentResponse;
    }


    private function convertDeploymentStatus (string $status) : string
    {
        if ("Live" === $status)
        {
            return "production";
        }

        return $status;
    }
}
