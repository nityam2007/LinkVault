<?php

// Storage directories
$storageDirs = [
    'app',
    'app/public',
    'app/archives',
    'framework',
    'framework/cache',
    'framework/cache/data',
    'framework/sessions',
    'framework/testing',
    'framework/views',
    'logs',
];

// Create storage directories
foreach ($storageDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Create .gitignore for storage
file_put_contents(__DIR__ . '/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/app/.gitignore', "*\n!public/\n!archives/\n!.gitignore\n");
file_put_contents(__DIR__ . '/app/public/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/framework/.gitignore', "compiled.php\nconfig.php\ndown\nevents.scanned.php\nmaintenance.php\nroutes.php\nroutes.scanned.php\nschedule-*\nservices.json\n");
file_put_contents(__DIR__ . '/framework/cache/.gitignore', "*\n!data/\n!.gitignore\n");
file_put_contents(__DIR__ . '/framework/cache/data/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/framework/sessions/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/framework/testing/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/framework/views/.gitignore', "*\n!.gitignore\n");
file_put_contents(__DIR__ . '/logs/.gitignore', "*\n!.gitignore\n");

echo "Storage directories created successfully.\n";
