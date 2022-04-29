<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\ProjectInformation;

use Becklyn\DeployMessageGenerator\Config\DeployMessageGeneratorConfigurator;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectInformationRenderer
{
    public function renderProjectInformation (
        SymfonyStyle $io,
        DeployMessageGeneratorConfigurator $configurator
    ) : void
    {
        $allEnvironments = \implode("</>, <fg=green>", $configurator->getAllEnvironments());

        $io->writeln("Project: <fg=green>{$configurator->getProjectName()}</>");
        $io->writeln("All Environments: <fg=green>{$allEnvironments}</>");

        $io->newLine();
        $io->writeln("Staging URL(s):");

        foreach ($configurator->getStagingUrls() as $url)
        {
            $this->renderUrlEntry($io, $url);
        }

        $io->newLine();
        $io->writeln("Production URL(s):");

        foreach ($configurator->getProductionUrls() as $url)
        {
            $this->renderUrlEntry($io, $url);
        }

        $io->newLine(2);
    }


    public function renderGeneralProjectInformationForEnvironment (
        SymfonyStyle $io,
        DeployMessageGeneratorConfigurator $configurator,
        string $environment
    ) : void
    {
        $io->writeln("Project: <fg=green>{$configurator->getProjectName()}</>");
        $io->writeln("Environment: <fg=green>{$environment}</>");

        $io->writeln("{$environment} URL(s):");

        foreach ($configurator->getProjectUrlsForEnvironment($environment) as $url)
        {
            $this->renderUrlEntry($io, $url);
        }

        $io->newLine(2);
    }


    private function renderUrlEntry (
        SymfonyStyle $io,
        string $url
    ) : void
    {
        $io->writeln(\sprintf(
            "<fg=green>  · %s</> ",
            $url,
        ));
    }
}
