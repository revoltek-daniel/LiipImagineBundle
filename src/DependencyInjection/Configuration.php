<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\DependencyInjection;

use Liip\ImagineBundle\Config\Controller\ControllerConfig;
use Liip\ImagineBundle\Controller\ImagineController;
use Liip\ImagineBundle\DependencyInjection\Factory\FactoryInterface;
use Liip\ImagineBundle\DependencyInjection\Factory\Loader\LoaderFactoryInterface;
use Liip\ImagineBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Configuration implements ConfigurationInterface
{
    /**
     * @var ResolverFactoryInterface[]
     */
    protected array $resolversFactories;

    /**
     * @var LoaderFactoryInterface[]
     */
    protected array $loadersFactories;

    /**
     * @param ResolverFactoryInterface[] $resolversFactories
     * @param LoaderFactoryInterface[]   $loadersFactories
     */
    public function __construct(array $resolversFactories, array $loadersFactories)
    {
        $this->resolversFactories = $resolversFactories;
        $this->loadersFactories = $loadersFactories;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('liip_imagine');
        $rootNode = $treeBuilder->getRootNode();

        $resolversPrototypeNode = $rootNode
            ->children()
                ->arrayNode('resolvers')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->performNoDeepMerging();
        $this->addResolversSections($resolversPrototypeNode);

        $loadersPrototypeNode = $rootNode
            ->children()
                ->arrayNode('loaders')
                    ->useAttributeAsKey('name')
                    ->prototype('array');
        $this->addLoadersSections($loadersPrototypeNode);

        $rootNode
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return
                        empty($v['loaders'])
                        || empty($v['loaders']['default'])
                        || empty($v['resolvers'])
                        || empty($v['resolvers']['default']);
                })
                ->then(function ($v) {
                    if (empty($v['loaders'])) {
                        $v['loaders'] = [];
                    }

                    if (false === \is_array($v['loaders'])) {
                        throw new \LogicException('Loaders has to be array');
                    }

                    if (false === \array_key_exists('default', $v['loaders'])) {
                        $v['loaders']['default'] = ['filesystem' => null];
                    }

                    if (empty($v['resolvers'])) {
                        $v['resolvers'] = [];
                    }

                    if (false === \is_array($v['resolvers'])) {
                        throw new \LogicException('Resolvers has to be array');
                    }

                    if (false === \array_key_exists('default', $v['resolvers'])) {
                        $v['resolvers']['default'] = ['web_path' => null];
                    }

                    return $v;
                })
            ->end();

        $rootNode
            ->fixXmlConfig('filter_set', 'filter_sets')
            ->children()
                ->scalarNode('driver')->defaultValue('gd')
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !\in_array($v, ['gd', 'imagick', 'gmagick', 'vips'], true);
                        })
                        ->thenInvalid('Invalid imagine driver specified: %s')
                    ->end()
                ->end()
                ->scalarNode('cache')->defaultValue('default')->end()
                ->scalarNode('cache_base_path')->defaultValue('')->end()
                ->scalarNode('data_loader')->defaultValue('default')->end()
                ->scalarNode('default_image')->defaultNull()->end()
                ->arrayNode('default_filter_set_settings')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('quality')->defaultValue(100)->end()
                        ->scalarNode('jpeg_quality')->defaultNull()->end()
                        ->scalarNode('png_compression_level')->defaultNull()->end()
                        ->scalarNode('png_compression_filter')->defaultNull()->end()
                        ->scalarNode('format')->defaultNull()->end()
                        ->booleanNode('animated')->defaultFalse()->end()
                        ->scalarNode('cache')->defaultNull()->end()
                        ->scalarNode('data_loader')->defaultNull()->end()
                        ->scalarNode('default_image')->defaultNull()->end()
                        ->arrayNode('filters')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->useAttributeAsKey('name')
                                ->ignoreExtraKeys(false)
                                ->prototype('variable')->end()
                            ->end()
                        ->end()
                        ->arrayNode('post_processors')
                            ->defaultValue([])
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->useAttributeAsKey('name')
                                ->ignoreExtraKeys(false)
                                ->prototype('variable')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('controller')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('filter_action')->defaultValue(\sprintf('%s::filterAction', ImagineController::class))->end()
                        ->scalarNode('filter_runtime_action')->defaultValue(\sprintf('%s::filterRuntimeAction', ImagineController::class))->end()
                        ->integerNode('redirect_response_code')->defaultValue(302)
                            ->validate()
                                ->ifTrue(function ($redirectResponseCode) {
                                    return !\in_array($redirectResponseCode, ControllerConfig::REDIRECT_RESPONSE_CODES, true);
                                })
                                ->thenInvalid('Invalid redirect response code "%s" (must be 201, 301, 302, 303, 307, or 308).')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('filter_sets')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('filter', 'filters')
                        ->children()
                            ->scalarNode('quality')->end()
                            ->scalarNode('jpeg_quality')->end()
                            ->scalarNode('png_compression_level')->end()
                            ->scalarNode('png_compression_filter')->end()
                            ->scalarNode('format')->end()
                            ->booleanNode('animated')->end()
                            ->scalarNode('cache')->end()
                            ->scalarNode('data_loader')->end()
                            ->scalarNode('default_image')->end()
                            ->arrayNode('filters')
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->useAttributeAsKey('name')
                                    ->ignoreExtraKeys(false)
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                            ->arrayNode('post_processors')
                                ->defaultValue([])
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->useAttributeAsKey('name')
                                    ->ignoreExtraKeys(false)
                                    ->prototype('variable')->end()
                                ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('twig')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('mode')
                        ->defaultValue('lazy')
                        ->info('Twig mode: none/lazy (default)')
                        ->values(['none', 'lazy'])
                    ->end()
                    ->scalarNode('assets_version')->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('messenger')
                ->canBeEnabled()
                ->info('Enables integration with symfony/messenger if set true. Warmup image caches in background by sending messages to MQ.')
            ->end()
        ->end();

        $rootNode
            ->children()
                ->arrayNode('webp')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('generate')->defaultFalse()->end()
                        ->integerNode('quality')->defaultValue(100)->end()
                        ->scalarNode('cache')->defaultNull()->end()
                        ->scalarNode('data_loader')->defaultNull()->end()
                        ->arrayNode('post_processors')
                            ->defaultValue([])
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->useAttributeAsKey('name')
                                ->ignoreExtraKeys(false)
                                ->prototype('variable')->end()
                            ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private function addResolversSections(ArrayNodeDefinition $resolversPrototypeNode): void
    {
        $this->addConfigurationSections($this->resolversFactories, $resolversPrototypeNode, 'resolver');
    }

    private function addLoadersSections(ArrayNodeDefinition $resolversPrototypeNode): void
    {
        $this->addConfigurationSections($this->loadersFactories, $resolversPrototypeNode, 'loader');
    }

    /**
     * @param FactoryInterface[] $factories
     */
    private function addConfigurationSections(array $factories, ArrayNodeDefinition $definition, string $type): void
    {
        foreach ($factories as $f) {
            $f->addConfiguration($definition->children()->arrayNode($f->getName()));
        }

        $nodeDefinition = $definition->end();

        \assert($nodeDefinition instanceof ArrayNodeDefinition);

        $nodeDefinition
            ->validate()
            ->ifTrue(function ($array) use ($type) {
                foreach ($array as $name => $element) {
                    if (!$element) {
                        throw new InvalidConfigurationException(ucfirst($type).' "'.$name.'" must have a factory configured');
                    }
                }

                return false;
            })
            ->thenInvalid('Each '.$type.' must have a factory configured')
            ->end();
    }
}
