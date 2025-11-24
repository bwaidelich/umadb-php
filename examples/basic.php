<?php

declare(strict_types=1);

/**
 * Basic UmaDB PHP Client Example
 *
 * This example demonstrates basic operations:
 * - Connecting to UmaDB server
 * - Appending events
 * - Reading events
 * - Getting head position
 */

require_once __DIR__ . '/../vendor/autoload.php';

use UmaDB\Client;
use UmaDB\Event;

// Connect to UmaDB server
$url = 'http://localhost:50051';
echo "Connecting to UmaDB at {$url}...\n";

try {
    $client = new Client($url);
    echo "✓ Connected successfully\n\n";
} catch (\Exception $e) {
    die("✗ Connection failed: {$e->getMessage()}\n");
}

// Check current head position
echo "Getting current head position...\n";
$head = $client->head();
if ($head === null) {
    echo "Store is empty (head = null)\n\n";
} else {
    echo "Current head position: {$head}\n\n";
}

// Create and append a simple event
echo "Appending a simple event...\n";
$event = new Event(
    event_type: 'UserCreated',
    data: json_encode([
        'userId' => '12345',
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'createdAt' => date('c'),
    ]),
    tags: ['user', 'user:12345'],
    uuid: generateUuid()
);

$position = $client->append([$event]);
echo "✓ Event appended at position: {$position}\n\n";

// Append multiple events
echo "Appending multiple events...\n";
$events = [
    new Event(
        'UserUpdated',
        json_encode(['userId' => '12345', 'field' => 'email', 'value' => 'newemail@example.com']),
        ['user', 'user:12345'],
        generateUuid()
    ),
    new Event(
        'UserLoggedIn',
        json_encode(['userId' => '12345', 'timestamp' => date('c'), 'ip' => '192.168.1.1']),
        ['user', 'user:12345', 'login'],
        generateUuid()
    ),
];

$position = $client->append($events);
echo "✓ Multiple events appended, last position: {$position}\n\n";

// Read all events
echo "Reading all events...\n";
$allEvents = $client->read();
echo "✓ Read " . count($allEvents) . " events\n\n";

// Display recent events
echo "Recent events:\n";
$recentEvents = array_slice($allEvents, -5);
foreach ($recentEvents as $seqEvent) {
    $data = json_decode($seqEvent->event()->data, true);
    echo sprintf(
        "  [%d] %s - tags: %s\n",
        $seqEvent->position,
        $seqEvent->event()->event_type,
        implode(', ', $seqEvent->event()->tags)
    );
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
