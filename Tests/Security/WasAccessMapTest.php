<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Tests\Security;

use KimaiPlugin\AarhusKommuneBundle\Security\WasAccessMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;

#[CoversClass(WasAccessMap::class)]
final class WasAccessMapTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function wasRoutes(): iterable
    {
        yield '/was' => ['aarhuskommune_was'];
        yield '/{_locale}/was' => ['aarhuskommune_was_locale'];
    }

    #[DataProvider('wasRoutes')]
    public function testTheWasRoutesArePublic(string $route): void
    {
        self::assertSame([['PUBLIC_ACCESS'], 'https'], $this->map()->getPatterns($this->request($route)));
    }

    public function testOtherRoutesKeepKimaisRules(): void
    {
        self::assertSame([['ROLE_USER'], 'https'], $this->map()->getPatterns($this->request('timesheet')));
    }

    private function map(): WasAccessMap
    {
        $inner = self::createStub(AccessMapInterface::class);
        $inner->method('getPatterns')->willReturn([['ROLE_USER'], 'https']);

        return new WasAccessMap($inner);
    }

    private function request(string $route): Request
    {
        return new Request(attributes: ['_route' => $route]);
    }
}
