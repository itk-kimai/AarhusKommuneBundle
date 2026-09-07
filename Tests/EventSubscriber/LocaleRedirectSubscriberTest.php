<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Tests\EventSubscriber;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\LocaleService;
use App\Configuration\SystemConfiguration;
use App\Entity\User;
use App\Entity\UserPreference;
use KimaiPlugin\AarhusKommuneBundle\EventSubscriber\LocaleRedirectSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The configured default language used throughout, standing in for
 * kimai.defaults.user.language.
 */
#[CoversClass(LocaleRedirectSubscriber::class)]
final class LocaleRedirectSubscriberTest extends TestCase
{
    private const DEFAULT_LANGUAGE = 'da';

    public function testSubscribesToKernelRequest(): void
    {
        $events = LocaleRedirectSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('kernel.request', $events);
        self::assertSame(['onKernelRequest', 0], $events['kernel.request']);
    }

    // --- anonymous visitors -------------------------------------------------
    // Kimai negotiates their locale from Accept-Language, so without this they
    // are served whatever language their browser asks for.

    public function testAnonymousVisitorOnAnotherLocaleIsSentToTheDefault(): void
    {
        $event = $this->dispatch($this->request('/en/login', 'login', ['_locale' => 'en']), user: null);

        self::assertRedirectedTo('/da/login', $event);
    }

    public function testAnonymousVisitorOnAGermanUrlIsSentToTheDefault(): void
    {
        $event = $this->dispatch($this->request('/de/login', 'login', ['_locale' => 'de']), user: null);

        self::assertRedirectedTo('/da/login', $event);
    }

    public function testAnonymousVisitorAlreadyOnTheDefaultIsLeftAlone(): void
    {
        $event = $this->dispatch($this->request('/da/login', 'login', ['_locale' => 'da']), user: null);

        self::assertNull($event->getResponse());
    }

    // --- authenticated users ------------------------------------------------

    public function testUserPreferenceWins(): void
    {
        $event = $this->dispatch(
            $this->request('/da/timesheet/', 'timesheet', ['_locale' => 'da']),
            user: $this->user('en')
        );

        self::assertRedirectedTo('/en/timesheet/', $event);
    }

    public function testUserOnTheirOwnLocaleIsLeftAlone(): void
    {
        $event = $this->dispatch(
            $this->request('/da/timesheet/', 'timesheet', ['_locale' => 'da']),
            user: $this->user('da')
        );

        self::assertNull($event->getResponse());
    }

    public function testUserWithoutAPreferenceFallsBackToTheConfiguredDefault(): void
    {
        // Kimai's User::getLanguage() would answer its hard-coded 'en' here.
        $event = $this->dispatch(
            $this->request('/en/timesheet/', 'timesheet', ['_locale' => 'en']),
            user: $this->user(null)
        );

        self::assertRedirectedTo('/da/timesheet/', $event);
    }

    public function testQueryStringIsPreserved(): void
    {
        $event = $this->dispatch(
            $this->request('/en/timesheet/', 'timesheet', ['_locale' => 'en'], 'page=2&order=DESC'),
            user: null
        );

        // Request::getQueryString() normalises, so the parameters come back
        // sorted by name rather than in the order they were sent.
        self::assertRedirectedTo('/da/timesheet/?order=DESC&page=2', $event);
    }

    // --- requests that must never be touched --------------------------------

    public function testSubRequestsAreIgnored(): void
    {
        $event = new RequestEvent(
            self::createStub(HttpKernelInterface::class),
            $this->request('/en/login', 'login', ['_locale' => 'en']),
            HttpKernelInterface::SUB_REQUEST
        );
        $this->subscriber(null)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('unsafeMethods')]
    public function testOnlyGetIsRedirected(string $method): void
    {
        // Rewriting the SAML ACS callback or a form submit into a GET would
        // drop the request body.
        $event = $this->dispatch(
            $this->request('/en/auth/saml/acs', 'saml_acs', ['_locale' => 'en'], null, $method),
            user: null
        );

        self::assertNull($event->getResponse());
    }

    public function testRoutesWithoutALocaleParameterAreIgnored(): void
    {
        // /api and /auth/saml/... carry no {_locale} segment.
        $event = $this->dispatch($this->request('/api/users', 'get_users', []), user: null);

        self::assertNull($event->getResponse());
    }

    public function testUnroutedRequestsAreIgnored(): void
    {
        $request = Request::create('/en/login');
        $event = $this->dispatch($request, user: null);

        self::assertNull($event->getResponse());
    }

    public function testAnUnknownDefaultLanguageIsNotRedirectedTo(): void
    {
        // Guard against sending anyone to a locale Kimai cannot route.
        $event = $this->dispatch(
            $this->request('/en/login', 'login', ['_locale' => 'en']),
            user: null,
            defaultLanguage: 'xx'
        );

        self::assertNull($event->getResponse());
    }

    public function testNoRedirectWhenOnlyTheQueryStringWouldChange(): void
    {
        // When _locale is a route default rather than a path segment, generate()
        // appends it as a query parameter and the path does not move, so
        // redirecting would loop.
        $event = $this->dispatch(
            $this->request('/login', 'login_no_locale', ['_locale' => 'en']),
            user: null,
            generated: '/login?_locale=da'
        );

        self::assertNull($event->getResponse());
    }

    // --- helpers ------------------------------------------------------------

    private function dispatch(
        Request $request,
        ?User $user,
        string $defaultLanguage = self::DEFAULT_LANGUAGE,
        ?string $generated = null,
    ): RequestEvent {
        $event = new RequestEvent(
            self::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->subscriber($user, $defaultLanguage, $generated)->onKernelRequest($event);

        return $event;
    }

    private function subscriber(
        ?User $user,
        string $defaultLanguage = self::DEFAULT_LANGUAGE,
        ?string $generated = null,
    ): LocaleRedirectSubscriber {
        $tokenStorage = new TokenStorage();
        if (null !== $user) {
            $token = self::createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->setToken($token);
        }

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            // Stand in for the router: swap the locale into the path, which is
            // what generating a localized Kimai route does.
            function (string $route, array $parameters = []) use ($generated): string {
                if (null !== $generated) {
                    return $generated;
                }

                return match ($route) {
                    'login' => '/' . $parameters['_locale'] . '/login',
                    'timesheet' => '/' . $parameters['_locale'] . '/timesheet/',
                    default => '/' . $parameters['_locale'],
                };
            }
        );

        return new LocaleRedirectSubscriber(
            $tokenStorage,
            $this->systemConfiguration($defaultLanguage),
            // Both are final, so they are built for real rather than stubbed.
            // DEFAULT_SETTINGS keeps the per-locale shape in step with Kimai.
            new LocaleService([
                'da' => LocaleService::DEFAULT_SETTINGS,
                'de' => LocaleService::DEFAULT_SETTINGS,
                'en' => LocaleService::DEFAULT_SETTINGS,
            ]),
            $urlGenerator,
        );
    }

    private function systemConfiguration(string $defaultLanguage): SystemConfiguration
    {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        // Settings are a flat, dot-keyed array.
        return new SystemConfiguration($loader, ['defaults.user.language' => $defaultLanguage]);
    }

    /**
     * @param string|null $language the LANGUAGE preference, or null for a user with none
     */
    private function user(?string $language): User
    {
        $user = new User();
        if (null !== $language) {
            $preference = new UserPreference(UserPreference::LANGUAGE, $language);
            $user->addPreference($preference);
        }

        return $user;
    }

    /**
     * @param array<string, string> $routeParams
     */
    private function request(
        string $path,
        ?string $route = null,
        array $routeParams = [],
        ?string $queryString = null,
        string $method = 'GET',
    ): Request {
        $request = Request::create($path . (null !== $queryString ? '?' . $queryString : ''), $method);
        if (null !== $route) {
            $request->attributes->set('_route', $route);
            $request->attributes->set('_route_params', $routeParams);
        }

        return $request;
    }

    private static function assertRedirectedTo(string $expected, RequestEvent $event): void
    {
        $response = $event->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($expected, $response->getTargetUrl());
    }
}
