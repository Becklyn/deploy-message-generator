<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

/**
 * @author Marco Woehr <mw@becklyn.com>
 * @since 2022-04-08
 */
class JiraDeploymentResponse
{
    private array $errors = [];
    private ?int $code = null;


    public function getErrors () : array
    {
        return $this->errors;
    }


    public function setErrors (array $errors) : void
    {
        $this->errors = $errors;
    }


    public function getCode () : ?int
    {
        return $this->code;
    }


    public function setCode (?int $code) : void
    {
        $this->code = $code;
    }


    public function isSuccessful () : bool
    {
        return 0 === \count($this->errors) || 202 !== $this->code;
    }
}
