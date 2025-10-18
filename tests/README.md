# Peku Test Directory Structure

## Directory Layout

```
tests/
├── Unit/                                    # Pure unit tests (mirrors src/)
│   ├── Controllers/
│   │   └── ControllerTest.php
│   └── Helpers/
│       ├── Config/
│       │   ├── ConfigTest.php
│       │   └── NoopTest.php
│       └── Loggers/
│           ├── NoopTest.php
│           └── LoggerHelpersTest.php
│
├── Integration/                             # Cross-component tests
│   └── ControllerConfigIntegrationTest.php
│
├── Fixtures/                                # Test data, samples, mocks
│   ├── configs/
│   │   ├── valid.ini
│   │   ├── invalid.ini
│   │   ├── empty.ini
│   │   └── malformed.ini
│   ├── Mocks/
│   │   ├── MockLogger.php
│   │   └── MockConfig.php
│   └── TestCase.php                         # Base test class (optional)
│
├── bootstrap.php                            # PHPUnit bootstrap file
└── phpunit.xml                              # PHPUnit configuration
```

## File Organization Rules

### Unit Tests (`tests/Unit/`)
- **Mirror `src/` structure exactly**
- One test file per source file: `Foo.php` → `FooTest.php`
- Namespace: `Peku\Tests\Unit\[Same\As\Source]`
- Test only the class in isolation (use mocks for dependencies)

### Integration Tests (`tests/Integration/`)
- Test multiple components working together
- Namespace: `Peku\Tests\Integration`
- Name pattern: `ComponentAComponentBTest.php`
- Can use real implementations (not mocks)

### Fixtures (`tests/Fixtures/`)
- **configs/**: Sample configuration files for testing
- **Mocks/**: Reusable mock objects and test doubles
- **TestCase.php**: Shared base class for common test setup
- No namespace restrictions (organized by type)

### Running tests
Run tests: `composer test`
If running from phar in the project: `php composer.phar test`
For coverage: `XDEBUG_MODE=coverage composer test:coverage`
If running from phar in the project: `XDEBUG_MODE=coverage php composer.phar test:coverage`