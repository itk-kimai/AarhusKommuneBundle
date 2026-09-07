<?php

/*
 * This file is part of the "AarhusKommuneBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AarhusKommuneBundle\Tests\Helper;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Timesheet\TrackingMode\TrackingModeInterface;
use App\Timesheet\TrackingModeService;
use KimaiPlugin\AarhusKommuneBundle\Configuration\AarhusKommuneConfiguration;
use KimaiPlugin\AarhusKommuneBundle\Helper\TimesheetHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Everything here is entity and configuration work, so it runs without a
 * database: the two repositories are doubled and the tracking mode is a fake
 * that only stamps a begin, the way Kimai's default mode does.
 */
#[CoversClass(TimesheetHelper::class)]
final class TimesheetHelperTest extends TestCase
{
    private const PROJECT_ID = 87;
    private const ACTIVITY_ID = 42;

    // --- setDefaultProject --------------------------------------------------

    public function testTheConfiguredProjectAndActivityAreSet(): void
    {
        $project = $this->project('Aarhus Kommune');
        $activity = $this->activity('Tid', $project);
        $helper = $this->helper($this->user(), $project, $activity);

        $timesheet = $helper->setDefaultProject(new Timesheet());

        self::assertSame($project, $timesheet->getProject());
        self::assertSame($activity, $timesheet->getActivity());
    }

    public function testWithoutAnAuthenticatedUserNothingIsSet(): void
    {
        $project = $this->project('Aarhus Kommune');
        $helper = $this->helper(null, $project, $this->activity('Tid', $project));

        $timesheet = $helper->setDefaultProject(new Timesheet());

        self::assertNull($timesheet->getProject());
        self::assertNull($timesheet->getActivity());
    }

    public function testWithoutAConfiguredProjectNothingIsSet(): void
    {
        // No primary project means no primary activity either, even when one is
        // configured.
        $helper = $this->helper($this->user(), null, $this->activity('Tid', $this->project('Aarhus Kommune')));

        $timesheet = $helper->setDefaultProject(new Timesheet());

        self::assertNull($timesheet->getProject());
        self::assertNull($timesheet->getActivity());
    }

    public function testAConfiguredProjectWithoutAnActivityStillSetsTheProject(): void
    {
        $project = $this->project('Aarhus Kommune');
        $helper = $this->helper($this->user(), $project, null);

        $timesheet = $helper->setDefaultProject(new Timesheet());

        self::assertSame($project, $timesheet->getProject());
        self::assertNull($timesheet->getActivity());
    }

    // --- ensureUserTimesheet ------------------------------------------------

    public function testAnExistingTimesheetIsReturnedUnchanged(): void
    {
        $project = $this->project('Aarhus Kommune');
        $existing = new Timesheet();
        $repository = $this->createMock(TimesheetRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $repository->expects(self::never())->method('save');

        $helper = $this->helper($this->user(), $project, $this->activity('Tid', $project), $repository);

        self::assertSame($existing, $helper->ensureUserTimesheet());
    }

    public function testANewTimesheetIsCreatedStoppedAndSaved(): void
    {
        $project = $this->project('Aarhus Kommune');
        $activity = $this->activity('Tid', $project);
        $repository = $this->createMock(TimesheetRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $user = $this->user();
        $timesheet = $this->helper($user, $project, $activity, $repository)->ensureUserTimesheet();

        self::assertInstanceOf(Timesheet::class, $timesheet);
        self::assertSame($user, $timesheet->getUser());
        self::assertSame($project, $timesheet->getProject());
        self::assertSame($activity, $timesheet->getActivity());
        // A running tracker would show up as an open registration in the UI.
        self::assertFalse($timesheet->isRunning());
        self::assertEquals($timesheet->getBegin(), $timesheet->getEnd());
    }

    public function testATimesheetIsCreatedForTheGivenUser(): void
    {
        $project = $this->project('Aarhus Kommune');
        $other = new User();
        $other->setUserIdentifier('someone_else');

        $timesheet = $this->helper($this->user(), $project, $this->activity('Tid', $project))
            ->ensureUserTimesheet($other);

        self::assertInstanceOf(Timesheet::class, $timesheet);
        self::assertSame($other, $timesheet->getUser());
    }

    public function testWithoutAUserNoTimesheetIsCreated(): void
    {
        $project = $this->project('Aarhus Kommune');
        $repository = $this->createMock(TimesheetRepository::class);
        $repository->expects(self::never())->method('save');

        $helper = $this->helper(null, $project, $this->activity('Tid', $project), $repository);

        self::assertNull($helper->ensureUserTimesheet());
    }

    public function testAnUnsavableTimesheetIsNotSaved(): void
    {
        // Without a primary project the new timesheet would be incomplete, so
        // it is dropped rather than written.
        $repository = $this->createMock(TimesheetRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $helper = $this->helper($this->user(), null, null, $repository);

        self::assertNull($helper->ensureUserTimesheet());
    }

    // --- helpers ------------------------------------------------------------

    private function helper(
        ?User $user,
        ?Project $project,
        ?Activity $activity,
        ?TimesheetRepository $timesheetRepository = null,
    ): TimesheetHelper {
        $timesheetRepository ??= self::createStub(TimesheetRepository::class);

        $tokenStorage = new TokenStorage();
        if (null !== $user) {
            $token = self::createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->setToken($token);
        }

        $configuration = $this->systemConfiguration($project, $activity);

        return new TimesheetHelper(
            new AarhusKommuneConfiguration(
                $configuration,
                $this->projectRepository($project),
                $this->activityRepository($activity),
            ),
            $tokenStorage,
            $timesheetRepository,
            // TimesheetService is final, so it is built for real.
            new TimesheetService(
                $configuration,
                $timesheetRepository,
                new TrackingModeService($configuration, [$this->trackingMode()]),
                new EventDispatcher(),
                self::createStub(AuthorizationCheckerInterface::class),
                self::createStub(ValidatorInterface::class),
            ),
        );
    }

    private function systemConfiguration(?Project $project, ?Activity $activity): SystemConfiguration
    {
        $loader = self::createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        $settings = [];
        if (null !== $project) {
            $settings['aarhus_kommune.primary_project'] = self::PROJECT_ID;
        }
        if (null !== $activity) {
            $settings['aarhus_kommune.primary_activity'] = self::ACTIVITY_ID;
        }

        return new SystemConfiguration($loader, $settings);
    }

    private function projectRepository(?Project $project): ProjectRepository
    {
        $repository = self::createStub(ProjectRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (mixed $id): ?Project => self::PROJECT_ID === $id ? $project : null
        );

        return $repository;
    }

    private function activityRepository(?Activity $activity): ActivityRepository
    {
        $repository = self::createStub(ActivityRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (mixed $id): ?Activity => self::ACTIVITY_ID === $id ? $activity : null
        );

        return $repository;
    }

    /**
     * Stands in for Kimai's DefaultMode: the only thing the helper depends on
     * is that the mode stamps a begin on the new timesheet.
     */
    private function trackingMode(): TrackingModeInterface
    {
        return new class() implements TrackingModeInterface {
            public function create(Timesheet $timesheet, ?Request $request = null): void
            {
                $timesheet->setBegin(new \DateTime('2026-01-05 08:00:00'));
            }

            public function canEditBegin(): bool
            {
                return true;
            }

            public function canEditEnd(): bool
            {
                return true;
            }

            public function canEditDuration(): bool
            {
                return true;
            }

            public function canUpdateTimesWithAPI(): bool
            {
                return true;
            }

            public function getEditTemplate(): string
            {
                return 'timesheet/edit-default.html.twig';
            }

            public function canSeeBeginAndEndTimes(): bool
            {
                return true;
            }

            public function getId(): string
            {
                return 'default';
            }
        };
    }

    private function user(): User
    {
        $user = new User();
        $user->setUserIdentifier('john_user');

        return $user;
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
