<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Unit\Service\PackageSynchronizer;

use Buddy\Repman\Entity\Organization\Package;
use Buddy\Repman\Repository\PackageRepository;
use Buddy\Repman\Service\Dist\Storage\FileStorage;
use Buddy\Repman\Service\Organization\PackageManager;
use Buddy\Repman\Service\PackageNormalizer;
use Buddy\Repman\Service\PackageSynchronizer\ComposerPackageSynchronizer;
use Buddy\Repman\Tests\Doubles\FakeDownloader;
use Buddy\Repman\Tests\MotherObject\PackageMother;
use PHPUnit\Framework\TestCase;

final class ComposerPackageSynchronizerTest extends TestCase
{
    private ComposerPackageSynchronizer $synchronizer;
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir().'/repman';
        $this->synchronizer = new ComposerPackageSynchronizer(
            new PackageManager(new FileStorage($this->baseDir, new FakeDownloader()), $this->baseDir),
            new PackageNormalizer(),
            $this->createMock(PackageRepository::class)
        );
    }

    public function testSynchronizePackageFromLocalPath(): void
    {
        $path = $this->baseDir.'/buddy/p/buddy-works/repman.json';
        @unlink($path);

        $this->synchronizer->synchronize(PackageMother::withOrganization('path', __DIR__.'/../../../../', 'buddy'));

        self::assertFileExists($path);

        $json = unserialize((string) file_get_contents($path));
        self::assertTrue($json['packages']['buddy-works/repman'] !== []);
        @unlink($path);
    }

    public function testSynchronizeError(): void
    {
        $this->synchronizer->synchronize(PackageMother::withOrganization('artifact', '/non/exist/path', 'buddy'));
        // exception was not throw
        self::assertTrue(true);
    }

    public function testSynchronizePackageFromArtifacts(): void
    {
        $path = $this->baseDir.'/buddy/p/buddy-works/alpha.json';
        @unlink($path);

        $this->synchronizer->synchronize(PackageMother::withOrganization('artifact', __DIR__.'/../../../Resources/artifacts', 'buddy'));

        self::assertFileExists($path);

        $json = unserialize((string) file_get_contents($path));
        self::assertCount(4, $json['packages']['buddy-works/alpha']);
        @unlink($path);
    }

    public function testSynchronizePackageThatAlreadyExists(): void
    {
        $path = $this->baseDir.'/buddy/p/buddy-works/alpha.json';
        @unlink($path);

        $packageMock = $this->createMock(Package::class);
        $packagesMock = $this->createMock(PackageRepository::class);
        $packagesMock->method('findOneBy')->willReturn($packageMock);

        $synchronizer = new ComposerPackageSynchronizer(
            new PackageManager(new FileStorage($this->baseDir, new FakeDownloader()), $this->baseDir),
            new PackageNormalizer(),
            $packagesMock
        );

        $synchronizer->synchronize(PackageMother::withOrganization('artifact', __DIR__.'/../../../Resources/artifacts', 'buddy'));

        self::assertFileNotExists($path);
    }

    public function testSynchronizePackageWithToken(): void
    {
        $this->synchronizer->synchronize(PackageMother::withOrganizationAndToken('artifact', __DIR__.'/../../../Resources/artifacts', 'buddy'));

        // exception was not throw
        self::assertTrue(true);
    }
}
