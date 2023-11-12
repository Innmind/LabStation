# Changelog

## [Unreleased]

### Added

- You can use `proofs`, `blackbox` or `bb` as triggers to only run proofs via BlackBox

### Changed

- Tests are no longer run when the `proofs` or `properties` directories are modified
- Proofs are no longer run when the `tests` directory is modified
- Psalm is no longer run when the `tests` directory is modified

### Fixed

- Agents sub processes would become zombies when the parent process would crash (no longer possible as everything is done in the same process)

## 3.7.0 - 2023-09-24

### Added

- Support for `innmind/immutable:~5.0`

### Removed

- Support for PHP `8.1`

## 3.6.0 - 2023-09-02

### Added

- Support BlackBox setting marks on failures

## 3.5.0 - 2023-07-15

### Changed

- All triggers redirect errors to the standard output (as `php://stderr` regularly is no longer writable)

## 3.4.0 - 2023-07-14

### Added

- Add agent to watch `proofs` directory to trigger tests

### Changed

- `fixtures` and `properties` watcher are now started

### Fixed

- Fix watching `fixtures` and `properties` directories
- Fix trying to watch the `tests` directory when it doesn't exist

## 3.3.0 - 2023-07-08

### Added

- Runs [`blackbox.php`](https://github.com/Innmind/BlackBox) proofs
