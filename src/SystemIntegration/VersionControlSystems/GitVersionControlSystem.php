<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\SystemIntegration\VersionControlSystems;

use Symfony\Component\Process\Process;

class GitVersionControlSystem extends VersionControlSystem
{
    /**
     * @inheritDoc
     */
    public function getName () : string
    {
        return "git";
    }


    /**
     * @inheritDoc
     */
    protected function getChangelogFromCommitRange (string $commitRange) : string
    {
        $process = new Process(["git", "log", $commitRange]);
        $exitcode = $process->run();

        if ($process->isSuccessful()) {
            throw new \Exception("Cannot fetch the changelog from commit range \"{$commitRange}\". {$process->getErrorOutput()}", $exitcode);
        }

        return $process->getOutput();
    }
}
