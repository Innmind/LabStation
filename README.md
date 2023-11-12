# Lab Station

[![Build Status](https://github.com/Innmind/LabStation/workflows/CI/badge.svg?branch=master)](https://github.com/Innmind/LabStation/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/Innmind/LabStation/branch/develop/graph/badge.svg)](https://codecov.io/gh/Innmind/LabStation)
[![Type Coverage](https://shepherd.dev/github/Innmind/LabStation/coverage.svg)](https://shepherd.dev/github/Innmind/LabStation)

Development tool to automate certain parts of the dev cycle.

Automatisations:
- Propose to update dependencies when starting working on the project
- Launch BlackBox proofs when `src`, `proofs`, `fixtures` or `properties` folders are modified
- Launch PHPUnit tests when `src`, `tests` or `fixtures` folders are modified
- Launch Psalm checks (if a `psalm.xml` exists) when `src` folder is modified
- Verify the code style (if a `.php_cs.dist` or `.php-cs-fixer.dist.php` file exists) when `src`, `tests`, `proofs`, `fixtures` or `properties` folders are modified
- Start docker compose when there is a `docker-compose.yml` at the project root

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
