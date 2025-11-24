<?php

declare(strict_types=1);

/**
 * Dynamic Consistency Boundaries Example
 *
 * This example demonstrates:
 * - Using append conditions for optimistic concurrency control
 * - Enforcing business rules via queries
 * - Idempotent appends with UUIDs
 * - Handling integrity violations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use UmaDB\Client;
use UmaDB\Event;
use UmaDB\Query;
use UmaDB\QueryItem;
use UmaDB\AppendCondition;
use UmaDB\Exception\IntegrityException;

$client = new Client('http://localhost:50051');

echo "Dynamic Consistency Boundaries Demo\n";
echo "====================================\n\n";

// Example 1: Prevent duplicate email registration
echo "Example 1: Prevent duplicate email registration\n";
echo "------------------------------------------------\n";

$email = 'alice@example.com';
$userId = 'user-' . uniqid();

// Define consistency boundary: check for existing users with this email
$boundaryQuery = new Query([
    new QueryItem(
        types: ['UserRegistered'],
        tags: ["email:{$email}"]
    )
]);

// Read existing events in this boundary
$existingEvents = $client->read(query: $boundaryQuery);
$head = $client->head();

echo "Existing users with {$email}: " . count($existingEvents) . "\n";

// Create append condition
$condition = new AppendCondition(
    fail_if_events_match: $boundaryQuery,
    after: $head
);

// Try to register user
$event = new Event(
    'UserRegistered',
    json_encode(['userId' => $userId, 'email' => $email, 'name' => 'Alice']),
    ['user', "user:{$userId}", "email:{$email}"],
    generateUuid()
);

try {
    $position = $client->append([$event], $condition);
    echo "✓ User registered successfully at position {$position}\n";
} catch (IntegrityException $e) {
    echo "✗ Registration failed: Email already exists\n";
}
echo "\n";

// Example 2: Try to register same email again (should fail)
echo "Example 2: Try to register duplicate email\n";
echo "-------------------------------------------\n";

$newUserId = 'user-' . uniqid();
$newHead = $client->head();

$duplicateEvent = new Event(
    'UserRegistered',
    json_encode(['userId' => $newUserId, 'email' => $email, 'name' => 'Bob']),
    ['user', "user:{$newUserId}", "email:{$email}"],
    generateUuid()
);

$newCondition = new AppendCondition($boundaryQuery, $newHead);

try {
    $client->append([$duplicateEvent], $newCondition);
    echo "✗ Should have failed but didn't!\n";
} catch (IntegrityException $e) {
    echo "✓ Correctly rejected duplicate email: {$e->getMessage()}\n";
}
echo "\n";

// Example 3: Idempotent append
echo "Example 3: Idempotent append (same UUID)\n";
echo "-----------------------------------------\n";

$orderUuid = generateUuid();
$orderId = 'order-' . uniqid();

$orderBoundary = new Query([
    new QueryItem(types: ['OrderCreated'], tags: ["order:{$orderId}"])
]);

$head = $client->head();
$orderCondition = new AppendCondition($orderBoundary, $head);

$orderEvent = new Event(
    'OrderCreated',
    json_encode(['orderId' => $orderId, 'amount' => 100.00, 'currency' => 'USD']),
    ['order', "order:{$orderId}"],
    $orderUuid  // Same UUID
);

// First append
$position1 = $client->append([$orderEvent], $orderCondition);
echo "First append at position: {$position1}\n";

// Retry with same UUID - should return same position (idempotent)
$position2 = $client->append([$orderEvent], $orderCondition);
echo "Retry append at position: {$position2}\n";

if ($position1 === $position2) {
    echo "✓ Idempotent append confirmed (same position returned)\n";
} else {
    echo "✗ Expected same position\n";
}
echo "\n";

// Example 4: Multi-step workflow with consistency
echo "Example 4: Multi-step workflow\n";
echo "-------------------------------\n";

$workflowId = 'workflow-' . uniqid();

// Step 1: Start workflow
$event1 = new Event(
    'WorkflowStarted',
    json_encode(['workflowId' => $workflowId, 'step' => 1]),
    ['workflow', "workflow:{$workflowId}", 'step:1'],
    generateUuid()
);
$pos1 = $client->append([$event1]);
echo "✓ Workflow started at position {$pos1}\n";

// Step 2: Ensure step 1 is complete before step 2
$step1Boundary = new Query([
    new QueryItem(types: ['WorkflowStarted'], tags: ["workflow:{$workflowId}"])
]);

// Read to verify step 1 exists
$step1Events = $client->read(query: $step1Boundary);
if (count($step1Events) === 0) {
    die("✗ Step 1 not found!\n");
}

// Step 2: Prevent duplicate step 2
$step2Boundary = new Query([
    new QueryItem(types: ['WorkflowStep2Completed'], tags: ["workflow:{$workflowId}"])
]);

$head = $client->head();
$step2Condition = new AppendCondition($step2Boundary, $head);

$event2 = new Event(
    'WorkflowStep2Completed',
    json_encode(['workflowId' => $workflowId, 'step' => 2, 'result' => 'success']),
    ['workflow', "workflow:{$workflowId}", 'step:2'],
    generateUuid()
);

$pos2 = $client->append([$event2], $step2Condition);
echo "✓ Step 2 completed at position {$pos2}\n";

// Try to append step 2 again (should fail)
try {
    $duplicateStep2 = new Event(
        'WorkflowStep2Completed',
        json_encode(['workflowId' => $workflowId, 'step' => 2, 'result' => 'success'}),
        ['workflow', "workflow:{$workflowId}", 'step:2'],
        generateUuid()  // Different UUID
    );
    $client->append([$duplicateStep2], $step2Condition);
    echo "✗ Should have prevented duplicate step 2\n";
} catch (IntegrityException $e) {
    echo "✓ Correctly prevented duplicate step 2\n";
}
echo "\n";

// Example 5: Optimistic concurrency with position
echo "Example 5: Optimistic concurrency\n";
echo "----------------------------------\n";

$resourceId = 'resource-' . uniqid();

// Read current state
$resourceBoundary = new Query([
    new QueryItem(types: null, tags: ["resource:{$resourceId}"])
]);

$resourceEvents = $client->read(query: $resourceBoundary);
$knownPosition = $client->head();
echo "Known position: {$knownPosition}\n";

// Simulate: another process adds an event
$conflictingEvent = new Event(
    'ResourceUpdated',
    json_encode(['resourceId' => $resourceId, 'field' => 'status', 'value' => 'locked']),
    ['resource', "resource:{$resourceId}"],
    generateUuid()
);
$client->append([$conflictingEvent]);
echo "Another process updated the resource\n";

// Try to update based on old position (should fail)
$condition = new AppendCondition(
    fail_if_events_match: new Query([]),  // Empty query
    after: $knownPosition  // Position check - fail if events exist after this
);

$myUpdate = new Event(
    'ResourceUpdated',
    json_encode(['resourceId' => $resourceId, 'field' => 'value', 'value' => 42]),
    ['resource', "resource:{$resourceId}"],
    generateUuid()
);

try {
    $client->append([$myUpdate], $condition);
    echo "✗ Should have detected concurrent modification\n";
} catch (IntegrityException $e) {
    echo "✓ Correctly detected concurrent modification\n";
    echo "  Need to re-read events and retry\n";
}

echo "\n✓ All consistency boundary examples completed!\n";

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
