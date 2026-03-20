# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Unserializer` optional constructor `$options`: `repairLengths` (boolean coercion via `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)` — only strict `true` enables repair), `depth`, `maxDepth` for nested string scanning.
- `Unserializer::$lengthIssues` — each issue: `declared`, `actual`, `lengthDigitStart`, `lengthDigitEnd`, `path`.
- `Unserializer::parseOne()` — parse a single value from the start of a blob (bytes consumed).
- `Unserializer::repairSerializedString()` — rewrite wrong `s:` length digits (multi-pass, innermost-first).
- `Serial::repairSerializedString()` — thin wrapper.
- Detection of mis-declared string lengths: nested serialized payloads (parse one value) and, for non-nested strings, conservative `";` scan when the payload contains no `"`.
- Deep scan of string values that look like serialized data to collect nested length issues.
- Tests: repair coercion, nested wrong length, wire repair; docblock `@dataProvider` with PHPUnit 9.

### Fixed
- `unserializeString()` called `getLength()` with extra arguments; now uses instance `getLength()` only (PHP 8+ compatible).

### Changed
- **Minimum PHP version is now 7.3** (required for `FILTER_NULL_ON_FAILURE` with boolean validation). Consumers on PHP 5.x–7.2 cannot use this release; suggest tagging **0.2.0** if you treat that as breaking.
- `Unserializer::unserializeDouble()` return uses `(float)` cast.
- Dev: PHPUnit `^9.6`, `php-coveralls/php-coveralls`, migrated `phpunit.xml` to 9.3 schema, `.travis.yml` matrix PHP 7.3–8.3, `TestCase` shim without deprecated `parent::` callable pattern.
