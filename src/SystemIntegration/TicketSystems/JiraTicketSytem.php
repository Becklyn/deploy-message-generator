<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Becklyn\DeployMessageGenerator\Exception\IOException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class JiraTicketSytem extends TicketSystem
{
    private ?String $deploymentFieldName = null;

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
        if (!isset($this->config->getConfigFor($this->getName())['domain']))
        {
            throw new IOException("Cannot read configuration variable jira.domain. Is it set?");
        }

        try
        {
            $baseUrl = $this->config->getConfigFor($this->getName())['domain'];

            $response = $this->sendRequest("GET", "https://{$baseUrl}/rest/api/2/issue/{$id}?fields=summary");
            $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $title = $data["fields"]["summary"];
            $url = "https://{$baseUrl}/browse/{$id}";

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
        if (!isset($this->config->getConfigFor($this->getName())['domain']))
        {
            throw new IOException('Cannot read configuration variable jira.domain. Is it set?');
        }

        try
        {
            $baseUrl = $this->config->getConfigFor($this->getName())['domain'];
            $fieldId = $this->getDeploymentStatusFieldName();

            $response = $this->sendRequest("GET", "https://{$baseUrl}/rest/api/2/issue/{$id}?fields={$fieldId}");
            $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $field = $data["fields"][$fieldId];
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

    protected function getDeploymentStatusFieldName() : ?string
    {
        if (isset($this->deploymentFieldName) && null !== $this->deploymentFieldName)
        {
            return $this->deploymentFieldName;
        }

        if (!isset($this->config->getConfigFor($this->getName())['field']))
        {
            $this->io->warning("Cannot read configuration variable jira.field");
            return null;
        }

        try
        {
            $baseUrl = $this->config->getConfigFor($this->getName())['domain'];
            $fieldName = $this->config->getConfigFor($this->getName())['field'];

            $fieldEndpointResponse = $this->sendRequest("GET", "https://{$baseUrl}/rest/api/2/field");
            $fields = \json_decode($fieldEndpointResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field)
            {
                if (isset($field["name"]) && $fieldName === $field["name"] || isset($field["untranslatedName"]) && $fieldName === $field["untranslatedName"])
                {
                    $this->deploymentFieldName = $field["key"];
                    return $this->deploymentFieldName;
                }
            }

            return null;
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
        if (!isset($this->context["JIRA_USER"]) || !isset($this->context["JIRA_ACCESS_TOKEN"]))
        {
            throw new IOException("Cannot read environment variables JIRA_USER and JIRA_ACCESS_TOKEN. Are they set?");
        }

        $user = $this->context["JIRA_USER"];
        $token = $this->context["JIRA_ACCESS_TOKEN"];

        $authorizationCode = \base64_encode("{$user}:{$token}");
        $client = HttpClient::create([
            "headers" => [
                "Authorization" => "Basic {$authorizationCode}",
                "Content-Type" => "application/json",
            ],
        ]);

        $response = $client->request($method, $endpoint, $options);

        if (isset($response->getHeaders(false)["X-Seraph-LoginReason"]) && "AUTHENTICATION_DENIED" === $response->getHeaders(false)["X-Seraph-LoginReason"][0])
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
        if (!isset($this->config->getConfigFor($this->getName())['domain']))
        {
            throw new IOException("Cannot read configuration variable jira.domain. Is it set?");
        }

        try
        {
            $baseUrl = $this->config->getConfigFor($this->getName())['domain'];
            $fieldId = $this->getDeploymentStatusFieldName();
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
                        $fieldId => $status,
                    ],
                ],
            ];

            $response = $this->sendRequest("PUT", "https://{$baseUrl}/rest/api/2/issue/{$id}", $data);

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
