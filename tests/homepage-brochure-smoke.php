<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/organizer.php';

function fail_smoke(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$root = dirname(__DIR__);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_FILES = [];

ob_start();
require $root . '/index.php';
$html = ob_get_clean();

if (!is_string($html) || $html === '') {
    fail_smoke('Expected homepage HTML output.');
}

$requiredSnippets = [
    'Creating spaces with purpose',
    'Our design services',
    'Our Process',
    'Organize yourself',
    'Frequently asked questions',
    "Let's connect",
    'Our Instagram',
];

foreach ($requiredSnippets as $snippet) {
    if (strpos($html, $snippet) === false) {
        fail_smoke('Missing homepage snippet: ' . $snippet);
    }
}

$sampleReport = mindfulhomes_render_organizer_report([
    'image_set_assessment' => [
        'classification' => 'same_space',
        'confidence' => 'high',
        'reason' => 'All images show the same pantry from consistent angles.',
    ],
    'space_summary' => [
        'space_type' => 'kitchen pantry',
        'visible_structure' => [
            'approx_shelf_count' => 5,
            'notes' => 'Five wire shelves plus a door-mounted spice rack.',
        ],
        'what_is_visible' => [
            ['item' => 'boxed cereal', 'approx_quantity' => '5-7', 'confidence' => 'high'],
        ],
    ],
    'declutter' => [
        ['item' => 'expired food', 'action' => 'remove', 'reason' => 'Creates space for fresher items.'],
    ],
    'categories' => [
        [
            'name' => 'Breakfast',
            'items' => ['boxed cereal', 'oatmeal'],
            'why_grouped_together' => 'Common breakfast items with similar daily use.',
            'approx_space_needed' => '1 shelf',
        ],
    ],
    'shelf_plan' => [
        [
            'shelf_or_zone' => 'Middle shelf',
            'category' => 'Breakfast',
            'items' => ['boxed cereal'],
            'why_here' => 'Easy everyday access.',
        ],
    ],
    'existing_storage' => [
        [
            'storage_item' => 'wire shelving',
            'can_be_reused' => 'yes',
            'recommended_use' => 'Keep categories assigned by shelf.',
            'notes' => 'Already sturdy and functional.',
        ],
    ],
    'new_storage' => [
        [
            'type' => 'stackable bins',
            'quantity' => '2',
            'use_for' => 'snack grouping',
            'priority' => 'medium',
        ],
    ],
    'action_plan' => [
        ['step' => 1, 'task' => 'Sort pantry items by category.', 'time_estimate_min' => 20],
    ],
    'maintenance' => [
        'daily' => 'Return items to their zones after use.',
        'weekly' => 'Quick reset any drifted categories.',
    ],
]);

$requiredOrganizerSnippets = [
    'Visible items',
    'Declutter first',
    'Category plan',
    'Shelf plan',
    'Storage notes',
    'Maintenance',
];

foreach ($requiredOrganizerSnippets as $snippet) {
    if (strpos($sampleReport, $snippet) === false) {
        fail_smoke('Missing organizer report snippet: ' . $snippet);
    }
}

if (strpos($html, 'faq-question') !== false) {
    fail_smoke('Expected brochure FAQ cards, not accordion button markup.');
}

if (strpos($html, 'Try the AI organizer') !== false) {
    fail_smoke('Expected organizer CTA to be secondary, not promoted in the hero.');
}

fwrite(STDOUT, "Homepage brochure smoke test passed.\n");
