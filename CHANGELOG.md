# Changelog

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
