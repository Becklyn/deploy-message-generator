<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfig;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class JiraTicketSytem extends TicketSystem
{
    private String $deploymentFieldName;
    private String $domain;

    private string $jiraUser;

    private string $token;


    public function __construct (SymfonyStyle $io, DeployMessageGeneratorConfig $config, string $deploymentFieldName, string $domain, string $jiraUser, string $token)
    {
        parent::__construct($io, $config);
        $this->deploymentFieldName = $deploymentFieldName;
        $this->domain = $domain;
        $this->token = $token;
        $this->jiraUser = $jiraUser;

        if (!$this->isDeploymentStatusFieldNameValid()) {
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
        } catch (\Throwable $e) {
            throw new \Exception("Failed to make request to Jira", 1, $e);
        }
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function sendRequest(string $method, string $endpoint, array $options = []) : ResponseInterface
    {
        $authorizationCode = \base64_encode("{$this->jiraUser}:{$this->token}");
        $client = HttpClient::create([
            "headers" => [
                "Authorization" => "Basic {$authorizationCode}",
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
}
