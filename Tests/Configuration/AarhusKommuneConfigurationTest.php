<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Tests\Configuration;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Entity\Project;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\AarhusKommuneBundle\AarhusKommuneBundle;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every reader goes through Kimai's SystemConfiguration, which is handed the
 * same flat, dotted keys the container builds from local.yaml at compile time.
 */
#[CoversClass(AarhusKommuneConfiguration::class)]
final class AarhusKommuneConfigurationTest extends TestCase
{
    public function testTheConfigurationNameIsThePluginAlias(): void
    {
        // The keys below are only the real ones as long as this holds.
        self::assertSame(AarhusKommuneBundle::PLUGIN_NAME, AarhusKommuneConfiguration::CONFIGURATION_NAME);
        self::assertSame('aarhus_kommune', AarhusKommuneConfiguration::CONFIGURATION_NAME);
    }

    // --- was_url ------------------------------------------------------------

    public function testTheConfiguredWasUrlIsReturned(): void
    {
        $configuration = $this->configuration(['aarhus_kommune.was_url' => 'https://was.digst.dk/x']);

        self::assertSame('https://was.digst.dk/x', $configuration->getWasUrl());
    }

    public function testAnUnconfiguredWasUrlIsNull(): void
    {
        self::assertNull($this->configuration([])->getWasUrl());
    }

    public function testABlankWasUrlIsNull(): void
    {
        self::assertNull($this->configuration(['aarhus_kommune.was_url' => ''])->getWasUrl());
    }

    public function testANonStringWasUrlIsNull(): void
    {
        // The node is declared scalar, so a bare number gets past validation.
        self::assertNull($this->configuration(['aarhus_kommune.was_url' => 42])->getWasUrl());
    }

    // --- main_menu and user_defaults ----------------------------------------

    public function testTheMainMenuIsUndottedBackIntoAnArray(): void
    {
        $configuration = $this->configuration([
            'aarhus_kommune.main_menu.remove.0.route' => 'dashboard',
            'aarhus_kommune.main_menu.remove.1.route' => 'calendar',
        ]);

        self::assertSame(
            ['remove' => [['route' => 'dashboard'], ['route' => 'calendar']]],
            $configuration->getMainMenu()
        );
    }

    public function testAnUnconfiguredMainMenuIsAnEmptyArray(): void
    {
        self::assertSame([], $this->configuration([])->getMainMenu());
    }

    public function testTheUserDefaultsAreUndottedBackIntoAnArray(): void
    {
        $configuration = $this->configuration(['aarhus_kommune.user_defaults.login_initial_view' => 'quick_entry']);

        self::assertSame(['login_initial_view' => 'quick_entry'], $configuration->getUserDefaults());
    }

    public function testUnconfiguredUserDefaultsAreAnEmptyArray(): void
    {
        self::assertSame([], $this->configuration([])->getUserDefaults());
    }

    public function testOnlyTheRequestedSubtreeIsReturned(): void
    {
        // findArray() matches on a key prefix, so a sibling setting must not
        // leak into the menu configuration.
        $configuration = $this->configuration([
            'aarhus_kommune.main_menu.remove.0.route' => 'dashboard',
            'aarhus_kommune.user_defaults.login_initial_view' => 'quick_entry',
            'kimai.defaults.user.language' => 'da',
        ]);

        self::assertSame(['remove' => [['route' => 'dashboard']]], $configuration->getMainMenu());
    }

    // --- primary project and activity ---------------------------------------

    public function testThePrimaryProjectIsLoadedById(): void
    {
        $project = $this->project('Aarhus Kommune');

        $configuration = $this->configuration(
            ['aarhus_kommune.primary_project' => 87],
            projectRepository: $this->projectRepository(87, $project)
        );

        self::assertSame($project, $configuration->getPrimaryProject());
    }

    public function testAnUnconfiguredPrimaryProjectIsNotLookedUp(): void
    {
        // The repository would answer with a project; a null result proves the
        // lookup never happened.
        $configuration = $this->configuration([], projectRepository: $this->projectRepository(87, $this->project('Any')));

        self::assertNull($configuration->getPrimaryProject());
    }

    public function testAPrimaryProjectIdThatNoLongerExistsIsNull(): void
    {
        $configuration = $this->configuration(
            ['aarhus_kommune.primary_project' => 87],
            projectRepository: $this->projectRepository(87, null)
        );

        self::assertNull($configuration->getPrimaryProject());
    }

    public function testThePrimaryActivityIsLoadedById(): void
    {
        $project = $this->project('Aarhus Kommune');
        $activity = $this->activity('Tid', $project);

        $configuration = $this->configuration(
            ['aarhus_kommune.primary_activity' => 42],
            activityRepository: $this->activityRepository(42, $activity)
        );

        self::assertSame($activity, $configuration->getPrimaryActivity($project));
    }

    public function testAnUnconfiguredPrimaryActivityIsNotLookedUp(): void
    {
        // The repository would answer with an activity; a null result proves the
        // lookup never happened.
        $project = $this->project('Aarhus Kommune');

        $configuration = $this->configuration(
            [],
            activityRepository: $this->activityRepository(42, $this->activity('Tid', $project))
        );

        self::assertNull($configuration->getPrimaryActivity($project));
    }

    public function testAnActivityFromAnotherProjectIsRefused(): void
    {
        // Saving a timesheet with that pair would fail validation much later,
        // so the mismatch is reported here instead.
        $activity = $this->activity('Tid', $this->project('Another project'));

        $configuration = $this->configuration(
            ['aarhus_kommune.primary_activity' => 42],
            activityRepository: $this->activityRepository(42, $activity)
        );

        try {
            $configuration->getPrimaryActivity($this->project('Aarhus Kommune'));
            self::fail('Expected a RuntimeException naming both project and activity');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Tid', $exception->getMessage());
            self::assertStringContainsString('does not belong to project', $exception->getMessage());
            self::assertStringContainsString('Aarhus Kommune', $exception->getMessage());
        }
    }

    public function testAGlobalActivityIsRefused(): void
    {
        // A global activity has no project of its own, which is not the same as
        // belonging to this one.
        $configuration = $this->configuration(
            ['aarhus_kommune.primary_activity' => 42],
            activityRepository: $this->activityRepository(42, $this->activity('Global', null))
        );

        $this->expectException(RuntimeException::class);

        $configuration->getPrimaryActivity($this->project('Aarhus Kommune'));
    }

    // --- helpers ------------------------------------------------------------

    /**
     * @param array<string, string|int|null> $settings flat, dotted bundle settings
     */
    private function configuration(
        array $settings,
        ?ProjectRepository $projectRepository = null,
        ?ActivityRepository $activityRepository = null,
    ): AarhusKommuneConfiguration {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        // SystemConfiguration is final, so it is built for real.
        return new AarhusKommuneConfiguration(
            new SystemConfiguration($loader, $settings),
            $projectRepository ?? self::createStub(ProjectRepository::class),
            $activityRepository ?? self::createStub(ActivityRepository::class),
        );
    }

    private function projectRepository(int $id, ?Project $project): ProjectRepository
    {
        $repository = self::createStub(ProjectRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (mixed $needle): ?Project => $needle === $id ? $project : null
        );

        return $repository;
    }

    private function activityRepository(int $id, ?Activity $activity): ActivityRepository
    {
        $repository = self::createStub(ActivityRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (mixed $needle): ?Activity => $needle === $id ? $activity : null
        );

        return $repository;
    }

    private function project(string $name): Project
    {
        $project = new Project();
        $project->setName($name);

        return $project;
    }

    private function activity(string $name, ?Project $project): Activity
    {
        $activity = new Activity();
        $activity->setName($name);
        $activity->setProject($project);

        return $activity;
    }
}
