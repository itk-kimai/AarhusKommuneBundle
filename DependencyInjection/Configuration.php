<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\DependencyInjection;

use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\EventSubscriber\UserSubscriber;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(AarhusKommuneConfiguration::CONFIGURATION_NAME);
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('primary_project')
                    ->info('Id of primary project')
                ->end()
                ->scalarNode('primary_activity')
                    ->info('Id of primary activity')
                ->end()

                ->arrayNode('main_menu')
                    ->children()
                        ->arrayNode('remove')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('route')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->scalarNode('was_url')
                    ->info('Web Accessibility Statement URL')
                ->end()

                // language, timezone and theme come from Kimai's native
                // defaults.user.* configuration; the formatting locale follows
                // the language. Only login_initial_view, which Kimai has no
                // default for, lives here.
                ->arrayNode('user_defaults')
                    ->children()
                        ->scalarNode(UserSubscriber::LOGIN_INITIAL_VIEW)
                            ->defaultValue('quick_entry')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
