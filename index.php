<?php
declare(strict_types=1);

$config = require __DIR__ . '/includes/config.php';
$site = require __DIR__ . '/includes/site-data.php';
require_once __DIR__ . '/includes/organizer.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$organizerState = mindfulhomes_default_organizer_state();
$spaceTypeOptions = $site['organizer_promo']['space_type_options'];
$goalOptions = $site['organizer_promo']['goal_options'];
$formValues = [
    'spaceType' => 'pantry',
    'goal' => 'quick reset',
    'description' => '',
];
$contactState = [
    'submitted' => false,
    'message' => null,
];
$contactValues = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'message' => '',
];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form_type'] ?? '') === 'organizer'
) {
    $formValues = [
        'spaceType' => is_string($_POST['spaceType'] ?? null) ? $_POST['spaceType'] : $formValues['spaceType'],
        'goal' => is_string($_POST['goal'] ?? null) ? $_POST['goal'] : $formValues['goal'],
        'description' => is_string($_POST['description'] ?? null) ? $_POST['description'] : $formValues['description'],
    ];

    try {
        $organizerState = mindfulhomes_handle_organizer_submission($config, $_POST, $_FILES);
    } catch (Throwable $throwable) {
        error_log('Organizer submission failed: ' . $throwable);
        $organizerState['errors'][] = 'Something went wrong while generating your plan. Please try again.';
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['form_type'] ?? '') === 'contact'
) {
    $contactValues = [
        'first_name' => is_string($_POST['contact_first_name'] ?? null) ? trim($_POST['contact_first_name']) : '',
        'last_name' => is_string($_POST['contact_last_name'] ?? null) ? trim($_POST['contact_last_name']) : '',
        'email' => is_string($_POST['contact_email'] ?? null) ? trim($_POST['contact_email']) : '',
        'message' => is_string($_POST['contact_message'] ?? null) ? trim($_POST['contact_message']) : '',
    ];
    $contactState = [
        'submitted' => true,
        'message' => 'Thanks for reaching out. This contact form is not connected to email yet, so please use the phone or email details here to contact The Mindful Homes directly.',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Mindful Homes | Professional Organizers</title>
    <meta name="description" content="Professional home organization with calm systems, thoughtful design, and an AI organizer tool for simple next steps.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="#home" aria-label="<?= htmlspecialchars($site['brand']['name'], ENT_QUOTES, 'UTF-8') ?>">
                <img
                    class="brand-mark"
                    src="<?= htmlspecialchars($site['brand']['logo'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($site['brand']['logo_alt'], ENT_QUOTES, 'UTF-8') ?>"
                >
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
            <nav id="site-nav" class="site-nav">
                <?php foreach ($site['nav'] as $item): ?>
                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main>
        <section
            id="home"
            class="hero"
            style="--hero-image: linear-gradient(rgba(250,246,241,0.18), rgba(250,246,241,0.34)), url('<?= htmlspecialchars($site['hero']['image'], ENT_QUOTES, 'UTF-8') ?>');"
        >
            <div class="container hero-grid">
                <div class="hero-copy reveal">
                    <h1><?= htmlspecialchars($site['hero']['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="hero-text"><?= htmlspecialchars($site['hero']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="<?= htmlspecialchars($site['hero']['primary_cta']['href'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($site['hero']['primary_cta']['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <a class="button button-secondary" href="<?= htmlspecialchars($site['hero']['secondary_cta']['href'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($site['hero']['secondary_cta']['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                </div>
                <div aria-hidden="true"></div>
            </div>
        </section>

        <section id="organizer" class="section section-accent organizer-section">
            <div class="container organizer-grid">
                <div class="reveal">
                    <p class="section-kicker">Secondary planning tool</p>
                    <h2 class="organizer-title"><?= htmlspecialchars($site['organizer_promo']['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary organizer-summary"><?= htmlspecialchars($site['organizer_promo']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <img
                        class="organizer-image"
                        src="<?= htmlspecialchars($site['organizer_promo']['image'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($site['organizer_promo']['image_alt'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
                <div class="organizer-card reveal">
                    <?php foreach ($organizerState['errors'] as $error): ?>
                        <p class="form-error" role="alert" data-submission-feedback><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                    <form method="post" action="#organizer" enctype="multipart/form-data" class="organizer-form" data-enhanced-form>
                        <input type="hidden" name="form_type" value="organizer">
                        <div class="sr-only" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <label for="media">Upload photos</label>
                        <input id="media" type="file" name="media[]" accept="image/*" multiple required>
                        <p class="form-hint">Add 1 to 6 photos. Natural lighting and wider angles usually produce the most helpful plan.</p>
                        <p class="file-feedback" data-file-feedback>No files selected yet.</p>
                        <div class="organizer-field-grid">
                            <div>
                                <label for="spaceType">Space type</label>
                                <select id="spaceType" name="spaceType">
                                    <?php foreach ($spaceTypeOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $formValues['spaceType'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="goal">Goal</label>
                                <select id="goal" name="goal">
                                    <?php foreach ($goalOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $formValues['goal'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="5" placeholder="Describe what feels messy, what is not working, and what kind of result you want."><?= htmlspecialchars($formValues['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        <button type="submit" data-default-label="Organize now" data-loading-label="Creating your plan...">Organize now</button>
                    </form>
                </div>
                <?php if (is_array($organizerState['result'])): ?>
                    <div class="organizer-report-wrap">
                        <?= mindfulhomes_render_organizer_report($organizerState['result']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="services" class="section services-section">
            <div class="container">
                <div class="section-intro reveal">
                    <h2><?= htmlspecialchars($site['services_intro']['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary section-summary-wide"><?= htmlspecialchars($site['services_intro']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="card-grid">
                    <?php foreach ($site['services'] as $service): ?>
                        <article class="card service-card reveal">
                            <img
                                class="editorial-image"
                                src="<?= htmlspecialchars($service['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($service['image_alt'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                            <h3><?= htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="card-copy"><?= htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="section-actions reveal">
                    <a class="button button-primary" href="<?= htmlspecialchars($site['services_intro']['cta']['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($site['services_intro']['cta']['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>
        </section>

        <section id="process" class="section section-soft process-section">
            <div class="container">
                <div class="section-intro reveal">
                    <h2><?= htmlspecialchars($site['process_intro']['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary"><?= htmlspecialchars($site['process_intro']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="process-grid">
                    <div class="process-visual reveal">
                        <img
                            class="editorial-image process-image"
                            src="<?= htmlspecialchars($site['process_intro']['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($site['process_intro']['image_alt'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="process-list">
                        <?php foreach ($site['process'] as $step): ?>
                            <article class="process-item reveal">
                                <p class="process-number"><?= htmlspecialchars($step['step'], ENT_QUOTES, 'UTF-8') ?></p>
                                <div>
                                    <h3><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p><?= htmlspecialchars($step['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="section">
            <div class="container">
                <div class="section-intro reveal">
                    <h2><?= htmlspecialchars($site['faq_intro']['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary"><?= htmlspecialchars($site['faq_intro']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="faq-grid">
                    <?php foreach ($site['faq'] as $item): ?>
                        <article class="card faq-card reveal">
                            <h3><?= htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="card-copy"><?= htmlspecialchars($item['answer'], ENT_QUOTES, 'UTF-8') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section id="contact" class="section section-soft">
            <div class="container contact-grid">
                <div class="contact-copy reveal">
                    <h2><?= htmlspecialchars($site['contact']['heading'], ENT_NOQUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary"><?= htmlspecialchars($site['contact']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="contact-card">
                        <a href="tel:<?= htmlspecialchars($site['contact']['phone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($site['contact']['phone'], ENT_QUOTES, 'UTF-8') ?></a>
                        <a href="mailto:<?= htmlspecialchars($site['contact']['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($site['contact']['email'], ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
                <div class="organizer-card contact-form-card reveal">
                    <form class="organizer-form" method="post" action="#contact" data-enhanced-form>
                        <input type="hidden" name="form_type" value="contact">
                        <label for="contact-first-name"><?= htmlspecialchars($site['contact']['form']['first_name_label'], ENT_QUOTES, 'UTF-8') ?></label>
                        <input id="contact-first-name" type="text" name="contact_first_name" autocomplete="given-name" value="<?= htmlspecialchars($contactValues['first_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <label for="contact-last-name"><?= htmlspecialchars($site['contact']['form']['last_name_label'], ENT_QUOTES, 'UTF-8') ?></label>
                        <input id="contact-last-name" type="text" name="contact_last_name" autocomplete="family-name" value="<?= htmlspecialchars($contactValues['last_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <label for="contact-email"><?= htmlspecialchars($site['contact']['form']['email_label'], ENT_QUOTES, 'UTF-8') ?></label>
                        <input id="contact-email" type="email" name="contact_email" autocomplete="email" value="<?= htmlspecialchars($contactValues['email'], ENT_QUOTES, 'UTF-8') ?>">
                        <label for="contact-message"><?= htmlspecialchars($site['contact']['form']['message_label'], ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea id="contact-message" name="contact_message" rows="5"><?= htmlspecialchars($contactValues['message'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        <p><?= htmlspecialchars($site['contact']['form']['status_note'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($contactState['submitted'] && is_string($contactState['message'])): ?>
                            <p class="contact-feedback" role="status" aria-live="polite" tabindex="-1" data-submission-feedback><?= htmlspecialchars($contactState['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <button type="submit" data-default-label="<?= htmlspecialchars($site['contact']['form']['submit_label'], ENT_QUOTES, 'UTF-8') ?>" data-loading-label="Sending..."><?= htmlspecialchars($site['contact']['form']['submit_label'], ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </div>
            </div>
        </section>

        <section class="section instagram-section" aria-labelledby="instagram-heading">
            <div class="container">
                <div class="section-intro reveal">
                    <h2 id="instagram-heading"><?= htmlspecialchars($site['instagram']['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="section-summary"><?= htmlspecialchars($site['instagram']['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="instagram-grid">
                    <?php foreach ($site['instagram']['items'] as $item): ?>
                        <article class="card social-card reveal">
                            <img
                                class="editorial-image"
                                src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['alt'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="section-actions reveal">
                    <a class="button button-primary" href="<?= htmlspecialchars($site['instagram']['cta']['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($site['instagram']['cta']['label'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
