<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Command;

use App\Command\AbstractBundleInstallerCommand;
use KimaiPlugin\AarhusKommuneBundle\AarhusKommuneBundle;

class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return AarhusKommuneBundle::PLUGIN_NAME;
    }

    protected function hasAssets(): bool
    {
        return true;
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return __DIR__ . '/../Migrations/aarhus_kommune.yaml';
    }
}
