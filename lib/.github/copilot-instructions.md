# Copilot Instructions for `/lib` (PHP Backend)

## Architecture Pattern

### Responsibility Separation

**Entities** (`Db/*Entity*.php`, e.g., `Score.php`, `Tag.php`)
- Database table representations extending `OCP\AppFramework\Db\Entity`
- Property definitions with type declarations
- `jsonSerialize()` for API responses
- Transient properties (not stored in DB) documented clearly
- No business logic

**Mappers** (`Db/*Mapper.php`, e.g., `ScoreMapper.php`, `TagMapper.php`)
- Database operations (CRUD) extending `QBMapper`
- Query building with NextCloud's QueryBuilder
- Optimize for N+1 query avoidance (batch operations)
- Return Entity objects or arrays of Entities

**Services** (`Service/*.php`, e.g., `TagService.php`, `ScoreService.php`)
- Business logic and orchestration
- Call Mappers for data access
- Call Policies via AuthorizationService for authorization
- Throw domain exceptions (handled by Controllers)
- Transaction boundaries

**Policies** (`Policy/*.php`, e.g., `ScorePolicy.php`, `TagPolicy.php`)
- Implement `PolicyInterface`
- Authorization logic (who can do what)
- Return boolean from `allows(string $action, ?object $subject)`
- Keep logic simple and testable

**Controllers** (`Controller/*Controller.php`)
- Handle HTTP requests/responses
- Thin layer: validate input, call Services, return responses
- Use `ServiceExceptionBridgeTrait` for exception handling
- OpenAPI attributes for API documentation
- No business logic

## Service Method Naming Patterns

Services use a dual pattern for retrieving data, each serving a distinct purpose:

### `find{Entity}Entity(int $id): Entity`
Returns the raw database entity object. Used by:
- PATCH endpoint controllers to load entities for modification
- Other service methods internally for cross-service operations

**Example:**
```php
public function findScoreBookEntity(int $id): ScoreBook
```

### `get{Entity}ById(int $id): array`
Returns serialized entity data with computed fields. Used by:
- GET endpoint controllers to return complete data to clients
- Includes transient/computed fields (e.g., `scoreCount`)

**Example:**
```php
public function getScoreBookById(int $id): array  // Returns array with scoreCount
```

**Why both?**
- PATCH controllers need entity objects to apply field-by-field updates
- Other services need entity objects for cross-service operations
- GET endpoints need serialized data with computed fields for client responses
- Both methods must remain public to support these distinct use cases

**Best Practice:**
`get{Entity}ById()` should internally use `find{Entity}Entity()` for consistency.

### `update{Entity}(...): array`
All service update methods return complete, serialized data including all fields (changed and unchanged), ensuring PATCH endpoints return full objects, not just updated fields.

**Example:**
```php
public function updateScoreBook(ScoreBook $scoreBook, ?array $tagIds = null): array
```

## DRY Principles
- Shared logic in Services, not Controllers
- Common patterns in traits (e.g., `ServiceExceptionBridgeTrait`)
- Mapper utilities for common query patterns

## NextCloud Patterns
- Use dependency injection via constructor
- Type-hint interfaces from `OCP\` namespace
- Follow psalm annotations for type safety
- Use `declare(strict_types=1);` in all files

## IL10N (Internationalization)

### Dependency Injection
**ALWAYS** inject `IL10N` in services that have user-facing error messages:

```php
use OCP\IL10N;

final class YourService {
    public function __construct(
        // ... other dependencies
        private IL10N $l,
    ) {
    }
}
```

### Translation Method
Use the `$this->l->t()` method for all user-facing strings:

```php
// Simple string
throw new \Exception($this->l->t('Operation failed'));

// String with placeholders
throw new \Exception($this->l->t('User %s not found', [$username]));

// Multiple placeholders
$message = $this->l->t('Updated %s records in %s seconds', [$count, $duration]);
```

### Adding Translations
**WHENEVER** you add a new `$this->l->t()` call, add the translation to the `.po` file first:

1. **Edit the PO file** at `translationfiles/de/orchestrascoresmanager.po`:
   ```po
   msgid "Your English text"
   msgstr "Ihr deutscher Text"
   ```

2. **Generate the l10n files** by running:
   ```bash
   php translationtool.phar convert-po-files
   ```

This will automatically update `/l10n/de.json` and `/l10n/de.js`.

**Note**: The GitHub Actions workflow will verify that translation files are up to date on pull requests.

### When to Use IL10N
Use translations for:
- User-facing error messages that bubble up to the UI
- Validation error messages
- Status messages returned to the frontend
- Settings and configuration labels

Do NOT use translations for:
- Debug/log messages (use English)
- Developer-facing exceptions
- Internal error codes
- Database field names

### Best Practices
- Keep error messages concise and actionable
- Use placeholders for dynamic values
- Follow NextCloud's error message conventions
- Document complex translation strings with comments

## Testing

### Unit Tests Required
**ALWAYS** add or update unit tests for changes in `/lib`:
- Create tests in `/tests/unit/` mirroring the `/lib` structure
- Test files must end with `Test.php` (e.g., `TagTest.php`)
- Use PHPUnit's `TestCase` as base class
- Test business logic, edge cases, and error conditions

### Test Structure
```php
<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;

final class YourClassTest extends TestCase {
    public function testMethodName(): void {
        // Arrange
        // Act
        // Assert
    }
}
```

### PATCH Endpoint Tests
For PATCH endpoint tests, **ALWAYS** verify that the response includes unchanged fields:

```php
public function testPatchEntityUpdatesEntity(): void {
    $entity = new Entity();
    $entity->setId(1);
    $entity->setField1('Original');
    $entity->setField2('Unchanged');
    
    $updatedData = [
        'id' => 1,
        'field1' => 'Updated',
        'field2' => 'Unchanged',  // Verify this is present
    ];
    
    // ... mock setup ...
    
    $response = $this->controller->patchEntity(id: 1, field1: 'Updated');
    
    // Verify unchanged fields are present
    $this->assertArrayHasKey('field2', $response->getData());
    $this->assertEquals('Unchanged', $response->getData()['field2']);
}
```

### Running Tests
Execute tests with: `composer run test:unit`

### Verification
**ALWAYS** verify tests pass before completing changes to `/lib`.
