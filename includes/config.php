<?php
declare(strict_types=1);

$fallbackKeyPath = __DIR__ . '/../../private/openai-key.php';
$fallbackApiKey = is_readable($fallbackKeyPath) ? trim((string) require $fallbackKeyPath) : '';

return [
    'site_url' => 'https://themindfulhomes.com',
    'contact_email' => 'themindfulhomes@gmail.com',
    'contact_phone' => '818-601-2015',
    'max_upload_count' => 5,
    'max_upload_size_bytes' => 8 * 1024 * 1024,
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'openai_model' => 'gpt-5.4-mini',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: $fallbackApiKey,
];
