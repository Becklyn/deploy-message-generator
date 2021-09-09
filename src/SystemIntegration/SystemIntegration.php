<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration;

interface SystemIntegration
{
    /**
     * The name of the system
     */
    public function getName() : string;
}
