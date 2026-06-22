<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\EventSubscriber;

use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Entity\User;
use App\Entity\UserPreference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Forces the UI language to follow the user's preference, regardless of the
 * {_locale} segment in the URL.
 *
 * Kimai derives the UI translation language solely from the {_locale} route
 * parameter, so a stale /en/ bookmark, browser history or the post-login
 * target URL renders the wrong language even when the user's preference says
 * otherwise. This subscriber redirects authenticated GET requests whose URL
 * locale does not match the user's preferred language to the same path with
 * the correct locale prefix.
 *
 * When a user has no explicit language preference, Kimai's User::getLanguage()
 * silently falls back to the hard-coded 'en'. To avoid that, the fallback here
 * is the configured default language (kimai.defaults.user.language), the same
 * setting Kimai uses when provisioning new users.
 */
final readonly class LocaleRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SystemConfiguration $systemConfiguration,
        private LocaleService $localeService,
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Runs after Symfony's RouterListener/LocaleListener (priorities 32/16),
        // so the {_locale} route parameter is already applied to the request.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only redirect safe, navigational requests. Never rewrite a POST
        // (e.g. a form submit or the SAML ACS callback) into a GET.
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $language = $this->resolveLanguage($user);

        // Never redirect to a locale Kimai cannot route/translate.
        if (!$this->localeService->isKnownLocale($language)) {
            return;
        }

        // The router has already matched the route (RouterListener, priority 32),
        // so its parameters carry the {_locale} taken from the path. Routes with
        // no {_locale} parameter (/api, /auth/saml/...) are left untouched.
        $route = $request->attributes->get('_route');
        $routeParams = $request->attributes->get('_route_params', []);
        if (!\is_string($route) || !\is_array($routeParams) || !\array_key_exists('_locale', $routeParams)) {
            return;
        }
        if ($routeParams['_locale'] === $language) {
            return;
        }

        // Regenerate the route with the user's locale.
        $target = $this->urlGenerator->generate($route, array_merge($routeParams, ['_locale' => $language]));

        // Safety net: if _locale is only a route default (not a path segment),
        // generate() appends it as a query parameter and the path is unchanged,
        // so redirecting would loop. Only redirect when the path actually moves.
        if (parse_url($target, PHP_URL_PATH) === $request->getPathInfo()) {
            return;
        }

        $queryString = $request->getQueryString();
        if (null !== $queryString) {
            $target .= '?' . $queryString;
        }

        $event->setResponse(new RedirectResponse($target));
    }

    /**
     * The user's preferred language, falling back to the configured default
     * (kimai.defaults.user.language) when no preference is set, instead of
     * Kimai's hard-coded 'en'.
     */
    private function resolveLanguage(User $user): string
    {
        $preference = $user->getPreference(UserPreference::LANGUAGE);
        $value = $preference?->getValue();

        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        return $this->systemConfiguration->getUserDefaultLanguage();
    }
}
