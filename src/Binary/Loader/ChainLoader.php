<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Binary\Loader;

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;

class ChainLoader implements LoaderInterface
{
    /**
     * @var LoaderInterface[]
     */
    private array $loaders;

    /**
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders)
    {
        $this->loaders = array_filter($loaders, function ($loader) {
            return $loader instanceof LoaderInterface;
        });
    }

    public function find($path)
    {
        $exceptions = [];

        foreach ($this->loaders as $loader) {
            try {
                return $loader->find($path);
            } catch (\Exception $e) {
                $exceptions[$e->getMessage()] = $loader;
            }
        }

        throw new NotLoadableException(self::getLoaderExceptionMessage($path, $exceptions, $this->loaders));
    }

    /**
     * @param array<string, LoaderInterface> $exceptions
     * @param array<string, LoaderInterface> $loaders
     */
    private static function getLoaderExceptionMessage(string $path, array $exceptions, array $loaders): string
    {
        $loaderMessages = array_map(static function (string $name, LoaderInterface $loader): string {
            return \sprintf('%s=[%s]', (new \ReflectionObject($loader))->getShortName(), $name);
        }, array_keys($loaders), $loaders);

        $exceptionMessages = array_map(static function (string $message, LoaderInterface $loader): string {
            return \sprintf('%s=[%s]', (new \ReflectionObject($loader))->getShortName(), $message);
        }, array_keys($exceptions), $exceptions);

        return vsprintf('Source image not resolvable "%s" using "%s" %d loaders (internal exceptions: %s).', [
            $path,
            implode(', ', $loaderMessages),
            \count($loaders),
            implode(', ', $exceptionMessages),
        ]);
    }
}
