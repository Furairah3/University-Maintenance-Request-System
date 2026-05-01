<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    $r = $_SESSION['role'];
    redirect(SITE_URL . "/{$r}/dashboard.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HostelIQ — Smart Hostel Management | Ashesi University</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="landing-nav">
  <div class="landing-nav-logo">Hostel<span>IQ</span></div>
  <div class="landing-nav-links">
    <a href="#features">Features</a>
    <a href="login.php">Login</a>
    <a href="register.php" class="btn btn-primary btn-sm" style="margin-left:8px;">Register</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-eyebrow">🏛 Ashesi University — CS415 Group 11</div>
  <h1 class="hero-title">Smart Hostel<br><span>Management System</span></h1>
  <p class="hero-sub">Report, track and resolve hostel maintenance issues — connecting students, administrators, and maintenance staff in one seamless platform.</p>
  <div class="hero-actions">
    <a href="register.php" class="btn btn-primary btn-lg">Get Started</a>
    <a href="login.php" class="btn btn-gold btn-lg">Login</a>
  </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
  <h2 class="features-title">Everything you need</h2>
  <p class="features-sub">A complete system for every stakeholder in the hostel ecosystem</p>
  <div class="feature-grid">
    <div class="feature-card">
      <div class="feature-icon">🔧</div>
      <div class="feature-title">Report Issues Instantly</div>
      <p class="feature-text">Students can submit maintenance requests with photos in under a minute, from any device.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📊</div>
      <div class="feature-title">Real-Time Tracking</div>
      <p class="feature-text">Track every request from Pending to In Progress to Completed with a clear visual timeline.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⚡</div>
      <div class="feature-title">Fast Resolution</div>
      <p class="feature-text">Admins assign priority and staff instantly. Email alerts keep everyone informed at every step.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">👤</div>
      <div class="feature-title">Role-Based Access</div>
      <p class="feature-text">Students, Admins, and Maintenance Staff each see only what they need — nothing more.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📧</div>
      <div class="feature-title">Email Notifications</div>
      <p class="feature-text">Automatic emails notify students when their request is assigned or status changes.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📱</div>
      <div class="feature-title">Mobile Responsive</div>
      <p class="feature-text">Fully responsive design — works on phones, tablets, and desktops with no separate app needed.</p>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section style="padding:80px 48px;background:var(--bg);">
  <h2 class="features-title" style="margin-bottom:8px;">How it works</h2>
  <p class="features-sub" style="margin-bottom:48px;">Four simple steps from problem to resolution</p>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;max-width:900px;margin:0 auto;">
    <?php foreach ([
      ['1','Student Reports','Fill in the form — title, category, photo, and location.','#8B0000'],
      ['2','Admin Assigns','Admin sets priority and assigns the right staff member.','#D4AF37'],
      ['3','Staff Works','Maintenance staff sees the task and updates status as they work.','#28A745'],
      ['4','Done!','Request marked complete. Student gets an email confirmation.','#0d6efd'],
    ] as [$n,$t,$d,$c]): ?>
    <div style="text-align:center;padding:24px 16px;background:#fff;border-radius:12px;border:1px solid var(--border);">
      <div style="width:48px;height:48px;background:<?=$c?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;margin:0 auto 12px;"><?=$n?></div>
      <div style="font-size:13px;font-weight:700;margin-bottom:6px;"><?=$t?></div>
      <p style="font-size:12px;color:var(--text-muted);"><?=$d?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<footer class="landing-footer">
  <p>© <?=date('Y')?> <strong>Ashesi University</strong> — Smart Hostel Management System</p>
  <p style="margin-top:6px;">CS415 Software Engineering · Group 11 · Class of 2027 Cohort A</p>
  <p style="margin-top:6px;">Instructor: Dr. Umut Tosun &nbsp;|&nbsp; Faculty Interns: Elikem Bansah & Daniel Byiringiro</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
