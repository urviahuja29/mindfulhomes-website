<?php
declare(strict_types=1);

function mindfulhomes_default_organizer_state(): array
{
    return [
        'errors' => [],
        'result' => null,
        'raw_output' => null,
    ];
}

function mindfulhomes_has_report_items($value): bool
{
    return is_array($value) && $value !== [];
}

function mindfulhomes_report_items($value): array
{
    return is_array($value) ? $value : [];
}

function mindfulhomes_report_value($value): string
{
    return is_string($value) ? trim($value) : '';
}

function mindfulhomes_handle_organizer_submission(array $config, array $post, array $files): array
{
    $state = mindfulhomes_default_organizer_state();

    if (($post['form_type'] ?? '') !== 'organizer') {
        return $state;
    }

    $abuseErrors = mindfulhomes_organizer_abuse_errors($post);
    if ($abuseErrors !== []) {
        $state['errors'] = $abuseErrors;
        return $state;
    }

    $uploads = mindfulhomes_normalize_uploads($files['media'] ?? null);
    $validationErrors = mindfulhomes_validate_uploads($uploads, $config);

    if ($validationErrors !== []) {
        $state['errors'] = $validationErrors;
        return $state;
    }

    if (($config['openai_api_key'] ?? '') === '') {
        $state['errors'][] = 'The organizer is not configured yet. Please add your OpenAI API key on the server.';
        return $state;
    }

    $preparedFiles = mindfulhomes_prepare_data_urls($uploads);
    $rawOutput = mindfulhomes_request_plan($config, $post, $preparedFiles);
    $state['raw_output'] = $rawOutput;

    $decoded = json_decode($rawOutput, true);
    if (is_array($decoded)) {
        $state['result'] = $decoded;
    } else {
        $state['errors'][] = 'The organizer returned an unreadable response. Please try again with clearer photos.';
    }

    return $state;
}

function mindfulhomes_organizer_abuse_errors(array $post): array
{
    $honeypotValue = is_string($post['website'] ?? null) ? trim($post['website']) : '';
    if ($honeypotValue !== '') {
        return ['We could not process that submission. Please try again.'];
    }

    return mindfulhomes_organizer_rate_limit_errors();
}

function mindfulhomes_organizer_rate_limit_errors(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    $sessionKey = 'mindfulhomes_organizer_submission_times';
    $windowSeconds = 15 * 60;
    $maxSubmissions = 5;
    $now = time();
    $submissionTimes = $_SESSION[$sessionKey] ?? [];

    if (!is_array($submissionTimes)) {
        $submissionTimes = [];
    }

    $submissionTimes = array_values(array_filter(
        array_map('intval', $submissionTimes),
        static fn(int $timestamp): bool => $timestamp > ($now - $windowSeconds)
    ));

    if (count($submissionTimes) >= $maxSubmissions) {
        $_SESSION[$sessionKey] = $submissionTimes;
        return ['Please wait a few minutes before requesting another organizer plan.'];
    }

    $submissionTimes[] = $now;
    $_SESSION[$sessionKey] = $submissionTimes;

    return [];
}

function mindfulhomes_normalize_uploads($fileBag): array
{
    if (!is_array($fileBag) || !isset($fileBag['name']) || !is_array($fileBag['name'])) {
        return [];
    }

    $normalized = [];
    foreach ($fileBag['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $fileBag['type'][$index] ?? '',
            'tmp_name' => $fileBag['tmp_name'][$index] ?? '',
            'error' => $fileBag['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileBag['size'][$index] ?? 0,
        ];
    }

    return array_values(array_filter($normalized, static fn(array $file): bool => $file['error'] !== UPLOAD_ERR_NO_FILE));
}

function mindfulhomes_validate_uploads(array $uploads, array $config): array
{
    $errors = [];

    if ($uploads === []) {
        $errors[] = 'Please upload at least one image.';
        return $errors;
    }

    if (count($uploads) > (int) $config['max_upload_count']) {
        $errors[] = 'Please upload no more than ' . $config['max_upload_count'] . ' images.';
    }

    foreach ($uploads as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'One of the files could not be uploaded.';
            continue;
        }

        if ($file['size'] > (int) $config['max_upload_size_bytes']) {
            $errors[] = $file['name'] . ' is too large.';
        }

        try {
            $detectedMime = mindfulhomes_detect_upload_mime(
                (string) ($file['tmp_name'] ?? ''),
                (string) ($file['name'] ?? 'uploaded file')
            );
        } catch (RuntimeException $runtimeException) {
            $errors[] = $runtimeException->getMessage();
            continue;
        }

        if (!in_array($detectedMime, $config['allowed_mime_types'], true)) {
            $errors[] = $file['name'] . ' must be a JPG, PNG, or WEBP image.';
        }
    }

    return $errors;
}

function mindfulhomes_prepare_data_urls(array $uploads): array
{
    $prepared = [];
    foreach ($uploads as $file) {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $displayName = (string) ($file['name'] ?? 'uploaded file');
        $detectedMime = isset($file['detected_mime']) && is_string($file['detected_mime']) && $file['detected_mime'] !== ''
            ? $file['detected_mime']
            : mindfulhomes_detect_upload_mime($tmpName, $displayName);

        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new RuntimeException('Unable to read uploaded file "' . $displayName . '".');
        }

        $contents = file_get_contents($tmpName);

        if ($contents === false) {
            throw new RuntimeException('Unable to read uploaded file "' . $displayName . '".');
        }

        $base64 = base64_encode($contents);
        $prepared[] = [
            'type' => 'input_image',
            'image_url' => 'data:' . $detectedMime . ';base64,' . $base64,
        ];
    }

    return $prepared;
}

function mindfulhomes_build_prompt(array $post): string
{
    return "Input:\n"
        . '- Space type: ' . ($post['spaceType'] ?? '') . "\n"
        . '- User goal: ' . ($post['goal'] ?? '') . "\n"
        . '- Description: ' . ($post['description'] ?? '') . "\n\n"
        . "Task:\n"
        . "Analyze all uploaded images together.\n\n"
        . "Important requirements:\n"
        . "- First determine whether the uploaded images show:\n"
        . "  1. the same space\n"
        . "  2. related zones of one room\n"
        . "  3. different spaces\n"
        . "- If the images show different spaces, do NOT combine them into one organizing plan\n"
        . "- If inconsistent, clearly state that the images should be analyzed separately\n"
        . "- Treat all uploaded images as views of the same space ONLY if they are consistent\n"
        . "- Start by summarizing exactly what you see across the images\n"
        . "- Identify visible items as specifically as possible\n"
        . "- Include approximate counts when possible\n"
        . "- Do not mention items unless they are visible\n"
        . "- If uncertain, say \"unclear\" or \"possible\"\n"
        . "- Identify approximate number of shelves or levels\n"
        . "- Create categories based on visible items\n"
        . "- Explain grouping logic\n"
        . "- Assign categories to specific shelves or zones\n"
        . "- Estimate space usage\n"
        . "- Identify existing storage and reuse it first\n"
        . "- Recommend new storage only if necessary\n"
        . "- Make plan actionable and reduce overwhelm\n"
        . "- Return strict JSON in the agreed structure";
}

function mindfulhomes_request_plan(array $config, array $post, array $imageInputs): string
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    $payload = [
        'model' => $config['openai_model'],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'organizer_plan',
                'description' => 'A structured home organizing plan based on uploaded room photos.',
                'strict' => true,
                'schema' => mindfulhomes_organizer_response_schema(),
            ],
        ],
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => "You are a professional home organizer and visual space analyst.\n\n"
                            . "Rules:\n"
                            . "- First classify the image set as same space, related zones of one room, or different spaces\n"
                            . "- If images are different spaces, do not merge them into one plan\n"
                            . "- Stay grounded in visible items\n"
                            . "- Do not hallucinate items\n"
                            . "- Use approximate counts with confidence levels\n"
                            . "- Reduce first, organize second\n"
                            . "- Reuse existing storage before suggesting new\n"
                            . "- Assign items to specific shelves or zones\n"
                            . "- Keep plans practical and simple\n\n"
                            . "Return only valid JSON.",
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => array_merge(
                    [
                        [
                            'type' => 'input_text',
                            'text' => mindfulhomes_build_prompt($post),
                        ],
                    ],
                    $imageInputs
                ),
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['openai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($response === false || $error !== '') {
        throw new RuntimeException('Unable to reach the organizer service right now.');
    }

    $decoded = json_decode($response, true);
    if ($status >= 400 || !is_array($decoded)) {
        throw new RuntimeException('The organizer service returned an unexpected response.');
    }

    $outputText = $decoded['output_text'] ?? null;
    if (is_string($outputText) && $outputText !== '') {
        return $outputText;
    }

    $messageText = $decoded['output'][0]['content'][0]['text'] ?? null;
    if (is_string($messageText) && $messageText !== '') {
        return $messageText;
    }

    return '';
}

function mindfulhomes_detect_upload_mime(string $tmpName, string $displayName = 'uploaded file'): string
{
    if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
        throw new RuntimeException('Unable to inspect uploaded file "' . $displayName . '".');
    }

    if (PHP_SAPI !== 'cli' && function_exists('is_uploaded_file') && !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Unable to inspect uploaded file "' . $displayName . '".');
    }

    $detectedMime = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $finfoMime = finfo_file($finfo, $tmpName);

            if (is_string($finfoMime) && $finfoMime !== '') {
                $detectedMime = $finfoMime;
            }
        }
    }

    if ($detectedMime === '' && function_exists('mime_content_type')) {
        $fallbackMime = mime_content_type($tmpName);
        if (is_string($fallbackMime) && $fallbackMime !== '') {
            $detectedMime = $fallbackMime;
        }
    }

    if ($detectedMime === '') {
        throw new RuntimeException('Unable to inspect uploaded file "' . $displayName . '".');
    }

    return $detectedMime;
}

function mindfulhomes_render_organizer_report(array $result): string
{
    $assessment = is_array($result['image_set_assessment'] ?? null) ? $result['image_set_assessment'] : [];
    $spaceSummary = is_array($result['space_summary'] ?? null) ? $result['space_summary'] : [];
    $visibleStructure = is_array($spaceSummary['visible_structure'] ?? null) ? $spaceSummary['visible_structure'] : [];
    $visibleItems = mindfulhomes_report_items($spaceSummary['what_is_visible'] ?? null);
    $declutterItems = mindfulhomes_report_items($result['declutter'] ?? null);
    $categories = mindfulhomes_report_items($result['categories'] ?? null);
    $shelfPlan = mindfulhomes_report_items($result['shelf_plan'] ?? null);
    $existingStorage = mindfulhomes_report_items($result['existing_storage'] ?? null);
    $newStorage = mindfulhomes_report_items($result['new_storage'] ?? null);
    $actionPlan = mindfulhomes_report_items($result['action_plan'] ?? null);
    $maintenance = is_array($result['maintenance'] ?? null) ? $result['maintenance'] : [];

    $spaceType = mindfulhomes_report_value($spaceSummary['space_type'] ?? '');
    $assessmentClass = mindfulhomes_report_value($assessment['classification'] ?? '');
    $assessmentConfidence = mindfulhomes_report_value($assessment['confidence'] ?? '');
    $assessmentReason = mindfulhomes_report_value($assessment['reason'] ?? '');
    $shelfCount = $visibleStructure['approx_shelf_count'] ?? null;
    $structureNotes = mindfulhomes_report_value($visibleStructure['notes'] ?? '');
    $dailyMaintenance = mindfulhomes_report_value($maintenance['daily'] ?? '');
    $weeklyMaintenance = mindfulhomes_report_value($maintenance['weekly'] ?? '');

    ob_start();
    ?>
    <div class="results-panel organizer-report" role="status" aria-live="polite" tabindex="-1" data-submission-feedback>
        <div class="report-top-grid">
            <section class="result-block report-overview">
                <div class="report-section-header">
                    <p class="section-kicker">Organizer overview</p>
                    <h3><?= htmlspecialchars($spaceType !== '' ? ucwords(str_replace('_', ' ', $spaceType)) : 'Your organizing report', ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <div class="report-chip-list">
                    <?php if ($assessmentClass !== ''): ?>
                        <span class="report-chip"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $assessmentClass)), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if ($assessmentConfidence !== ''): ?>
                        <span class="report-chip report-chip-muted"><?= htmlspecialchars(ucwords($assessmentConfidence) . ' confidence', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="report-metric-grid">
                    <?php if (is_int($shelfCount) || ctype_digit((string) $shelfCount)): ?>
                        <div class="report-metric">
                            <span class="report-metric-label">Shelves</span>
                            <strong><?= htmlspecialchars((string) $shelfCount, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="report-metric">
                        <span class="report-metric-label">Visible items</span>
                        <strong><?= htmlspecialchars((string) count($visibleItems), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="report-metric">
                        <span class="report-metric-label">Action steps</span>
                        <strong><?= htmlspecialchars((string) count($actionPlan), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
                <?php if ($structureNotes !== ''): ?>
                    <p class="report-lead"><?= htmlspecialchars($structureNotes, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($assessmentReason !== ''): ?>
                    <p class="report-supporting-copy"><?= htmlspecialchars($assessmentReason, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </section>

            <?php if (mindfulhomes_has_report_items($actionPlan)): ?>
                <section class="result-block report-section report-action-plan">
                    <div class="report-section-header">
                        <p class="section-kicker">Priority steps</p>
                        <h3>Action plan</h3>
                    </div>
                    <ol class="report-timeline">
                        <?php foreach ($actionPlan as $step): ?>
                            <?php
                            $task = mindfulhomes_report_value($step['task'] ?? '');
                            $minutes = $step['time_estimate_min'] ?? null;
                            if ($task === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <span><?= htmlspecialchars($task, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (is_int($minutes) || ctype_digit((string) $minutes)): ?>
                                    <strong><?= htmlspecialchars((string) $minutes . ' min', ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
        </div>

        <?php if (mindfulhomes_has_report_items($visibleItems)): ?>
            <section class="result-block report-section">
                <div class="report-section-header">
                    <p class="section-kicker">Observed details</p>
                    <h3>Visible items</h3>
                </div>
                <ul class="report-chip-list report-chip-list-expanded">
                    <?php foreach ($visibleItems as $item): ?>
                        <?php
                        $itemName = mindfulhomes_report_value($item['item'] ?? '');
                        $quantity = mindfulhomes_report_value($item['approx_quantity'] ?? '');
                        $confidence = mindfulhomes_report_value($item['confidence'] ?? '');
                        if ($itemName === '') {
                            continue;
                        }
                        ?>
                        <li class="report-observation">
                            <strong><?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($quantity !== ''): ?>
                                <span><?= htmlspecialchars($quantity, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($confidence !== ''): ?>
                                <em><?= htmlspecialchars($confidence, ENT_QUOTES, 'UTF-8') ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (mindfulhomes_has_report_items($declutterItems)): ?>
            <section class="result-block report-section">
                <div class="report-section-header">
                    <p class="section-kicker">Reduce first</p>
                    <h3>Declutter first</h3>
                </div>
                <div class="report-card-grid">
                    <?php foreach ($declutterItems as $item): ?>
                        <?php
                        $itemName = mindfulhomes_report_value($item['item'] ?? '');
                        $action = mindfulhomes_report_value($item['action'] ?? '');
                        $reason = mindfulhomes_report_value($item['reason'] ?? '');
                        if ($itemName === '' && $reason === '') {
                            continue;
                        }
                        ?>
                        <article class="report-card">
                            <h4><?= htmlspecialchars($itemName !== '' ? $itemName : 'Declutter note', ENT_QUOTES, 'UTF-8') ?></h4>
                            <?php if ($action !== ''): ?>
                                <p class="report-card-meta"><?= htmlspecialchars(ucwords($action), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($reason !== ''): ?>
                                <p><?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (mindfulhomes_has_report_items($categories)): ?>
            <section class="result-block report-section">
                <div class="report-section-header">
                    <p class="section-kicker">Grouping strategy</p>
                    <h3>Category plan</h3>
                </div>
                <div class="report-card-grid">
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $name = mindfulhomes_report_value($category['name'] ?? '');
                        $items = mindfulhomes_report_items($category['items'] ?? null);
                        $groupingReason = mindfulhomes_report_value($category['why_grouped_together'] ?? '');
                        $spaceNeeded = mindfulhomes_report_value($category['approx_space_needed'] ?? '');
                        if ($name === '') {
                            continue;
                        }
                        ?>
                        <article class="report-card">
                            <h4><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h4>
                            <?php if ($groupingReason !== ''): ?>
                                <p><?= htmlspecialchars($groupingReason, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($spaceNeeded !== ''): ?>
                                <p class="report-card-meta">Approx space needed: <?= htmlspecialchars($spaceNeeded, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($items !== []): ?>
                                <ul class="report-bullet-list">
                                    <?php foreach ($items as $entry): ?>
                                        <?php if (is_string($entry) && trim($entry) !== ''): ?>
                                            <li><?= htmlspecialchars(trim($entry), ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (mindfulhomes_has_report_items($shelfPlan)): ?>
            <section class="result-block report-section">
                <div class="report-section-header">
                    <p class="section-kicker">Placement guide</p>
                    <h3>Shelf plan</h3>
                </div>
                <div class="report-timeline-grid">
                    <?php foreach ($shelfPlan as $zone): ?>
                        <?php
                        $zoneName = mindfulhomes_report_value($zone['shelf_or_zone'] ?? '');
                        $category = mindfulhomes_report_value($zone['category'] ?? '');
                        $items = mindfulhomes_report_items($zone['items'] ?? null);
                        $whyHere = mindfulhomes_report_value($zone['why_here'] ?? '');
                        if ($zoneName === '' && $category === '' && $whyHere === '') {
                            continue;
                        }
                        ?>
                        <article class="report-placement-card">
                            <div class="report-placement-heading">
                                <h4><?= htmlspecialchars($zoneName !== '' ? $zoneName : 'Zone', ENT_QUOTES, 'UTF-8') ?></h4>
                                <?php if ($category !== ''): ?>
                                    <span class="report-chip"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($whyHere !== ''): ?>
                                <p><?= htmlspecialchars($whyHere, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($items !== []): ?>
                                <ul class="report-bullet-list">
                                    <?php foreach ($items as $entry): ?>
                                        <?php if (is_string($entry) && trim($entry) !== ''): ?>
                                            <li><?= htmlspecialchars(trim($entry), ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (mindfulhomes_has_report_items($existingStorage) || mindfulhomes_has_report_items($newStorage)): ?>
            <section class="result-block report-section">
                <div class="report-section-header">
                    <p class="section-kicker">Reuse first</p>
                    <h3>Storage notes</h3>
                </div>
                <div class="report-storage-grid">
                    <?php if (mindfulhomes_has_report_items($existingStorage)): ?>
                        <div>
                            <h4>Existing storage</h4>
                            <div class="report-stack">
                                <?php foreach ($existingStorage as $storage): ?>
                                    <?php
                                    $name = mindfulhomes_report_value($storage['storage_item'] ?? '');
                                    $reuse = mindfulhomes_report_value($storage['can_be_reused'] ?? '');
                                    $use = mindfulhomes_report_value($storage['recommended_use'] ?? '');
                                    $notes = mindfulhomes_report_value($storage['notes'] ?? '');
                                    if ($name === '') {
                                        continue;
                                    }
                                    ?>
                                    <article class="report-card report-card-compact">
                                        <h5><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h5>
                                        <?php if ($reuse !== ''): ?>
                                            <p class="report-card-meta">Reuse: <?= htmlspecialchars($reuse, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($use !== ''): ?>
                                            <p><?= htmlspecialchars($use, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($notes !== ''): ?>
                                            <p class="report-supporting-copy"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (mindfulhomes_has_report_items($newStorage)): ?>
                        <div>
                            <h4>New storage</h4>
                            <div class="report-stack">
                                <?php foreach ($newStorage as $storage): ?>
                                    <?php
                                    $type = mindfulhomes_report_value($storage['type'] ?? '');
                                    $quantity = mindfulhomes_report_value($storage['quantity'] ?? '');
                                    $useFor = mindfulhomes_report_value($storage['use_for'] ?? '');
                                    $priority = mindfulhomes_report_value($storage['priority'] ?? '');
                                    if ($type === '') {
                                        continue;
                                    }
                                    ?>
                                    <article class="report-card report-card-compact">
                                        <h5><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></h5>
                                        <?php if ($quantity !== ''): ?>
                                            <p class="report-card-meta">Quantity: <?= htmlspecialchars($quantity, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($useFor !== ''): ?>
                                            <p><?= htmlspecialchars($useFor, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($priority !== ''): ?>
                                            <p class="report-card-meta">Priority: <?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($dailyMaintenance !== '' || $weeklyMaintenance !== ''): ?>
            <section class="result-block report-section report-maintenance">
                <div class="report-section-header">
                    <p class="section-kicker">Keep it going</p>
                    <h3>Maintenance</h3>
                </div>
                <div class="report-maintenance-grid">
                    <?php if ($dailyMaintenance !== ''): ?>
                        <div class="report-card report-card-compact">
                            <h4>Daily</h4>
                            <p><?= htmlspecialchars($dailyMaintenance, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($weeklyMaintenance !== ''): ?>
                        <div class="report-card report-card-compact">
                            <h4>Weekly</h4>
                            <p><?= htmlspecialchars($weeklyMaintenance, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <?php

    $html = ob_get_clean();
    return is_string($html) ? $html : '';
}

function mindfulhomes_organizer_response_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [
            'image_set_assessment',
            'space_summary',
            'declutter',
            'categories',
            'shelf_plan',
            'existing_storage',
            'new_storage',
            'action_plan',
            'maintenance',
        ],
        'properties' => [
            'image_set_assessment' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['classification', 'confidence', 'reason'],
                'properties' => [
                    'classification' => ['type' => 'string'],
                    'confidence' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ],
            ],
            'space_summary' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['space_type', 'visible_structure', 'what_is_visible'],
                'properties' => [
                    'space_type' => ['type' => 'string'],
                    'visible_structure' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['approx_shelf_count', 'notes'],
                        'properties' => [
                            'approx_shelf_count' => ['type' => 'integer'],
                            'notes' => ['type' => 'string'],
                        ],
                    ],
                    'what_is_visible' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['item', 'approx_quantity', 'confidence'],
                            'properties' => [
                                'item' => ['type' => 'string'],
                                'approx_quantity' => ['type' => 'string'],
                                'confidence' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'declutter' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['item', 'action', 'reason'],
                    'properties' => [
                        'item' => ['type' => 'string'],
                        'action' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                ],
            ],
            'categories' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'items', 'why_grouped_together', 'approx_space_needed'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'why_grouped_together' => ['type' => 'string'],
                        'approx_space_needed' => ['type' => 'string'],
                    ],
                ],
            ],
            'shelf_plan' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['shelf_or_zone', 'category', 'items', 'why_here'],
                    'properties' => [
                        'shelf_or_zone' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'why_here' => ['type' => 'string'],
                    ],
                ],
            ],
            'existing_storage' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['storage_item', 'can_be_reused', 'recommended_use', 'notes'],
                    'properties' => [
                        'storage_item' => ['type' => 'string'],
                        'can_be_reused' => ['type' => 'string'],
                        'recommended_use' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                ],
            ],
            'new_storage' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['type', 'quantity', 'use_for', 'priority'],
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'quantity' => ['type' => 'string'],
                        'use_for' => ['type' => 'string'],
                        'priority' => ['type' => 'string'],
                    ],
                ],
            ],
            'action_plan' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['step', 'task', 'time_estimate_min'],
                    'properties' => [
                        'step' => ['type' => 'integer'],
                        'task' => ['type' => 'string'],
                        'time_estimate_min' => ['type' => 'integer'],
                    ],
                ],
            ],
            'maintenance' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['daily', 'weekly'],
                'properties' => [
                    'daily' => ['type' => 'string'],
                    'weekly' => ['type' => 'string'],
                ],
            ],
        ],
    ];
}
