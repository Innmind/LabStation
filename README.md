# Lab Station

| `develop` |
|-----------|
| [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Innmind/LabStation/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/LabStation/?branch=develop) |
| [![Code Coverage](https://scrutinizer-ci.com/g/Innmind/LabStation/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/LabStation/?branch=develop) |
| [![Build Status](https://scrutinizer-ci.com/g/Innmind/LabStation/badges/build.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/LabStation/build-status/develop) |

Development tool to automate certain parts of the dev cycle.

Automatisations:
- Generating all dependecy graphs (based on the package name found in `composer.json`)
- Propose to update dependencies when starting working on the project
- Launch PHPUnit tests when `src` or `tests` folders are modified
- Launch Psalm checks (if a `psalm.xml` exists) when `src` or `tests` folders are modified
- Start docker compose when there is a `docker-compose.yml` at the project root
- Ask for the kind of tag to create when branch is changed to `master`

![](example.gif)

## Installation

```sh
composer global require innmind/lab-station
```

## Usage

In the project you want to work on (at the same level of `composer.json`):

```sh
lab-station
```

That's it, no configuration needed.
