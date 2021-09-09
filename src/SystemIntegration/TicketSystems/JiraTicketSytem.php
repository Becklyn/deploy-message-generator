<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class JiraTicketSytem extends TicketSystem
{
    private ?String $deploymentFieldName;
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
        if (!isset($_ENV["JIRA_DOMAIN"]))
        {
            $this->io->error("Cannot read environment variable JIRA_DOMAIN. Is it set?");
            throw new \Exception();
        }

        try
        {
            $baseUrl = $_ENV["JIRA_DOMAIN"];

            $response = $this->sendRequest("GET", "https://{$baseUrl}/rest/api/2/issue/{$id}?fields=summary");
            $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $title = $data["fields"]["summary"];
            $url = "https://{$baseUrl}/browse/{$id}";

            return new TicketInfo($id, $title, $url);
        }
        catch (\Throwable $e)
        {
            echo $e;
            $this->io->error("Failed to make request to Jira");
            throw new \Exception();
        }
    }


    /**
     * @inheritDoc
     */
    public function getDeploymentStatus (string $id) : string
    {
        if (!isset($_ENV["JIRA_DOMAIN"]))
        {
            $this->io->error("Cannot read environment variable JIRA_DOMAIN. Is it set?");
            throw new \Exception();
        }

        try
        {
            $baseUrl = $_ENV["JIRA_DOMAIN"];
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
            echo $e;
            $this->io->error("Failed to make request to Jira");
            throw new \Exception();
        }
    }

    protected function getDeploymentStatusFieldName() : ?string
    {
        if (isset($this->deploymentFieldName) && null !== $this->deploymentFieldName)
        {
            return $this->deploymentFieldName;
        }

        if (!isset($_ENV["JIRA_DEPLOYMENT_STATUS_FIELD"]))
        {
            $this->io->warning("Cannot read environment variable JIRA_DEPLOYMENT_STATUS_FIELD.");
            return null;
        }

        try
        {
            $baseUrl = $_ENV["JIRA_DOMAIN"];
            $fieldName = $_ENV["JIRA_DEPLOYMENT_STATUS_FIELD"];

            $fieldEndpointResponse = $this->sendRequest("GET", "https://{$baseUrl}/rest/api/2/field");
            $fields = \json_decode($fieldEndpointResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field)
            {
                if (isset($field["name"]) && $fieldName === $field["name"] || isset($field['untranslatedName']) && $fieldName === $field["untranslatedName"])
                {
                    $this->deploymentFieldName = $field["key"];
                    return $this->deploymentFieldName;
                }
            }

            return null;
        } catch (\Throwable $e) {
            echo $e;
            $this->io->error("Failed to make request to Jira");
            throw new \Exception();
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
        if (!isset($_ENV["JIRA_USER"]) || !isset($_ENV["JIRA_ACCESS_TOKEN"]))
        {
            $this->io->error("Cannot read environment variables JIRA_USER and JIRA_ACCESS_TOKEN. Are they set?");
            throw new \Exception();
        }


        $user = $_ENV["JIRA_USER"];
        $token = $_ENV["JIRA_ACCESS_TOKEN"];

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
            $this->io->error("Cannot make API call to Jira. Captcha required");
            throw new \Exception();
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    protected function setDeploymentStatus (string $id, ?string $deploymentStatus) : void
    {
        if (!isset($_ENV["JIRA_DOMAIN"]))
        {
            $this->io->error("Cannot read environment variable JIRA_DOMAIN. Is it set?");
            throw new \Exception();
        }

        try
        {
            $baseUrl = $_ENV["JIRA_DOMAIN"];
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
                echo $response->getContent();
                throw new \Exception();
            }
        }
        catch (\Throwable $e)
        {
            echo $e;
            $this->io->error("Failed to make request to Jira");
            throw new \Exception();
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
