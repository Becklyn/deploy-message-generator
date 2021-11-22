<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\TicketSystems;

class TicketInfo
{
    private string $url;

    private string $id;

    private string $title;

    public function __construct (string $id, string $title, string $url)
    {
        $this->title = $title;
        $this->id = $id;
        $this->url = $url;
    }

    public function getUrl () : string
    {
        return $this->url;
    }

    public function getId () : string
    {
        return $this->id;
    }

    public function getTitle () : string
    {
        return $this->title;
    }
}
