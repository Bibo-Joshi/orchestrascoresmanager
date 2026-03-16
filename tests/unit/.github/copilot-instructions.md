# Unit Test Guidelines (Backend Only)

## Purpose
Unit tests must validate small, isolated units of backend PHP code (classes/methods) without involving Nextcloud, databases, network, or frontend code. Fast, deterministic, and mock-based.

## Scope
- Backend PHP unit tests only. No frontend/unit tests in this file.
- Do NOT test functionality provided by external base classes or frameworks (for example: QBMapper, Entity, or other inherited behaviors defined outside this codebase). Only test the custom logic in classes inside this repository.

## Location
- Place backend tests under: tests/unit/ (e.g. tests/unit/Service, tests/unit/Db, tests/unit/Controller)

## Core Rules
- Do not use a real database or Nextcloud runtime.
- Inject dependencies and replace them with PHPUnit mocks or doubles.
- Keep tests small and focused. Prefer one assertion per behavior where reasonable.
- Use typed properties and return types where possible in tests and doubles.

## Base Test Class
Consider a UnitTestCase with common helpers for creating mocks:

```php
// ...existing code...
protected function setUp(): void {
    parent::setUp();
    $this->serviceMock = $this->createMock(SomeService::class);
    // ...existing code...
}
```

## Mocking & Isolation
- Mock external services, repositories, IDBConnection, HTTP clients, and Nextcloud services.
- Stub only what is necessary for the scenario.
- Verify interactions with ->expects(self::once())->method('...') for behavior tests.

## PHPUnit Features
- Prefer PHP 8 attributes: #[DataProvider('provide...')], #[TestWith(...)].
- Data providers must be public static and return arrays of cases.

## Naming & Style
- Test class: {ClassName}Test
- File name: Match the class name, e.g. MyServiceTest.php
- Test methods: descriptive names like testMethod_whenCondition_expectedResult
- Use assertSame(), assertCount(), assertNull()/assertNotNull() for clarity.

## What Not to Test
- Do NOT test inherited or framework-provided functionality (e.g., methods implemented in QBMapper, Entity, or other external base classes). Those belong to integration or framework tests.
- Do NOT start or assert on database schema, migrations, or Nextcloud lifecycle here.

## Examples

Mocking a repository:
```php
// ...existing code...
$repo = $this->createMock(MyRepo::class);
$repo->method('find')->willReturn($entity);
```

Data provider:
```php
public static function provideCases(): array {
    return [
        'empty' => ['', false],
        'valid' => ['ok', true],
    ];
}
```

Setup example with a mocked IDBConnection:
```php
protected function setUp(): void {
    parent::setUp();
    $this->dbMock = $this->createMock(\OCP\DB\IDBConnection::class);
    // configure expected calls on $this->dbMock as needed
}
```

## CI and Local Runs
- Local: ./vendor/bin/phpunit tests/unit or composer test:unit
- CI: run unit tests in a lightweight workflow without DB services.

## Quick Checklist
- Is the test isolated? (no DB, no Nextcloud, no network)
- Are dependencies injected and mocked?
- Is only repository-specific logic tested (not inherited framework code)?
- Is the test fast and deterministic?

Keep unit tests short and actionable: they should give quick, unambiguous feedback about small pieces of application logic.
