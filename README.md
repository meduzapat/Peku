# Peku Framework

**Lightweight, security-oriented PHP framework built for performance.**

Peku embraces OOP principles (polymorphism, abstraction, inheritance, encapsulation) while leveraging PHP's built-in functionality for maximum speed with minimal overhead.

## Philosophy

- **Lightweight** - Zero bloat, only what you need
- **Security-first** - Built with security as a core principle
- **Performance** - Native PHP functions over custom implementations
- **Clean OOP** - Proper abstraction without over-engineering
- **Developer-friendly** - Intuitive API, minimal configuration

## Requirements

- PHP 8.0+
- Composer

PHP depencencies
```bash
sudo apt install -y php8.3-cli php8.3-common php8.3-curl php8.3-mbstring php8.3-xml php8.3-xdebug
```

## Installation

```bash
composer require peku/framework
```

## Quick Start

TBD

## Project Structure

## Project Structure
```
peku/framework/
├── src/
│   ├── Controllers/
│   │   └── Controller.php              (abstract base controller)
│   └── Helpers/
│       ├── Loggers/
│       │   ├── Loggeable.php           (interface)
│       │   ├── LogLevel.php            (enum - debug, info, warning, error, critical)
│       │   ├── Logger.php              (abstract base with formatting utilities)
│       │   ├── Noop.php                (no-op implementation)
│       │   ├── File.php                (file logger with timestamps)
│       │   └── Syslog.php              (system syslog integration)
│       ├── Configs/
│       │   ├── Configurable.php        (interface)
│       │   ├── Config.php              (abstract base with section/key access)
│       │   ├── ConfigException.php     (configuration exceptions)
│       │   ├── Noop.php                (no-op implementation)
│       │   └── Php.php                 (PHP array file loader)
│       └── Files/
│           └── FileException.php       (file operation exceptions)
├── tests/
│   ├── Unit/                           (mirrors src/ structure)
│   │   └── Helpers/
│   │       ├── Loggers/
│   │       │   ├── LoggerTest.php
│   │       │   ├── NoopTest.php
│   │       │   ├── FileTest.php
│   │       │   └── SyslogTest.php
│   │       ├── Configs/
│   │       │   ├── ConfigTest.php
│   │       │   ├── NoopTest.php
│   │       │   └── PhpTest.php
│   │       └── Files/
│   │           └── FileExceptionTest.php
│   ├── Integration/                    (cross-component tests - planned)
│   ├── Fixtures/
│   │   └── TestCase.php                (base test class with helpers)
│   ├── bootstrap.php                   (PHPUnit bootstrap)
│   └── README.md                       (test organization guide)
├── composer.json
├── LICENSE
└── README.md
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Lint code
composer lint
```

## Contributing

Peku is currently in active development. Contributions welcome once core components are stable.

## License

MIT License - see [LICENSE](LICENSE) for details

## Author

**Patricio Rossi** - [meduzapat@netscape.net](mailto:meduzapat@netscape.net)

---

**Status:** Active Development | **Version:** 0.1.0-dev