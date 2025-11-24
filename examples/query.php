<?php

declare(strict_types=1);

/**
 * Query Example
 *
 * This example demonstrates filtering events using queries:
 * - Creating queries with event types and tags
 * - Reading filtered events
 * - Multiple query items (OR logic)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use UmaDB\Client;
use UmaDB\Event;
use UmaDB\Query;
use UmaDB\QueryItem;

// Connect to UmaDB
$client = new Client('http://localhost:50051');

// First, let's append some events to work with
echo "Setting up test data...\n";

$testEvents = [
    // Order events
    new Event('OrderCreated', json_encode(['orderId' => 'O1', 'amount' => 100]), ['order', 'order:O1'], generateUuid()),
    new Event('OrderPaid', json_encode(['orderId' => 'O1', 'method' => 'card']), ['order', 'order:O1', 'payment'], generateUuid()),
    new Event('OrderShipped', json_encode(['orderId' => 'O1', 'carrier' => 'UPS']), ['order', 'order:O1', 'shipping'], generateUuid()),

    // User events
    new Event('UserCreated', json_encode(['userId' => 'U1', 'name' => 'Alice']), ['user', 'user:U1'], generateUuid()),
    new Event('UserUpdated', json_encode(['userId' => 'U1', 'field' => 'email']), ['user', 'user:U1'], generateUuid()),

    // Product events
    new Event('ProductCreated', json_encode(['productId' => 'P1', 'name' => 'Widget']), ['product', 'product:P1'], generateUuid()),
];

$client->append($testEvents);
echo "✓ Test data created\n\n";

// Example 1: Query by event type
echo "Example 1: Query by event type\n";
echo "================================\n";

$queryItem = new QueryItem(
    types: ['OrderCreated', 'OrderPaid'],  // Match these event types
    tags: null                             // Any tags
);
$query = new Query([$queryItem]);

$events = $client->read(query: $query);
echo "Found " . count($events) . " order events:\n";
foreach ($events as $seqEvent) {
    echo "  [{$seqEvent->position}] {$seqEvent->event->event_type}\n";
}
echo "\n";

// Example 2: Query by tags
echo "Example 2: Query by tags\n";
echo "========================\n";

$queryItem = new QueryItem(
    types: null,          // Any event type
    tags: ['order:O1']    // Must have this tag
);
$query = new Query([$queryItem]);

$events = $client->read(query: $query);
echo "Found " . count($events) . " events for order O1:\n";
foreach ($events as $seqEvent) {
    echo "  [{$seqEvent->position}] {$seqEvent->event->event_type} - tags: " . implode(', ', $seqEvent->event->tags) . "\n";
}
echo "\n";

// Example 3: Query by both event type AND tags
echo "Example 3: Query by event type AND tags\n";
echo "========================================\n";

$queryItem = new QueryItem(
    types: ['OrderPaid', 'OrderShipped'],  // These event types
    tags: ['order:O1']                     // AND this tag
);
$query = new Query([$queryItem]);

$events = $client->read(query: $query);
echo "Found " . count($events) . " payment/shipping events for order O1:\n";
foreach ($events as $seqEvent) {
    echo "  [{$seqEvent->position}] {$seqEvent->event->event_type}\n";
}
echo "\n";

// Example 4: Multiple query items (OR logic)
echo "Example 4: Multiple query items (OR logic)\n";
echo "==========================================\n";

$query = new Query([
    // Match user events
    new QueryItem(types: null, tags: ['user']),
    // OR match product events
    new QueryItem(types: null, tags: ['product']),
]);

$events = $client->read(query: $query);
echo "Found " . count($events) . " user OR product events:\n";
foreach ($events as $seqEvent) {
    echo "  [{$seqEvent->position}] {$seqEvent->event->event_type} - tags: " . implode(', ', $seqEvent->event->tags) . "\n";
}
echo "\n";

// Example 5: Complex query
echo "Example 5: Complex query\n";
echo "========================\n";

$query = new Query([
    // All order creation events
    new QueryItem(types: ['OrderCreated'], tags: null),
    // OR any payment-related events
    new QueryItem(types: null, tags: ['payment']),
]);

$events = $client->read(query: $query);
echo "Found " . count($events) . " order creation OR payment events:\n";
foreach ($events as $seqEvent) {
    echo "  [{$seqEvent->position}] {$seqEvent->event->event_type}\n";
}

echo "\n✓ Done!\n";

function generateUuid(): string
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
