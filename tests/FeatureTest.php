<?php

use Anhoder\PrettyDumper\Formatter\Transformers\DiffTransformer;
use Anhoder\PrettyDumper\Formatter\Transformers\SqlTransformer;
use Anhoder\PrettyDumper\Storage\DumpHistoryStorage;

beforeEach(function () {
    // Clear history before each test
    DumpHistoryStorage::clearAll();
});

// ============================================================================
// DiffTransformer Tests
// ============================================================================

test('DiffTransformer detects identical values', function () {
    $transformer = new DiffTransformer();
    $diff = $transformer->diff(['a' => 1], ['a' => 1]);

    expect($diff['type'])->toBe(DiffTransformer::DIFF_UNCHANGED);
});

test('DiffTransformer detects added keys', function () {
    $transformer = new DiffTransformer();
    $diff = $transformer->diff(['a' => 1], ['a' => 1, 'b' => 2]);

    expect($diff['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['b']['type'])->toBe(DiffTransformer::DIFF_ADDED);
});

test('DiffTransformer detects removed keys', function () {
    $transformer = new DiffTransformer();
    $diff = $transformer->diff(['a' => 1, 'b' => 2], ['a' => 1]);

    expect($diff['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['b']['type'])->toBe(DiffTransformer::DIFF_REMOVED);
});

test('DiffTransformer detects modified values', function () {
    $transformer = new DiffTransformer();
    $diff = $transformer->diff(['a' => 1], ['a' => 2]);

    expect($diff['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['a']['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['a']['old'])->toBe(1);
    expect($diff['children']['a']['new'])->toBe(2);
});

test('DiffTransformer handles nested arrays', function () {
    $transformer = new DiffTransformer();
    $old = ['user' => ['name' => 'John', 'age' => 30]];
    $new = ['user' => ['name' => 'Jane', 'age' => 30]];

    $diff = $transformer->diff($old, $new);

    expect($diff['children']['user']['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['user']['children']['name']['type'])->toBe(DiffTransformer::DIFF_MODIFIED);
    expect($diff['children']['user']['children']['age']['type'])->toBe(DiffTransformer::DIFF_UNCHANGED);
});

test('DiffTransformer creates diff segment', function () {
    $transformer = new DiffTransformer();
    $diff = $transformer->diff(['a' => 1], ['a' => 2]);
    $segment = $transformer->createDiffSegment($diff);

    expect($segment)->toBeObject();
    expect($segment->type())->toBe('diff');
});

// ============================================================================
// SqlTransformer Tests
// ============================================================================

test('SqlTransformer detects SELECT queries', function () {
    $transformer = new SqlTransformer();

    expect($transformer->isSql('SELECT * FROM users'))->toBeTrue();
    expect($transformer->isSql('SELECT id, name FROM products WHERE price > 100'))->toBeTrue();
});

test('SqlTransformer detects INSERT queries', function () {
    $transformer = new SqlTransformer();

    expect($transformer->isSql('INSERT INTO users (name, email) VALUES (?, ?)'))->toBeTrue();
});

test('SqlTransformer detects UPDATE queries', function () {
    $transformer = new SqlTransformer();

    expect($transformer->isSql('UPDATE users SET name = ? WHERE id = ?'))->toBeTrue();
});

test('SqlTransformer detects DELETE queries', function () {
    $transformer = new SqlTransformer();

    expect($transformer->isSql('DELETE FROM users WHERE id = ?'))->toBeTrue();
});

test('SqlTransformer rejects non-SQL strings', function () {
    $transformer = new SqlTransformer();

    expect($transformer->isSql('Hello world'))->toBeFalse();
    expect($transformer->isSql('This is a test'))->toBeFalse();
    expect($transformer->isSql(''))->toBeFalse();
});

test('SqlTransformer formats SQL with line breaks', function () {
    $transformer = new SqlTransformer();
    $sql = 'SELECT id, name, email FROM users WHERE active = 1 ORDER BY name';

    $formatted = $transformer->format($sql);

    expect($formatted)->toContain('SELECT');
    expect($formatted)->toContain('FROM');
    expect($formatted)->toContain('WHERE');
    expect($formatted)->toContain('ORDER BY');
});

test('SqlTransformer replaces positional bindings', function () {
    $transformer = new SqlTransformer();
    $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
    $bindings = [123, 'John'];

    $formatted = $transformer->format($sql, $bindings);

    expect($formatted)->toContain('123');
    expect($formatted)->toContain("'John'");
    expect($formatted)->not->toContain('?');
});

test('SqlTransformer replaces named bindings', function () {
    $transformer = new SqlTransformer();
    $sql = 'SELECT * FROM users WHERE id = :id AND name = :name';
    $bindings = ['id' => 123, 'name' => 'John'];

    $formatted = $transformer->format($sql, $bindings);

    expect($formatted)->toContain('123');
    expect($formatted)->toContain("'John'");
});

test('SqlTransformer highlights SQL for CLI', function () {
    $transformer = new SqlTransformer();
    $sql = 'SELECT * FROM users WHERE id = 123';

    $highlighted = $transformer->highlightForCli($sql);

    expect($highlighted)->toContain("\033["); // Contains ANSI color codes
});

test('SqlTransformer highlights SQL for web', function () {
    $transformer = new SqlTransformer();
    $sql = 'SELECT * FROM users WHERE id = 123';

    $highlighted = $transformer->highlightForWeb($sql);

    expect($highlighted)->toContain('<span');
    expect($highlighted)->toContain('color:');
});

// ============================================================================
// DumpHistoryStorage Tests
// ============================================================================

test('DumpHistoryStorage stores and retrieves values', function () {
    $location = 'test.php:10';

    DumpHistoryStorage::store($location, ['a' => 1]);

    expect(DumpHistoryStorage::hasHistory($location))->toBeTrue();
    expect(DumpHistoryStorage::getLast($location))->toBe(['a' => 1]);
});

test('DumpHistoryStorage maintains history order', function () {
    $location = 'test.php:10';

    DumpHistoryStorage::store($location, ['version' => 1]);
    DumpHistoryStorage::store($location, ['version' => 2]);
    DumpHistoryStorage::store($location, ['version' => 3]);

    expect(DumpHistoryStorage::getLast($location))->toBe(['version' => 3]);

    $history = DumpHistoryStorage::getHistory($location);
    expect(count($history))->toBe(3);
});

test('DumpHistoryStorage limits history size', function () {
    $location = 'test.php:10';
    DumpHistoryStorage::setMaxEntriesPerLocation(3);

    for ($i = 1; $i <= 5; $i++) {
        DumpHistoryStorage::store($location, ['version' => $i]);
    }

    $history = DumpHistoryStorage::getHistory($location);
    expect(count($history))->toBe(3);
    expect(DumpHistoryStorage::getLast($location))->toBe(['version' => 5]);
});

test('DumpHistoryStorage clears specific location', function () {
    DumpHistoryStorage::store('test1.php:10', ['a' => 1]);
    DumpHistoryStorage::store('test2.php:20', ['b' => 2]);

    DumpHistoryStorage::clear('test1.php:10');

    expect(DumpHistoryStorage::hasHistory('test1.php:10'))->toBeFalse();
    expect(DumpHistoryStorage::hasHistory('test2.php:20'))->toBeTrue();
});

test('DumpHistoryStorage clears all history', function () {
    DumpHistoryStorage::store('test1.php:10', ['a' => 1]);
    DumpHistoryStorage::store('test2.php:20', ['b' => 2]);

    DumpHistoryStorage::clearAll();

    expect(DumpHistoryStorage::hasHistory('test1.php:10'))->toBeFalse();
    expect(DumpHistoryStorage::hasHistory('test2.php:20'))->toBeFalse();
});

test('DumpHistoryStorage generates location from trace', function () {
    $trace = [
        ['file' => '/vendor/pretty-dumper/src/PrettyDumper/Formatter/PrettyFormatter.php', 'line' => 10],
        ['file' => '/app/test.php', 'line' => 42],
    ];

    $location = DumpHistoryStorage::generateLocation($trace);

    expect($location)->toBe('/app/test.php:42');
});

// ============================================================================
// Helper Functions Tests
// ============================================================================

test('pd_diff compares two values', function () {
    $output = pd_diff(['a' => 1], ['a' => 2], [], false);

    expect($output)->toBeString();
});

test('pd_diff auto-detects JSON', function () {
    $json1 = '{"name": "John", "age": 30}';
    $json2 = '{"name": "Jane", "age": 30}';

    $output = pd_diff($json1, $json2, [], false);

    expect($output)->toBeString();
});

test('pd_when only dumps when condition is true', function () {
    $output1 = pd_when(['test' => 1], true, [], false);
    $output2 = pd_when(['test' => 2], false, [], false);

    expect($output1)->toBeString();
    expect($output2)->toBeNull();
});

test('pd_when accepts callable condition', function () {
    $output1 = pd_when(5, fn($v) => $v > 3, [], false);
    $output2 = pd_when(2, fn($v) => $v > 3, [], false);

    expect($output1)->toBeString();
    expect($output2)->toBeNull();
});

test('pd_assert dumps with assertion metadata', function () {
    $output = pd_assert(['count' => 10], fn($v) => $v['count'] > 5, 'Count should be > 5', [], false);

    expect($output)->toBeString();
});

test('pd_sql detects and formats SQL', function () {
    $sql = 'SELECT * FROM users WHERE id = ?';
    $output = pd_sql($sql, [123], null, [], false);

    expect($output)->toBeString();
});

test('pd_sql handles non-SQL strings gracefully', function () {
    $notSql = 'Hello world';
    $output = pd_sql($notSql, [], null, [], false);

    expect($output)->toBeString();
});
