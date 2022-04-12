<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Marco Woehr <mw@becklyn.com>
 *
 * @since 2022-04-08
 */
class JiraDeploymentResponse
{
    private ?int $code;
    /** @var string[] */
    private array $errors;


    /**
     * @param string[] $errors
     */
    private function __construct (
        ?int $code,
        array $errors
    )
    {
        $this->code = $code;
        $this->errors = $errors;
    }


    public function getCode () : ?int
    {
        return $this->code;
    }


    public function setCode (?int $code) : self
    {
        $this->code = $code;

        return $this;
    }


    public function getErrors () : array
    {
        return $this->errors;
    }


    public function setErrors (array $errors) : self
    {
        $this->errors = $errors;

        return $this;
    }


    public function isSuccessful () : bool
    {
        return 0 === \count($this->errors) || 202 !== $this->code;
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public static function fromResponse (ResponseInterface $response) : self
    {
        $responseData = $response->toArray();
        $errors = [];

        foreach ($responseData["rejectedDeployments"] as $rejected)
        {
            foreach ($rejected["errors"] as $error)
            {
                $errors[] = $error["message"];
            }
        }

        return new self($response->getStatusCode(), $errors);
    }


    /**
     * @param string[] $errors
     */
    public static function fromErrors (array $errors) : self
    {
        return new self(500, $errors);
    }
}
