<?php

if ($argc < 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Usage: php audit_routes.php storage/app/route-list.json\n");
    exit(2);
}

$routes = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$failures = [];
$highRiskPatterns = [
    '#^api/(documents|daily-report-photos)/.+/(download|pdf)$#',
    '#^api/(bills|measurements)/.+/(certify|approve|reject|pdf)$#',
    '#^api/bills/.+/payments$#',
    '#^api/material-transactions$#',
    '#^api/projects/.+/wage-computations/generate$#',
];

foreach ($routes as $route) {
    $uri = $route['uri'] ?? '';
    if (! str_starts_with($uri, 'api/')) {
        continue;
    }

    $middleware = implode('|', $route['middleware'] ?? []);
    if (! str_contains($middleware, 'auth:sanctum')) {
        $failures[] = "$uri: missing auth:sanctum";
    }

    foreach ($highRiskPatterns as $pattern) {
        if (preg_match($pattern, $uri) && ! str_contains($middleware, 'permission:')) {
            $failures[] = "$uri: high-risk route missing permission middleware";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "Route audit failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Route audit passed. All API routes are authenticated and sampled high-risk routes have permission middleware.\n";
