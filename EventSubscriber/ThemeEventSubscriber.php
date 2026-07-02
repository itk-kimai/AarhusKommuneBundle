<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\EventSubscriber;

use App\Event\ThemeEvent;
use App\Plugin\PluginManager;
use KimaiPlugin\AarhusKommuneBundle\AarhusKommuneBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ThemeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => ['renderStylesheet', 100],
        ];
    }

    public function renderStylesheet(ThemeEvent $event): void
    {
        // Use the installed plugin version as the asset cache-buster. Kimai
        // reads it from composer.json (set at release build time); a plugin run
        // from source reports "unknown".
        $version = $this->plugins->getPlugin((new AarhusKommuneBundle())->getName())?->getMetadata()->getVersion() ?? 'unknown';

        $url = '/bundles/aarhuskommune/styles.css?' . http_build_query(['v' => $version]);
        $css = '<link rel="stylesheet" href="' . htmlspecialchars($url) . '"/>';

        $event->addContent($css);
    }
}
