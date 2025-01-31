<?php

/*
 * This file is part of the `liip/LiipImagineBundle` project.
 *
 * (c) https://github.com/liip/LiipImagineBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Liip\ImagineBundle\Tests\Functional\Binary\Locator;

use Liip\ImagineBundle\Binary\Locator\FileSystemLocator;
use Liip\ImagineBundle\Binary\Locator\LocatorInterface;
use Liip\ImagineBundle\Tests\Functional\AbstractWebTestCase;

/**
 * @covers \Liip\ImagineBundle\Binary\Locator\FileSystemLocator
 */
class FileSystemLocatorTest extends AbstractWebTestCase
{
    public function testBundleResourcesOnAllLoader(): void
    {
        static::createClient();

        $locator = $this->getFileSystemLoaderLocator('bundles_all');
        $roots = $this->getPrivateProperty($locator, 'roots');

        $this->assertTrue(\count($roots) >= 2);
        $this->assertStringEndsWith('FooBundle/Resources/public', $roots['LiipFooBundle']);
        $this->assertStringEndsWith('BarBundle/Resources/public', $roots['LiipBarBundle']);

        $this->assertFooBundleResourcesExist($locator);
        $this->assertBarBundleResourcesExist($locator);
    }

    public function testBundleResourcesOnFooLoader(): void
    {
        static::createClient();

        $locator = $this->getFileSystemLoaderLocator('bundles_only_foo');
        $roots = $this->getPrivateProperty($locator, 'roots');

        $this->assertTrue(\count($roots) >= 1);
        $this->assertStringEndsWith('FooBundle/Resources/public', $roots['LiipFooBundle']);

        $this->assertFooBundleResourcesExist($locator);
    }

    public function testBundleResourcesOnBarLoader(): void
    {
        static::createClient();

        $locator = $this->getFileSystemLoaderLocator('bundles_only_bar');
        $roots = $this->getPrivateProperty($locator, 'roots');

        $this->assertTrue(\count($roots) >= 1);
        $this->assertStringEndsWith('BarBundle/Resources/public', $roots['LiipBarBundle']);

        $this->assertBarBundleResourcesExist($locator, true);
    }

    private function getFileSystemLoaderLocator(string $name): FileSystemLocator
    {
        return $this->getPrivateProperty($this->getService(\sprintf('liip_imagine.binary.loader.%s', $name)), 'locator');
    }

    private function assertFooBundleResourcesExist(LocatorInterface $locator): void
    {
        $this->assertLocatedFileContentsStartsWith($locator, 'file.ext', 'Fixtures/FooBundle');
        $this->assertLocatedFileContentsStartsWith($locator, '@LiipFooBundle:file.ext', 'Fixtures/FooBundle');
        $this->assertLocatedFileContentsStartsWith($locator, 'foo-bundle-file.ext', 'Fixtures/FooBundle');
        $this->assertLocatedFileContentsStartsWith($locator, '@LiipFooBundle:foo-bundle-file.ext', 'Fixtures/FooBundle');
    }

    private function assertBarBundleResourcesExist(LocatorInterface $locator, bool $only = false): void
    {
        $this->assertLocatedFileContentsStartsWith($locator, 'file.ext', $only ? 'Fixtures/BarBundle' : 'Fixtures/FooBundle');
        $this->assertLocatedFileContentsStartsWith($locator, '@LiipBarBundle:file.ext', 'Fixtures/BarBundle');
        $this->assertLocatedFileContentsStartsWith($locator, 'bar-bundle-file.ext', 'Fixtures/BarBundle');
        $this->assertLocatedFileContentsStartsWith($locator, '@LiipBarBundle:bar-bundle-file.ext', 'Fixtures/BarBundle');
    }

    private function assertLocatedFileContentsStartsWith(LocatorInterface $locator, string $filePath, string $expectedContents, $message = ''): void
    {
        $this->assertStringStartsWith($expectedContents, file_get_contents($locator->locate($filePath)), $message);
    }
}
