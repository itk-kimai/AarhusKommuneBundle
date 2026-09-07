<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Configuration;

use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Entity\Project;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\AarhusKommuneBundle\AarhusKommuneBundle;
use KimaiPlugin\AarhusKommuneBundle\Exception\RuntimeException;

final class AarhusKommuneConfiguration
{
    public const CONFIGURATION_NAME = AarhusKommuneBundle::PLUGIN_NAME;

    public function __construct(
        private readonly SystemConfiguration $configuration,
        private readonly ProjectRepository $projectRepository,
        private readonly ActivityRepository $activityRepository,
    )
    {
    }

    public function getPrimaryProject(): ?Project
    {
        $id = $this->findConfiguration('primary_project');

        return null === $id ? null : $this->projectRepository->find($id);
    }

    public function getPrimaryActivity(Project $project): ?Activity
    {
        $id = $this->findConfiguration('primary_activity');
        $activity = null === $id ? null : $this->activityRepository->find($id);

        if (null !== $activity && $activity->getProject() !== $project) {
            throw new RuntimeException(\sprintf('Activity %s (%s) does not belong to project %s (%s)', $activity->getName(), $activity->getId(), $project->getName(), $project->getId()));
        }

        return $activity;
    }

    public function getMainMenu(): array
    {
        return $this->findArrayConfiguration('main_menu');
    }

    public function getWasUrl(): ?string
    {
        $url = $this->findConfiguration('was_url');

        // The node is a scalar URL; anything else (or a blank string) means
        // "not configured".
        return \is_string($url) && '' !== $url ? $url : null;
    }

    public function getUserDefaults(): array
    {
        return $this->findArrayConfiguration('user_defaults');
    }

    private function findConfiguration(string $name): string|int|bool|float|null
    {
        return $this->configuration->find($this->key($name));
    }

    private function findArrayConfiguration(string $name): array
    {
        return $this->configuration->findArray($this->key($name));
    }

    private function key(string $name): string
    {
        return self::CONFIGURATION_NAME . '.' . $name;
    }
}
