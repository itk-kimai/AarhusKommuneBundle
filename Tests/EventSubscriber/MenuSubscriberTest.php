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
use App\Configuration\SystemConfiguration;
use App\Event\ConfigureMainMenuEvent;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Utils\MenuItemModel;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\EventSubscriber\MenuSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The menu the subscriber is let loose on mirrors Kimai's own: two top level
 * items with a route, plus one section whose child carries the route.
 */
#[CoversClass(MenuSubscriber::class)]
final class MenuSubscriberTest extends TestCase
{
    public function testSubscribesLastToTheMainMenuEvent(): void
    {
        $events = MenuSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ConfigureMainMenuEvent::class, $events);
        self::assertSame(['onMenuConfigure', -9999], $events[ConfigureMainMenuEvent::class]);
    }

    public function testConfiguredRoutesAreRemoved(): void
    {
        $event = $this->menu();

        $this->subscriber(['dashboard'])->onMenuConfigure($event);

        self::assertSame(['timesheet', 'admin', 'admin_timesheet'], self::routes($event->getMenu()));
    }

    public function testNestedItemsAreRemoved(): void
    {
        // admin_timesheet sits below the admin section, so removal has to
        // descend into the tree.
        $event = $this->menu();

        $this->subscriber(['admin_timesheet'])->onMenuConfigure($event);

        self::assertSame(['dashboard', 'timesheet', 'admin'], self::routes($event->getMenu()));
    }

    public function testEveryConfiguredRouteIsRemoved(): void
    {
        $event = $this->menu();

        $this->subscriber(['dashboard', 'admin_timesheet'])->onMenuConfigure($event);

        self::assertSame(['timesheet', 'admin'], self::routes($event->getMenu()));
    }

    public function testARouteThatIsNotInTheMenuIsTolerated(): void
    {
        // Kimai renaming or dropping a route must not break the menu; the
        // removal simply finds nothing.
        $event = $this->menu();

        $this->subscriber(['a_route_kimai_no_longer_has'])->onMenuConfigure($event);

        self::assertSame(['dashboard', 'timesheet', 'admin', 'admin_timesheet'], self::routes($event->getMenu()));
    }

    public function testNoConfigurationLeavesTheMenuAlone(): void
    {
        $event = $this->menu();

        $this->subscriber([])->onMenuConfigure($event);

        self::assertSame(['dashboard', 'timesheet', 'admin', 'admin_timesheet'], self::routes($event->getMenu()));
    }

    public function testASpecWithoutARouteIsIgnored(): void
    {
        // The configuration tree makes `route` optional, so `- {}` is valid
        // YAML that names nothing.
        $event = $this->menu();

        $subscriber = $this->subscriberFromSettings(['aarhus_kommune.main_menu.remove.0.label' => 'dashboard']);
        $subscriber->onMenuConfigure($event);

        self::assertSame(['dashboard', 'timesheet', 'admin', 'admin_timesheet'], self::routes($event->getMenu()));
    }

    // --- helpers ------------------------------------------------------------

    private function menu(): ConfigureMainMenuEvent
    {
        $event = new ConfigureMainMenuEvent();
        $menu = $event->getMenu();

        $menu->addChild(new MenuItemModel('dashboard', 'menu.dashboard', 'dashboard'));
        $menu->addChild(new MenuItemModel('timesheet', 'menu.timesheet', 'timesheet'));

        $admin = new MenuItemModel('admin', 'menu.admin', 'admin');
        $admin->addChild(new MenuItemModel('admin_timesheet', 'menu.admin_timesheet', 'admin_timesheet'));
        $menu->addChild($admin);

        return $event;
    }

    /**
     * @param list<string> $remove route names to configure for removal
     */
    private function subscriber(array $remove): MenuSubscriber
    {
        $settings = [];
        foreach ($remove as $index => $route) {
            // Kimai flattens the bundle configuration to dotted keys at compile
            // time, list indexes included.
            $settings['aarhus_kommune.main_menu.remove.' . $index . '.route'] = $route;
        }

        return $this->subscriberFromSettings($settings);
    }

    /**
     * @param array<string, string> $settings flat, dotted bundle settings
     */
    private function subscriberFromSettings(array $settings): MenuSubscriber
    {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        // SystemConfiguration and AarhusKommuneConfiguration are both final, so
        // they are built for real; only the repositories are doubled.
        return new MenuSubscriber(new AarhusKommuneConfiguration(
            new SystemConfiguration($loader, $settings),
            self::createStub(ProjectRepository::class),
            self::createStub(ActivityRepository::class),
        ));
    }

    /**
     * The route of every remaining item, parents before children.
     *
     * @return list<string>
     */
    private static function routes(MenuItemModel $menu): array
    {
        $routes = [];
        foreach ($menu->getChildren() as $child) {
            $route = $child->getRoute();
            if (null !== $route) {
                $routes[] = $route;
            }

            $routes = [...$routes, ...self::routes($child)];
        }

        return $routes;
    }
}
