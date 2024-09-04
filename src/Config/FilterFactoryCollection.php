<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Config;

use Liip\ImagineBundle\Exception\Config\Filter\NotFoundException;
use Liip\ImagineBundle\Factory\Config\FilterFactoryInterface;

class FilterFactoryCollection
{
    /**
     * @var FilterFactoryInterface[]
     */
    private array $filterFactories = [];

    public function __construct(FilterFactoryInterface ...$filterFactories)
    {
        foreach ($filterFactories as $filterFactory) {
            $this->filterFactories[$filterFactory->getName()] = $filterFactory;
        }
    }

    /**
     * @throws NotFoundException
     */
    public function getFilterFactoryByName(string $name): FilterFactoryInterface
    {
        if (!\array_key_exists($name, $this->filterFactories)) {
            throw new NotFoundException(\sprintf("Filter factory with name '%s' was not found.", $name));
        }

        return $this->filterFactories[$name];
    }

    /**
     * @return FilterFactoryInterface[]
     */
    public function getAll(): array
    {
        return $this->filterFactories;
    }
}
