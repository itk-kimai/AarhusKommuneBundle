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

        // Kimai applies language, timezone and theme from defaults.user.*, and
        // the formatting locale follows the language. Only login_initial_view,
        // which Kimai has no default for, is set here (value from the bundle
        // configuration tree).
        $defaults = $this->configuration->getUserDefaults();

        $user->setPreferenceValue(self::LOGIN_INITIAL_VIEW, (string) $defaults[self::LOGIN_INITIAL_VIEW]);
    }
}
