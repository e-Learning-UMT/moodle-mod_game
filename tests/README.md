# Moodle Game Module - PHPUnit Tests

This directory contains PHPUnit tests for the Moodle Game module, compatible with Moodle 5.1.

## Test Files

### custom_completion_test.php
Tests the custom completion functionality including:
- `completionpass` - Passing grade requirement
- `completionattemptsexhausted` - All attempts used requirement
- Custom rule definitions and descriptions
- Sort order for completion rules

### completion_lib_test.php
Tests the completion library functions including:
- `game_supports()` - Feature support declarations
- `game_get_completion_state()` - Completion state calculations
- Grade-based completion scenarios

### generator_testcase.php
Tests the game module generator for creating test instances.

## Running the Tests

### Run all game module tests:
```bash
vendor/bin/phpunit --testsuite mod_game_testsuite
```

### Run specific test file:
```bash
vendor/bin/phpunit mod/game/tests/custom_completion_test.php
vendor/bin/phpunit mod/game/tests/completion_lib_test.php
```

### Run from Moodle root:
```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --filter mod_game
```

## Test Coverage

The tests cover:
- ✓ Custom completion rules implementation
- ✓ Completion state validation
- ✓ Grade-based completion
- ✓ Attempts-based completion
- ✓ Feature support declarations
- ✓ Backward compatibility with older completion API

## Requirements

- Moodle 5.0+
- PHPUnit 9.5+
- PHP 8.1+

## Moodle 5.1 Compatibility

These tests are specifically designed for Moodle 5.1 and verify:
- Modern custom completion API implementation
- Proper namespace usage
- Activity custom completion interface compliance
- Form suffix handling for default completion settings

## Contributing

When adding new features to the game module, please:
1. Add corresponding PHPUnit tests
2. Ensure all existing tests pass
3. Run tests before committing changes
