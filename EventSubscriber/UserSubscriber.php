<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\EventSubscriber;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Event\UserCreatePreEvent;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * Preference key for a user's initial view after login.
     * (Kimai does not define one)
     */
    public const LOGIN_INITIAL_VIEW = 'login_initial_view';

    public function __construct(
        private readonly AarhusKommuneConfiguration $configuration
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatePreEvent::class => ['onUserPreCreate'],
        ];
    }

    public function onUserPreCreate(UserCreatePreEvent $event): void {
        $user = $event->getUser();
        // Set all user wizards as seen.
        foreach (User::WIZARDS as $wizard) {
            $user->setWizardAsSeen($wizard);
        }

        // language, timezone and theme are applied by Kimai from its
        // defaults.user.* configuration (UserService::createNewUser). Here we
        // only set the preferences Kimai has no default for; their values are
        // declared in the bundle configuration tree (DependencyInjection\Configuration).
        $defaults = $this->configuration->getUserDefaults();

        $user->setLocale((string) $defaults[UserPreference::LOCALE]);
        $user->setPreferenceValue(self::LOGIN_INITIAL_VIEW, (string) $defaults[self::LOGIN_INITIAL_VIEW]);
    }
}
