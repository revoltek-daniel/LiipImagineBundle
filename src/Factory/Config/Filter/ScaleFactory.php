<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Factory\Config\Filter;

use Liip\ImagineBundle\Config\Filter\Type\Scale;
use Liip\ImagineBundle\Config\FilterInterface;
use Liip\ImagineBundle\Factory\Config\Filter\Argument\SizeFactory;
use Liip\ImagineBundle\Factory\Config\FilterFactoryInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class ScaleFactory implements FilterFactoryInterface
{
    private SizeFactory $sizeFactory;

    public function __construct(SizeFactory $sizeFactory)
    {
        $this->sizeFactory = $sizeFactory;
    }

    public function getName(): string
    {
        return Scale::NAME;
    }

    public function create(array $options): FilterInterface
    {
        $dimensions = $this->sizeFactory->createFromOptions($options, 'dim');
        $to = \array_key_exists('to', $options) ? (float) $options['to'] : null;

        return new Scale($dimensions, $to);
    }
}