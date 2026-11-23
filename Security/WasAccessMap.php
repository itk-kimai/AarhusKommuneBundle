<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Security;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Makes the `/was` routes public.
 *
 * Kimai's `access_control` requires ROLE_USER for every path under a locale,
 * and a plugin cannot add a rule: SecurityBundle forbids a second
 * `access_control` section. The router matches before the firewall runs, so
 * the route name is known here.
 */
#[AsDecorator('security.access_map')]
final readonly class WasAccessMap implements AccessMapInterface
{
    private const array PUBLIC_ROUTES = ['aarhuskommune_was', 'aarhuskommune_was_locale'];

    public function __construct(
        #[AutowireDecorated]
        private AccessMapInterface $inner,
    ) {
    }

    public function getPatterns(Request $request): array
    {
        $patterns = $this->inner->getPatterns($request);

        if (\in_array($request->attributes->get('_route'), self::PUBLIC_ROUTES, true)) {
            return [[AuthenticatedVoter::PUBLIC_ACCESS], $patterns[1]];
        }

        return $patterns;
    }
}
