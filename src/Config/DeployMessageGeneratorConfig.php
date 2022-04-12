<?php declare(strict_types=1);

namespace Becklyn\DeployMessageGenerator\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DeployMessageGeneratorConfig implements ConfigurationInterface
{
    public const ROOT_NODE_NAME = "deploy_message_generator";


    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder () : TreeBuilder
    {
        $builder = new TreeBuilder(self::ROOT_NODE_NAME);
        $builder->getRootNode()
            ->normalizeKeys(false)
            ->children()
                ->scalarNode("name")
                    ->isRequired()
                ->end()

                ->enumNode("vcs")
                    ->values(["git"])
                    ->isRequired()
                ->end()

                ->enumNode("ticket-system")
                    ->values(["jira"])
                    ->defaultValue("jira")
                    ->isRequired()
                ->end()

                ->enumNode("chat-system")
                    ->values(["slack"])
                    ->defaultValue("slack")
                    ->isRequired()
                ->end()

                ->arrayNode("jira")
                    ->children()
                        ->scalarNode("domain")
                            ->isRequired()
                        ->end()
                        ->scalarNode("field")
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode("slack")
                    ->children()
                        ->scalarNode("channel")
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode("server")
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->scalarPrototype()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode("urls")
                    ->children()
                        ->arrayNode("staging")
                            ->beforeNormalization()->castToArray()->end()
                            ->defaultValue([])
                            ->requiresAtLeastOneElement()
                            ->scalarPrototype()
                            ->end()
                        ->end()

                        ->arrayNode("production")
                            ->beforeNormalization()->castToArray()->end()
                            ->defaultValue([])
                            ->requiresAtLeastOneElement()
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode("mentions")
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->scalarPrototype()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
