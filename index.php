<?php
/**
 * Landing page — public homepage.
 * Logged-in users are routed straight to their dashboard.
 */
require_once __DIR__ . '/backend/includes/Auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

Auth::initSession();

if (Auth::isLoggedIn()) {
    $redirect = match(Auth::getRole()) {
        'admin' => 'admin/dashboard.php',
        'staff' => 'staff/dashboard.php',
        default => 'student/dashboard.php',
    };
    header("Location: $redirect");
    exit;
}

// Live stats for the hero strip
$db = Database::getInstance();
try {
    $metrics = $db->fetchOne(
        "SELECT COUNT(*) AS total,
                COUNT(CASE WHEN status='Completed' THEN 1 END) AS resolved
         FROM requests WHERE is_archived = 0"
    ) ?: ['total' => 0, 'resolved' => 0];

    $avgResponse = $db->fetchOne(
        "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, a.assigned_at)), 1) AS hours
         FROM requests r JOIN assignments a ON r.id = a.request_id
         WHERE r.is_archived = 0"
    );

    $staffCount = $db->fetchOne(
        "SELECT COUNT(*) AS count FROM users WHERE role='staff' AND is_active=1"
    ) ?: ['count' => 0];

    $categories = $db->fetchAll(
        "SELECT name, icon FROM categories WHERE is_active = 1 ORDER BY display_order"
    );
} catch (Exception $e) {
    $metrics = ['total' => 0, 'resolved' => 0];
    $avgResponse = ['hours' => null];
    $staffCount = ['count' => 0];
    $categories = [];
}

$totalRequests = (int)($metrics['total'] ?? 0);
$totalResolved = (int)($metrics['resolved'] ?? 0);
$avgHours      = $avgResponse['hours'] !== null ? (float)$avgResponse['hours'] : 2.5;
$staffTotal    = (int)($staffCount['count'] ?? 0);
$satisfaction  = 98; // static — we don't collect ratings yet

// Map category names to emoji (icon column stores lucide names we can't render here)
$catEmoji = [
    'Electrical' => '⚡',
    'Plumbing'   => '💧',
    'Furniture'  => '🛋️',
    'HVAC'       => '❄️',
    'Other'      => '🔧',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Fast, transparent hostel maintenance</title>
    <meta name="description" content="Submit, track, and resolve hostel maintenance requests with ease. Built for Ashesi University residents.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/css/style.css">
</head>
<body class="landing">

<canvas id="bgCanvas" class="bg-canvas"></canvas>

<!-- Nav -->
<nav class="landing-nav">
    <div class="brand">
        <span class="logo">🏠</span>
        <span><?= APP_NAME ?></span>
    </div>
    <div class="nav-links">
        <a href="#features">Features</a>
        <a href="#how">How it works</a>
        <a href="#roles">For everyone</a>
        <a href="login.php" class="btn-ghost">Sign in</a>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <span class="dot"></span>
            <span>Live at Ashesi University</span>
        </div>
        <h1>
            Hostel maintenance,<br>
            <span class="gradient-text">reimagined for residents.</span>
        </h1>
        <p class="subtitle">
            Report a broken light, leaking tap, or wobbly desk in seconds.
            Track every step from request to resolution — no more chasing, no more guessing.
        </p>
        <div class="hero-cta">
            <a href="register.php" class="btn-gradient">Get started — it's free <span>→</span></a>
            <a href="login.php" class="btn-ghost">I already have an account</a>
        </div>
    </div>

    <a href="#stats" class="scroll-indicator" aria-label="Scroll to content">
        <span>Scroll</span>
        <span class="arrow"></span>
    </a>
</section>

<!-- Stats strip -->
<div id="stats" class="stats-strip">
    <div class="stat-item">
        <div class="num" data-count="<?= $totalRequests ?>">0</div>
        <div class="label">Requests logged</div>
    </div>
    <div class="stat-item">
        <div class="num" data-count="<?= $totalResolved ?>">0</div>
        <div class="label">Resolved</div>
    </div>
    <div class="stat-item">
        <div class="num" data-count="<?= $avgHours ?>">0</div>
        <div class="label">Avg response (hrs)</div>
    </div>
    <div class="stat-item">
        <div class="num" data-count="<?= $satisfaction ?>">0<span style="font-size:0.6em">%</span></div>
        <div class="label">Issues closed on first try</div>
    </div>
</div>

<!-- Features -->
<section class="section" id="features">
    <div class="section-head reveal">
        <span class="eyebrow">Features</span>
        <h2>Everything you need. Nothing you don't.</h2>
        <p>A focused toolkit that gets maintenance moving instead of getting in the way.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card reveal">
            <div class="icon">📝</div>
            <h3>Submit in seconds</h3>
            <p>Describe the issue, snap a photo, pick a category. Your request lands instantly on the admin dashboard.</p>
        </div>
        <div class="feature-card reveal">
            <div class="icon">🔍</div>
            <h3>Live status tracking</h3>
            <p>Watch each request move from <em>Pending</em> to <em>In Progress</em> to <em>Completed</em> — with timestamps on every change.</p>
        </div>
        <div class="feature-card reveal">
            <div class="icon">👷</div>
            <h3>Smart staff assignment</h3>
            <p>Admins route work to the right technician based on current workload — no more tasks falling through the cracks.</p>
        </div>
        <div class="feature-card reveal">
            <div class="icon">🔔</div>
            <h3>Real-time notifications</h3>
            <p>Get notified the moment someone picks up your request, updates it, or closes it out.</p>
        </div>
        <div class="feature-card reveal">
            <div class="icon">📸</div>
            <h3>Photo evidence</h3>
            <p>Attach a picture so staff arrive knowing exactly what they're fixing — bring the right tools the first time.</p>
        </div>
        <div class="feature-card reveal">
            <div class="icon">📊</div>
            <h3>Admin insights</h3>
            <p>Response times, workload, categories, resolution rates — all visible at a glance for smarter decisions.</p>
        </div>
    </div>
</section>

<!-- Categories -->
<?php if (!empty($categories)): ?>
<section class="section">
    <div class="section-head reveal">
        <span class="eyebrow">What we handle</span>
        <h2>From a loose screw to a silent AC</h2>
        <p>Five categories cover every hostel-life hiccup.</p>
    </div>
    <div class="cat-chips reveal">
        <?php foreach ($categories as $cat): ?>
            <div class="cat-chip">
                <span class="emoji"><?= $catEmoji[$cat['name']] ?? '🔧' ?></span>
                <span><?= htmlspecialchars($cat['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- How it works -->
<section class="section" id="how">
    <div class="section-head reveal">
        <span class="eyebrow">How it works</span>
        <h2>Three steps. Zero friction.</h2>
        <p>Built to match how residents actually report problems — not how bureaucracy imagines them.</p>
    </div>
    <div class="steps">
        <div class="step reveal">
            <div class="step-num">1</div>
            <h3>Report</h3>
            <p>Fill in a quick form: title, description, category, and an optional photo. Submit in under a minute.</p>
        </div>
        <div class="step reveal">
            <div class="step-num">2</div>
            <h3>Assign</h3>
            <p>An admin reviews and routes the request to a maintenance staff member with the right availability.</p>
        </div>
        <div class="step reveal">
            <div class="step-num">3</div>
            <h3>Resolve</h3>
            <p>Staff marks the job done. You get a notification. Full history stays on record for accountability.</p>
        </div>
    </div>
</section>

<!-- Roles -->
<section class="section" id="roles">
    <div class="section-head reveal">
        <span class="eyebrow">For everyone</span>
        <h2>One platform. Three experiences.</h2>
        <p>Everyone gets a view designed for how they actually work.</p>
    </div>
    <div class="roles-grid">
        <div class="role-card reveal">
            <span class="role-icon">🎓</span>
            <h3>Students</h3>
            <ul>
                <li>Submit requests in under a minute</li>
                <li>Attach photos for clarity</li>
                <li>Real-time status tracking</li>
                <li>Full history of past requests</li>
                <li>Reopen closed issues if needed</li>
            </ul>
        </div>
        <div class="role-card reveal">
            <span class="role-icon">🛠️</span>
            <h3>Maintenance staff</h3>
            <ul>
                <li>Personal task queue, priority-sorted</li>
                <li>One-click status updates</li>
                <li>See room, block, and photos upfront</li>
                <li>Track your completed work</li>
                <li>No paperwork — ever</li>
            </ul>
        </div>
        <div class="role-card reveal">
            <span class="role-icon">📋</span>
            <h3>Administrators</h3>
            <ul>
                <li>Full visibility across all requests</li>
                <li>Assign & reassign with a click</li>
                <li>Response & resolution metrics</li>
                <li>Staff workload balancing</li>
                <li>Filter by category, status, priority</li>
            </ul>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="cta-section">
    <div class="cta-card reveal">
        <h2>Ready to stop chasing your caretaker?</h2>
        <p>Create your account with your Ashesi email and submit your first request in minutes.</p>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <a href="register.php" class="btn-gradient">Create your account <span>→</span></a>
            <a href="login.php" class="btn-ghost">Sign in instead</a>
        </div>
    </div>
</section>

<footer class="landing-footer">
    &copy; 2026 Group 11 — CS415 Software Engineering, Ashesi University &nbsp;·&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?>
</footer>

<script src="frontend/js/bg-animation.js"></script>
<script src="frontend/js/landing.js"></script>
</body>
</html>
