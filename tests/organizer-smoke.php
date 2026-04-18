<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/organizer.php';

function fail_smoke(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6d8AAAAASUVORK5CYII=', true);
if ($tinyPng === false) {
    fail_smoke('Unable to decode fixture PNG.');
}

$imagePath = tempnam(sys_get_temp_dir(), 'mh-img-');
if ($imagePath === false || file_put_contents($imagePath, $tinyPng) === false) {
    fail_smoke('Unable to create image fixture.');
}

$textPath = tempnam(sys_get_temp_dir(), 'mh-txt-');
if ($textPath === false || file_put_contents($textPath, "not an image") === false) {
    fail_smoke('Unable to create text fixture.');
}

$missingPath = $imagePath . '-missing';
$imageSize = filesize($imagePath);
$textSize = filesize($textPath);

if ($imageSize === false || $textSize === false) {
    fail_smoke('Unable to read fixture sizes.');
}

$emptyConfig = [
    'max_upload_count' => 5,
    'max_upload_size_bytes' => 1024,
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
];

$errors = mindfulhomes_validate_uploads([], $emptyConfig);
if ($errors === [] || $errors[0] !== 'Please upload at least one image.') {
    fail_smoke('Expected missing upload validation error.');
}

$tooManyErrors = mindfulhomes_validate_uploads([
    [
        'name' => 'first.png',
        'type' => 'image/png',
        'tmp_name' => $imagePath,
        'error' => UPLOAD_ERR_OK,
        'size' => $imageSize,
    ],
    [
        'name' => 'second.png',
        'type' => 'image/png',
        'tmp_name' => $imagePath,
        'error' => UPLOAD_ERR_OK,
        'size' => $imageSize,
    ],
], [
    'max_upload_count' => 1,
    'max_upload_size_bytes' => 1024,
    'allowed_mime_types' => ['image/png'],
]);

if (!in_array('Please upload no more than 1 images.', $tooManyErrors, true)) {
    fail_smoke('Expected too-many-files validation error.');
}

$prompt = mindfulhomes_build_prompt([
    'spaceType' => 'pantry',
    'goal' => 'quick reset',
    'description' => 'Too many snacks and no clear zones.',
]);

if (strpos($prompt, 'Space type: pantry') === false) {
    fail_smoke('Expected prompt to include the selected space type.');
}

if (strpos($prompt, 'Identify visible items as specifically as possible') === false) {
    fail_smoke('Expected prompt to request specific visible-item identification.');
}

if (strpos($prompt, 'Create categories based on visible items') === false) {
    fail_smoke('Expected prompt to request categories based on visible items.');
}

if (strpos($prompt, 'Assign categories to specific shelves or zones') === false) {
    fail_smoke('Expected prompt to request shelf or zone assignments.');
}

if (strpos($prompt, 'Recommend new storage only if necessary') === false) {
    fail_smoke('Expected prompt to prioritize reusing storage before recommending new storage.');
}

$invalidMimeErrors = mindfulhomes_validate_uploads([
    [
        'name' => 'spoofed.png',
        'type' => 'image/png',
        'tmp_name' => $textPath,
        'error' => UPLOAD_ERR_OK,
        'size' => $textSize,
    ],
], [
    'max_upload_count' => 5,
    'max_upload_size_bytes' => 1024,
    'allowed_mime_types' => ['image/png'],
]);

if (!in_array('spoofed.png must be a JPG, PNG, or WEBP image.', $invalidMimeErrors, true)) {
    fail_smoke('Expected server-side MIME validation to reject a non-image file.');
}

try {
    mindfulhomes_prepare_data_urls([
        [
            'name' => 'missing.png',
            'type' => 'image/png',
            'detected_mime' => 'image/png',
            'tmp_name' => $missingPath,
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ],
    ]);
    fail_smoke('Expected unreadable upload to throw during data URL preparation.');
} catch (RuntimeException $runtimeException) {
    if (strpos($runtimeException->getMessage(), 'Unable to read uploaded file') === false) {
        fail_smoke('Expected a clear unreadable-upload exception message.');
    }
}

@unlink($imagePath);
@unlink($textPath);

fwrite(STDOUT, "Organizer smoke test passed.\n");
