<?php
declare(strict_types=1);

/**
 * Cleanup approval requests. Viewers walk the exact same cleanup cycle
 * (date → preview → double confirmation) but the confirmed action lands here
 * as a pending request; an admin approves (which executes it with the
 * viewer's parameters) or rejects it. Stored as one JSON list in data/.
 */

define('REQUESTS_FILE', DATA_PATH . '/requests.json');

function requests_all(): array
{
    return json_read(REQUESTS_FILE, []);
}

function request_find(string $id): ?array
{
    foreach (requests_all() as $request) {
        if (($request['id'] ?? '') === $id) {
            return $request;
        }
    }
    return null;
}

/** @return string the new request id */
function request_add(array $request): string
{
    $request['id']         = bin2hex(random_bytes(8));
    $request['status']     = 'pending';
    $request['created_at'] = date('c');
    $all = requests_all();
    array_unshift($all, $request);
    // The list never needs to grow unbounded — keep the latest 200.
    json_write(REQUESTS_FILE, array_slice($all, 0, 200));
    return $request['id'];
}

function request_update(string $id, array $patch): bool
{
    $all = requests_all();
    foreach ($all as $i => $request) {
        if (($request['id'] ?? '') === $id) {
            $all[$i] = array_merge($request, $patch);
            return json_write(REQUESTS_FILE, $all);
        }
    }
    return false;
}

function requests_pending(): array
{
    return array_values(array_filter(requests_all(), fn($r) => ($r['status'] ?? '') === 'pending'));
}

function requests_pending_count(): int
{
    return count(requests_pending());
}

function requests_by_user(string $username, int $limit = 10): array
{
    return array_slice(array_values(array_filter(
        requests_all(),
        fn($r) => strcasecmp((string)($r['user'] ?? ''), $username) === 0
    )), 0, $limit);
}
