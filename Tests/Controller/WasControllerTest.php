<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Tests\Controller;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\Controller\WasController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `/was` and `/{_locale}/was` both point here. The controller only reads the
 * configured URL, so it needs no container.
 */
#[CoversClass(WasController::class)]
final class WasControllerTest extends TestCase
{
    private const WAS_URL = 'https://was.digst.dk/tid-aarhuskommune-dk';

    public function testRedirectsToTheConfiguredUrl(): void
    {
        $response = ($this->controller(['aarhus_kommune.was_url' => self::WAS_URL]))();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(self::WAS_URL, $response->getTargetUrl());
    }

    public function testAnUnconfiguredUrlIsANotFound(): void
    {
        // Better a 404 than a redirect to nowhere.
        $this->expectException(NotFoundHttpException::class);

        ($this->controller([]))();
    }

    /**
     * @return iterable<string, array{string|int|null}>
     */
    public static function unusableValues(): iterable
    {
        yield 'blank' => [''];
        yield 'null, as YAML `was_url: ~` arrives' => [null];
        yield 'not a URL at all' => [42];
    }

    #[DataProvider('unusableValues')]
    public function testAnUnusableUrlIsANotFound(string|int|null $url): void
    {
        $this->expectException(NotFoundHttpException::class);

        ($this->controller(['aarhus_kommune.was_url' => $url]))();
    }

    /**
     * @param array<string, string|int|null> $settings flat, dotted bundle settings
     */
    private function controller(array $settings): WasController
    {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        // SystemConfiguration and AarhusKommuneConfiguration are both final, so
        // they are built for real; only the repositories are doubled.
        return new WasController(new AarhusKommuneConfiguration(
            new SystemConfiguration($loader, $settings),
            self::createStub(ProjectRepository::class),
            self::createStub(ActivityRepository::class),
        ));
    }
}
