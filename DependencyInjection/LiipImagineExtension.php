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

use Imagine\Vips\Imagine;
use Liip\ImagineBundle\DependencyInjection\Factory\Loader\LoaderFactoryInterface;
use Liip\ImagineBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;

class LiipImagineExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var ResolverFactoryInterface[]
     */
    private $resolversFactories = [];

    /**
     * @var LoaderFactoryInterface[]
     */
    private $loadersFactories = [];

    public function addResolverFactory(ResolverFactoryInterface $resolverFactory)
    {
        $this->resolversFactories[$resolverFactory->getName()] = $resolverFactory;
    }

    public function addLoaderFactory(LoaderFactoryInterface $loaderFactory)
    {
        $this->loadersFactories[$loaderFactory->getName()] = $loaderFactory;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration($this->resolversFactories, $this->loadersFactories);
    }

    /**
     * @see \Symfony\Component\DependencyInjection\Extension.ExtensionInterface::load()
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(
            $this->getConfiguration($configs, $container),
            $configs
        );

        if (interface_exists(MimeTypeGuesserInterface::class)) {
            $mimeTypes = new Definition(MimeTypes::class);
            $mimeTypes->setFactory([MimeTypes::class, 'getDefault']);

            $container->setDefinition('liip_imagine.mime_types', $mimeTypes);
        }

        $container->setParameter('liip_imagine.resolvers', $config['resolvers']);
        $container->setParameter('liip_imagine.loaders', $config['loaders']);

        $this->loadResolvers($config['resolvers'], $container);
        $this->loadLoaders($config['loaders'], $container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('imagine.xml');

        if ('none' !== $config['twig']['mode']) {
            $this->loadTwig($config['twig'], $loader, $container);
        }

        $loader->load('commands.xml');

        if ($this->isConfigEnabled($container, $config['messenger'])) {
            $this->registerMessengerConfiguration($loader);
        }

        if ($config['enqueue']) {
            $loader->load('enqueue.xml');
        }

        if ($config['templating']) {
            $loader->load('templating.xml');
        }

        $driver = $config['driver'];
        if ('vips' === $driver) {
            if (!class_exists(Imagine::class)) {
                $vipsImagineClass = Imagine::class;

                throw new \RuntimeException("Unable to use 'vips' driver without '{$vipsImagineClass}' class.");
            }

            $loader->load('imagine_vips.xml');
        }

        $container->setParameter('liip_imagine.driver_service', "liip_imagine.{$driver}");

        $container
            ->getDefinition('liip_imagine.controller.config')
            ->replaceArgument(0, $config['controller']['redirect_response_code']);

        $container->setAlias('liip_imagine', new Alias("liip_imagine.{$driver}"));
        $container->setAlias(CacheManager::class, new Alias('liip_imagine.cache.manager', false));
        $container->setAlias(DataManager::class, new Alias('liip_imagine.data.manager', false));
        $container->setAlias(FilterManager::class, new Alias('liip_imagine.filter.manager', false));

        $container->setParameter('liip_imagine.cache.resolver.default', $config['cache']);

        $container->setParameter('liip_imagine.default_image', $config['default_image']);

        $filterSets = $this->createFilterSets($config['default_filter_set_settings'], $config['filter_sets']);

        $container->setParameter('liip_imagine.filter_sets', $filterSets);
        $container->setParameter('liip_imagine.binary.loader.default', $config['data_loader']);

        $container->setParameter('liip_imagine.controller.filter_action', $config['controller']['filter_action']);
        $container->setParameter('liip_imagine.controller.filter_runtime_action', $config['controller']['filter_runtime_action']);

        if ($container->hasDefinition('liip_imagine.mime_types')) {
            $mimeTypes = $container->getDefinition('liip_imagine.mime_types');
            $container->getDefinition('liip_imagine.binary.mime_type_guesser')
                ->replaceArgument(0, $mimeTypes);

            $container->getDefinition('liip_imagine.data.manager')
                ->replaceArgument(1, $mimeTypes);
        }

        $this->deprecationTemplatingFilterHelper($container);

        $container->setParameter('liip_imagine.webp.generate', $config['webp']['generate']);
        $webpOptions = $config['webp'];
        unset($webpOptions['generate']);
        $container->setParameter('liip_imagine.webp.options', $webpOptions);
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', ['form_themes' => ['@LiipImagine/Form/form_div_layout.html.twig']]);
        }
    }

    private function createFilterSets(array $defaultFilterSets, array $filterSets): array
    {
        return array_map(function (array $filterSet) use ($defaultFilterSets) {
            return array_replace_recursive($defaultFilterSets, $filterSet);
        }, $filterSets);
    }

    private function loadResolvers(array $config, ContainerBuilder $container)
    {
        $this->createFactories($this->resolversFactories, $config, $container);
    }

    private function loadLoaders(array $config, ContainerBuilder $container)
    {
        $this->createFactories($this->loadersFactories, $config, $container);
    }

    private function createFactories(array $factories, array $configurations, ContainerBuilder $container)
    {
        foreach ($configurations as $name => $conf) {
            $factories[key($conf)]->create($container, $name, $conf[key($conf)]);
        }
    }

    private function registerMessengerConfiguration(XmlFileLoader $loader): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }

        $loader->load('messenger.xml');
    }

    private function deprecationTemplatingFilterHelper(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('liip_imagine.templating.filter_helper')) {
            return;
        }

        $message = 'The "%service_id%" service is deprecated since LiipImagineBundle 2.2 and will be removed in 3.0.';
        $definition = $container->getDefinition('liip_imagine.templating.filter_helper');

        if (method_exists(Definition::class, 'getDeprecation')) {
            $definition->setDeprecated('liip/imagine-bundle', '2.2', $message);
        } else {
            $definition->setDeprecated(true, $message);
        }
    }

    private function loadTwig(array $config, XmlFileLoader $loader, ContainerBuilder $container): void
    {
        if ('legacy' === $config['mode']) {
            $loader->load('imagine_twig_mode_legacy.xml');

            return;
        }

        if ('lazy' === $config['mode']) {
            $loader->load('imagine_twig_mode_lazy.xml');
            if (\array_key_exists('assets_version', $config) && null !== $config['assets_version']) {
                $runtime = $container->getDefinition('liip_imagine.templating.filter_runtime');
                $runtime->setArgument(1, $config['assets_version']);
            }

            return;
        }

        throw new InvalidConfigurationException('Unexpected twig mode '.$config['mode']);
    }
}
