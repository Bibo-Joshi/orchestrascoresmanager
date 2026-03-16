# NextCloud Mapper Integration Testing Guidelines

## Test Structure

### Integration Tests Location
Mapper tests are **integration tests** and must be in `/tests/integration/Db`.

- Tests require a real database and NextCloud environment
- Run in CI using PHPUnit workflow (`.github/workflows/phpunit-sqlite.yml`)
- Full NextCloud instance with database (SQLite, MySQL, MariaDB, PostgreSQL)
- App is enabled before tests run

### Base Test Class - MapperTestCase

**ALL** mapper integration tests MUST extend `MapperTestCase` which provides:

#### Automatic Setup
- `IDBConnection $db` - Database connection available as `$this->db`
- Foreign key constraint enablement (PRAGMA for SQLite - **before** transaction)
- Transaction start in `setUp()`
- Transaction rollback in `tearDown()`

#### Usage Pattern
```php
final class MyMapperTest extends MapperTestCase {
	private MyMapper $mapper;

	protected function setUp(): void {
		parent::setUp();  // CRITICAL: Must call parent

		$this->mapper = new MyMapper($this->db);
		// Initialize other dependent mappers as needed
	}

	// No tearDown() needed - handled by base class
	// No manual transaction or PRAGMA setup - handled by base class
}
```

### Transaction Isolation (Handled by MapperTestCase)
Each test runs in a database transaction that is rolled back automatically:

```php
protected function setUp(): void {
    parent::setUp();
    $this->db = \OC::$server->get(IDBConnection::class);
    // CRITICAL: Enable foreign keys BEFORE starting transaction
    // SQLite requires this - PRAGMA cannot be set within a transaction
    if ($this->db->getDatabaseProvider() === 'sqlite') {
        $this->db->executeStatement('PRAGMA foreign_keys = ON');
    }
    $this->db->beginTransaction();
}

protected function tearDown(): void {
    $this->db->rollBack(); // Automatic cleanup
    parent::tearDown();
}
```

**Benefits:**
- Tests are isolated - no cross-test pollution
- Fast cleanup - rollback is faster than DELETE queries
- Database is clean after test suite
- **No manual table cleanup needed** - rely on rollback

## Modern PHPUnit Features

### Use Attributes Over Annotations
- `#[DataProvider('methodName')]` instead of `@dataProvider`
- `#[TestWith([param1, param2])]` for simple parameterized tests
- More type-safe and IDE-friendly

### Data Providers
Data providers must be `public static` and return arrays:

```php
public static function provideTestCases(): array {
    return [
        'case description' => [param1, param2, expected],
        'another case' => [param1, param2, expected],
    ];
}

#[DataProvider('provideTestCases')]
public function testSomething($param1, $param2, $expected): void {
    // test implementation
}
```

### TestWith for Simple Cases
For single-parameter or simple multi-parameter tests:

```php
#[TestWith([FolderCollectionType::Concert])]
#[TestWith([FolderCollectionType::Practice])]
public function testCollectionTypes(FolderCollectionType $type): void {
    // test implementation
}
```

## Foreign Key Constraint Testing

**Why test constraints:** Mappers rely on database schema from Migrations. Testing CASCADE/RESTRICT validates that schema and mapper work together correctly.

**Enable foreign keys first:** SQLite requires `PRAGMA foreign_keys = ON` in setUp(). MySQL/PostgreSQL enable by default.

### CASCADE DELETE
Verify dependent records are automatically deleted when parent is deleted:

```php
public function testCascadeDeleteOnScoreDeletion(): void {
    $score = $this->createTestScore();
    $comment = $this->createTestComment($score->getId());

    $this->scoreMapper->delete($score); // Should cascade

    $found = $this->commentMapper->findByScoreId($score->getId());
    $this->assertCount(0, $found); // Comments should be gone
}
```

### RESTRICT Constraints
Verify deletion fails when dependent records exist:

```php
public function testRestrictDeleteOnTagWithLinks(): void {
    $tag = $this->createTestTag();
    $score = $this->createTestScore();
    $this->linkMapper->setTagsForScore($score->getId(), [$tag->getId()]);

    $this->expectException(Exception::class);
    $this->tagMapper->delete($tag); // Should fail - tag has links
}
```

### Invalid Foreign Keys
Verify inserts fail with non-existent foreign key references:

```php
public function testForeignKeyConstraintOnInsert(): void {
    $comment = new Comment();
    $comment->setScoreId(999999); // Non-existent score
    $comment->setText('Test');

    $this->expectException(Exception::class);
    $this->commentMapper->insert($comment);
}
```

## Transaction Rollback Testing

For mappers with explicit transaction handling, test rollback behavior:

```php
public function testTransactionRollbackOnFailure(): void {
    try {
        // Code that should fail and trigger rollback
        $this->mapper->someMethodWithTransaction($invalidData);
        $this->fail('Should have thrown exception');
    } catch (\Throwable $e) {
        // Verify rollback occurred
        $results = $this->mapper->findAll();
        $this->assertCount($expectedCount, $results);
    }
}
```

## Edge Cases to Test

### Empty Results
- `findAll()` with no records
- `findByX()` with no matches
- Empty array parameters

### Boundary Values
- Very long strings (near field limits)
- Unicode/emoji content
- Large numbers of records
- Null vs empty string

### Data Type Handling
- String IDs passed to methods expecting integers
- Array normalization (values, map vs list)

### Ordering
- Verify results are ordered as specified (e.g., `ORDER BY creation_date DESC`)

## What to Test

**Test mapper-specific methods only.** Do NOT test inherited QBMapper methods:

### QBMapper Methods (DO NOT TEST):
- `insert()`, `update()`, `delete()`, `insertOrUpdate()`
- `findEntities()`, `findEntity()`, `findOneQuery()`
- `getTableName()`, `mapRowToEntity()`, `yieldEntities()`

### Custom Mapper Methods (TEST THESE):
- Query methods: `findByScoreId()`, `findByName()`, `findTagIdsForScore()`
- Batch operations: `setTagsForScore()`, `addScoresToScoreBook()`
- Complex queries with JOINs, aggregations, date logic
- Transaction-wrapped operations
- Methods with business logic beyond basic CRUD

**Example:** TagMapper has `findByName()` with normalization logic - test that. Don't test basic `insert()`.

## Accessing Database Connection

Use NextCloud's server container:

```php
$this->db = \OC::$server->get(IDBConnection::class);
```

**Do NOT:**
- Mock `IDBConnection` - these are integration tests
- Use direct QueryBuilder access in tests - use mapper methods
- Create your own connection - use the server's

## Naming Conventions

- Test class: `{EntityName}MapperTest`
- Test file: Match class name with `.php` extension
- Test methods: `testMethodName` or `testScenarioDescription`
- Data providers: `provideTestCases` or `provide{DescriptiveName}`

## Test Organization

Group related tests together in the file:
1. Basic CRUD operations (insert, find, update, delete)
2. Query methods (findAll, findByX)
3. Foreign key and constraint tests
4. Edge cases and error conditions
5. Complex scenarios (ordering, transactions, etc.)

## Assertions

Prefer specific assertions:
- `assertSame()` over `assertEquals()` for strict comparison
- `assertCount()` for array/collection sizes
- `assertContains()` for membership checks
- `assertNull()`/`assertNotNull()` for nullable fields

## Common Patterns

### Creating Test Entities
Create helper methods for common test entity creation:

```php
private function createTestScore(): Score {
    $score = new Score();
    $score->setTitle('Test Score');
    return $this->scoreMapper->insert($score);
}
```

### Testing Mapper Dependencies
When a mapper depends on other mappers (e.g., for foreign keys), instantiate them in `setUp()`:

```php
protected function setUp(): void {
    // ... parent::setUp()
    $this->tagMapper = new TagMapper($this->db);
    $this->scoreMapper = new ScoreMapper($this->db);
    $this->mapper = new ScoreTagLinkMapper($this->db);
}
```

**Use mappers for test setup:** Create test data using mapper methods, NOT direct QueryBuilder:

```php
// GOOD - Uses mapper abstraction
private function createTestScore(): Score {
    $score = new Score();
    $score->setTitle('Test');
    return $this->scoreMapper->insert($score);
}

// BAD - Direct QB access breaks abstraction
private function createTestScore(): Score {
    $qb = $this->db->getQueryBuilder();
    $qb->insert('osm_scores')->values([...])->executeStatement();
    // ...
}
```

## Running Tests Locally

Tests require a NextCloud environment. They will fail when run standalone but succeed in CI:

```bash
# These will fail locally without NextCloud
composer test:unit tests/integration/Db/TagMapperTest.php

# Tests run automatically in CI on push/PR
# See .github/workflows/phpunit-sqlite.yml
```

## Testing Link Mappers

Link mappers (many-to-many relationships) need special attention:

### Array Normalization
Verify `array_map('intval', array_values($ids))` pattern works:

```php
public function testWithStringIds(): void {
    $found = $this->mapper->find(['1', '2', '3']); // strings
    $this->assertCount(3, $found);
}
```

### Empty Arrays
Always test empty array handling:

```php
public function testWithEmptyArray(): void {
    $found = $this->mapper->find([]);
    $this->assertCount(0, $found);
}
```

### Batch Operations
Test transaction-wrapped batch operations fully rollback on failure.
