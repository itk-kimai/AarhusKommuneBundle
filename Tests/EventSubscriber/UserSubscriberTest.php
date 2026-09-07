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
use App\Entity\User;
use App\Event\UserCreatePreEvent;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\EventSubscriber\UserSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Runs on every user Kimai creates, SAML provisioning on first login included,
 * so the user leaving this event is what the person actually gets to see.
 */
#[CoversClass(UserSubscriber::class)]
final class UserSubscriberTest extends TestCase
{
    public function testSubscribesToUserPreCreate(): void
    {
        $events = UserSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(UserCreatePreEvent::class, $events);
        self::assertSame(['onUserPreCreate'], $events[UserCreatePreEvent::class]);
    }

    public function testEveryWizardIsMarkedAsSeen(): void
    {
        // Nobody should be walked through Kimai's intro on a provisioned login.
        $user = $this->create(['aarhus_kommune.user_defaults.login_initial_view' => 'quick_entry']);

        self::assertNotEmpty(User::WIZARDS);
        foreach (User::WIZARDS as $wizard) {
            self::assertTrue($user->hasSeenWizard($wizard), $wizard);
        }
    }

    public function testTheConfiguredInitialViewIsApplied(): void
    {
        $user = $this->create(['aarhus_kommune.user_defaults.login_initial_view' => 'quick_entry']);

        self::assertSame('quick_entry', $user->getPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW));
    }

    public function testAnotherInitialViewIsApplied(): void
    {
        $user = $this->create(['aarhus_kommune.user_defaults.login_initial_view' => 'timesheet']);

        self::assertSame('timesheet', $user->getPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW));
    }

    public function testAMissingUserDefaultsConfigurationIsGuarded(): void
    {
        // `user_defaults` has no addDefaultsIfNotSet(), so leaving the node out
        // of local.yaml leaves nothing behind to read.
        $user = $this->create([]);

        self::assertNull($user->getPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW));
        self::assertTrue($user->hasSeenWizard('intro'));
    }

    public function testABlankInitialViewIsNotApplied(): void
    {
        // An empty preference is worse than none: Kimai would route the user to
        // "" after login.
        $user = $this->create(['aarhus_kommune.user_defaults.login_initial_view' => '']);

        self::assertNull($user->getPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW));
    }

    public function testAnExistingPreferenceIsOverwritten(): void
    {
        $user = new User();
        $user->setPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW, 'dashboard');

        $this->subscriber(['aarhus_kommune.user_defaults.login_initial_view' => 'quick_entry'])
            ->onUserPreCreate(new UserCreatePreEvent($user));

        self::assertSame('quick_entry', $user->getPreferenceValue(UserSubscriber::LOGIN_INITIAL_VIEW));
    }

    // --- helpers ------------------------------------------------------------

    /**
     * @param array<string, string> $settings flat, dotted bundle settings
     */
    private function create(array $settings): User
    {
        $user = new User();
        $this->subscriber($settings)->onUserPreCreate(new UserCreatePreEvent($user));

        return $user;
    }

    /**
     * @param array<string, string> $settings flat, dotted bundle settings
     */
    private function subscriber(array $settings): UserSubscriber
    {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        // SystemConfiguration and AarhusKommuneConfiguration are both final, so
        // they are built for real; only the repositories are doubled.
        return new UserSubscriber(new AarhusKommuneConfiguration(
            new SystemConfiguration($loader, $settings),
            self::createStub(ProjectRepository::class),
            self::createStub(ActivityRepository::class),
        ));
    }
}
