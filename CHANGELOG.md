# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-42](https://github.com/itk-kimai/AarhusKommuneBundle/pull/42)
  Require PHP `>=8.4`, drop the `config.platform` pin and remove the custom
  `composer.json` scripts (covered by the Taskfile). Raise
  `extra.kimai.require` from `21800` to `26100`: the plugin has needed the
  `config(...)` Twig function since 1.4.0 (Kimai 2.57), and its forked core
  templates track 2.61's markup, so 2.18 was never a real floor. Both
  declarations now state the only install this runs on, matching
  `AakSamlBundle`.

## [1.5.0] - 2026-07-02

* [PR-40](https://github.com/itk-kimai/AarhusKommuneBundle/pull/40)
  * Align dev tooling with the itk-dev docker templates: PHP 8.4
    `docker-compose.yml`, a Taskfile, and per-concern CI workflows.
  * Build releases via a tag-triggered GitHub release workflow; drop
    `bin/create-release`, `rsync` and the custom `Dockerfile`.
  * Omit the `version` field from `composer.json` (set at release build) so
    `composer validate --strict` passes.
  * Use the installed plugin version as the stylesheet cache-buster instead of
    a `%%VERSION%%` placeholder.
  * Upgrade PHPStan to `^2.0` and twig-cs-fixer to `^4.0`.
* [PR-39](https://github.com/itk-kimai/AarhusKommuneBundle/pull/39)
  7596: Force the UI language to follow the user's language preference by
  redirecting authenticated requests whose URL locale differs, and source
  new-user language, timezone and theme from Kimai's `defaults.user.*`
  configuration instead of bundle-local defaults.

## [1.4.0] - 2026-05-26

* [PR-37](https://github.com/itk-kimai/AarhusKommuneBundle/pull/37)
  7596: Replace deprecated `kimai_config.get(...)` / `kimai_config.loginFormActive`
  template accessors with `config(...)`; required for Kimai 2.57 compatibility.
* [PR-36](https://github.com/itk-kimai/AarhusKommuneBundle/pull/36)
  7596: Register plugin migrations in install command so
  `kimai:bundle:aarhus_kommune:install` runs them automatically.

## [1.3.0] - 2025-09-01

* [PR-32](https://github.com/itk-kimai/AarhusKommuneBundle/pull/32)
  2491: Updated Teamlead permissions documentation
* [PR-31](https://github.com/itk-kimai/AarhusKommuneBundle/pull/31)
  Code cleanup
* [PR-29](https://github.com/itk-kimai/AarhusKommuneBundle/pull/30)
  Remove prerelease flag in release.yml.
* [PR-28](https://github.com/itk-kimai/AarhusKommuneBundle/pull/28)
  Improved release script

## [1.2.0] - 2024-07-04

* [PR-27](https://github.com/itk-kimai/AarhusKommuneBundle/pull/27)
  1854: Fixed timesheet issues and cleaned up form display
* [PR-26](https://github.com/itk-kimai/AarhusKommuneBundle/pull/26)
  Teamlead roles documentation
* [PR-25](https://github.com/itk-kimai/AarhusKommuneBundle/pull/25)
  Login form design
* [PR-24](https://github.com/itk-kimai/AarhusKommuneBundle/pull/24)
  Only hide begin time on timesheet modal
* [PR-23](https://github.com/itk-kimai/AarhusKommuneBundle/pull/23)
  Prevent editing timesheet begin and end
* [PR-22](https://github.com/itk-kimai/AarhusKommuneBundle/pull/22)
  1855: Change title on login

## [1.1.0] - 2024-07-03

* [PR-21](https://github.com/itk-kimai/AarhusKommuneBundle/pull/21)
  1829: Hide start and stop timetracking on timesheet column actions
* [PR-20](https://github.com/itk-kimai/AarhusKommuneBundle/pull/20)
  1711: Added No tracking tracking mode
* [PR-19](https://github.com/itk-kimai/AarhusKommuneBundle/pull/19)
  1829: Hide create and edit button on timesheet page
* [PR-18](https://github.com/itk-kimai/AarhusKommuneBundle/pull/18)
  1819: Set login_initial_view on user creation
* [PR-17](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/17)
  Remove rows with repeated durations

## [1.0.0] - 2024-06-27

* [PR-14](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/14)
  Fix logout
* [PR-13](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/13)
  Remove 2fa link(dropdown)
* [PR-12](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/12)
  Set user default on creation
* [PR-11](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/11)
  Config documentation
* [PR-10](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/10)
  Added help URL setting
* [PR-9](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/9)
  Trimmed and improved login form
* [PR-8](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/8)
  Hide ui elements
* [PR-7](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/7)
  Configuration documentation
* [PR-6](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/6)
  Improved handling of default timesheet entries
* [PR-5](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/5)
  Web Accessibility Statement
* [PR-4](https://github.com/itk-kimai/kimai-plugin-AarhusKommuneBundle/pull/4)
  Trimmed main menu
* [PR-3](https://github.com/itk-dev/kimai-plugin-AarhusKommuneBundle/pull/3)
  Hide project, customer and activity inputs and tablecoumns.
* [PR-2](https://github.com/itk-dev/kimai-plugin-AarhusKommuneBundle/pull/2)
  Add app icons
* [PR-1](https://github.com/itk-dev/kimai-plugin-AarhusKommuneBundle/pull/1)
  Added Aarhus kommune plugin

[Unreleased]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.5.0...HEAD
[1.5.0]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.4.0...1.5.0
[1.4.0]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.3.0...1.4.0
[1.3.0]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/itk-kimai/AarhusKommuneBundle/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/itk-kimai/AarhusKommuneBundle/releases/tag/1.0.0
