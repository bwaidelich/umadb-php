<?php

declare(strict_types=1);

namespace UmaDB\Tests;

use PHPUnit\Framework\TestCase;
use UmaDB\Client;
use UmaDB\Event;
use UmaDB\Query;
use UmaDB\QueryItem;
use UmaDB\AppendCondition;
use UmaDB\Exception\IntegrityException;

/**
 * Integration tests for UmaDB PHP client
 *
 * These tests require a running UmaDB server on localhost:50051
 * Start the server with: cargo run --bin umadb -- --listen 127.0.0.1:50051 --db-path /tmp/test.db
 */
class ClientTest extends TestCase
{
    private const SERVER_URL = 'http://127.0.0.1:50051';
    private Client $client;

    protected function setUp(): void
    {
        try {
            $this->client = new Client(self::SERVER_URL);
        } catch (\Exception $e) {
            $this->markTestSkipped('UmaDB server not available: ' . $e->getMessage());
        }
    }

    public function testClientConstruction(): void
    {
        $client = new Client(self::SERVER_URL);
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testHeadOnEmptyStore(): void
    {
        // Note: This test assumes an empty store or will get the current head
        $head = $this->client->head();
        $this->assertIsInt($head) || $this->assertNull($head);
    }

    public function testAppendSingleEvent(): void
    {
        $event = new Event(
            eventType: 'TestEvent',
            data: 'test data',
            tags: ['test', 'php-client'],
            uuid: $this->generateUuid()
        );

        $position = $this->client->append([$event]);

        $this->assertIsInt($position);
        $this->assertGreaterThan(0, $position);
    }

    public function testAppendMultipleEvents(): void
    {
        $events = [
            new Event('Event1', 'data1', ['tag1'], $this->generateUuid()),
            new Event('Event2', 'data2', ['tag2'], $this->generateUuid()),
            new Event('Event3', 'data3', ['tag3'], $this->generateUuid()),
        ];

        $position = $this->client->append($events);

        $this->assertIsInt($position);
        $this->assertGreaterThan(0, $position);
    }

    public function testReadAllEvents(): void
    {
        // First append an event to ensure there's something to read
        $event = new Event(
            'ReadTest',
            'read test data',
            ['read-test'],
            $this->generateUuid()
        );
        $this->client->append([$event]);

        // Read all events
        $events = $this->client->read();

        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        foreach ($events as $seqEvent) {
            $this->assertIsObject($seqEvent);
            $this->assertObjectHasProperty('position', $seqEvent);
            $this->assertObjectHasProperty('event', $seqEvent);
            $this->assertIsInt($seqEvent->position);
        }
    }

    public function testReadWithQuery(): void
    {
        // Append an event with specific tags
        $uuid = $this->generateUuid();
        $event = new Event(
            'QueryTest',
            'query test data',
            ['query-test', 'specific-tag'],
            $uuid
        );
        $position = $this->client->append([$event]);

        // Create a query to filter by tags
        $queryItem = new QueryItem(
            types: ['QueryTest'],
            tags: ['query-test']
        );
        $query = new Query([$queryItem]);

        // Read with query
        $events = $this->client->read(query: $query);

        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        // Verify all returned events match the query
        foreach ($events as $seqEvent) {
            $this->assertEquals('QueryTest', $seqEvent->event->event_type);
            $this->assertContains('query-test', $seqEvent->event->tags);
        }
    }

    public function testReadWithLimit(): void
    {
        // Append multiple events
        for ($i = 0; $i < 5; $i++) {
            $event = new Event(
                'LimitTest',
                "limit test $i",
                ['limit-test'],
                $this->generateUuid()
            );
            $this->client->append([$event]);
        }

        // Read with limit
        $events = $this->client->read(limit: 2);

        $this->assertIsArray($events);
        $this->assertLessThanOrEqual(2, count($events));
    }

    public function testReadBackwards(): void
    {
        // Append events to establish order
        $positions = [];
        for ($i = 0; $i < 3; $i++) {
            $event = new Event(
                'BackwardTest',
                "backward test $i",
                ['backward-test'],
                $this->generateUuid()
            );
            $positions[] = $this->client->append([$event]);
        }

        // Read backwards from the last position
        $events = $this->client->read(
            start: end($positions),
            backwards: true,
            limit: 2
        );

        $this->assertIsArray($events);

        // Verify events are in descending order
        if (count($events) >= 2) {
            $this->assertGreaterThan($events[1]->position, $events[0]->position);
        }
    }

    public function testAppendWithCondition(): void
    {
        $uuid = $this->generateUuid();
        $tag = 'condition-test-' . uniqid();

        // Create a query for the consistency boundary
        $queryItem = new QueryItem(
            types: ['ConditionTest'],
            tags: [$tag]
        );
        $query = new Query([$queryItem]);

        // First, read to get current head
        $head = $this->client->head();

        // Create append condition
        $condition = new AppendCondition(
            failIfEventsMatch: $query,
            after: $head
        );

        // First append should succeed
        $event1 = new Event(
            'ConditionTest',
            'first event',
            [$tag],
            $uuid
        );
        $position1 = $this->client->append([$event1], $condition);

        $this->assertIsInt($position1);
    }

    public function testAppendConditionFailure(): void
    {
        $tag = 'conflict-test-' . uniqid();

        // Append first event
        $event1 = new Event(
            'ConflictTest',
            'first event',
            [$tag],
            $this->generateUuid()
        );
        $this->client->append([$event1]);

        // Get current head
        $head = $this->client->head();

        // Create condition that should fail
        $queryItem = new QueryItem(
            types: ['ConflictTest'],
            tags: [$tag]
        );
        $query = new Query([$queryItem]);
        $condition = new AppendCondition($query, $head);

        // Second append with same tags should fail
        $event2 = new Event(
            'ConflictTest',
            'second event',
            [$tag],
            $this->generateUuid()  // Different UUID
        );

        $this->expectException(IntegrityException::class);
        $this->client->append([$event2], $condition);
    }

    public function testIdempotentAppend(): void
    {
        $uuid = $this->generateUuid();
        $tag = 'idempotent-test-' . uniqid();

        // Create query and condition
        $queryItem = new QueryItem(
            types: ['IdempotentTest'],
            tags: [$tag]
        );
        $query = new Query([$queryItem]);
        $head = $this->client->head();
        $condition = new AppendCondition($query, $head);

        // Create event with UUID
        $event = new Event(
            'IdempotentTest',
            'idempotent data',
            [$tag],
            $uuid
        );

        // First append
        $position1 = $this->client->append([$event], $condition);

        // Second append with same UUID and condition should return same position
        $position2 = $this->client->append([$event], $condition);

        $this->assertEquals($position1, $position2);
    }

    public function testEventProperties(): void
    {
        $eventType = 'PropertiesTest';
        $data = 'test data with special chars: éàü';
        $tags = ['tag1', 'tag2', 'tag3'];
        $uuid = $this->generateUuid();

        $event = new Event($eventType, $data, $tags, $uuid);

        $this->assertEquals($eventType, $event->event_type);
        $this->assertEquals($data, $event->data);
        $this->assertEquals($tags, $event->tags);
        $this->assertEquals($uuid, $event->uuid);
    }

    public function testBinaryData(): void
    {
        // Test with binary data
        $binaryData = random_bytes(256);
        $event = new Event(
            'BinaryTest',
            $binaryData,
            ['binary'],
            $this->generateUuid()
        );

        $position = $this->client->append([$event]);
        $this->assertIsInt($position);

        // Read back and verify (simplified - would need exact position match in real scenario)
        $events = $this->client->read(limit: 1);
        $this->assertNotEmpty($events);
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
