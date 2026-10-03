<?php
// about.php  (REPLACES the old About page) -> /about.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/site_info.php';
if (!defined('DEVELOPER_ROLE')) define('DEVELOPER_ROLE', 'Developer');

function ab($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$logged = isset($_SESSION['user_id']);
$dash = (($_SESSION['role'] ?? '') === 'admin') ? '/admin/dashboard.php' : '/student/dashboard.php';

// Simple icons (inline SVG, nothing to download)
$icons = [
    'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'usercheck'=> '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>',
    'key'      => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
    'shuffle'  => '<polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/>',
    'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    'award'    => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
    'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
];
function ab_icon($icons, $name, $size = 26) {
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

$features = [
    ['shield',    'Separate admin and student areas', 'Admins manage everything. Students only see their own exams and results.'],
    ['clock',     'Timed exams',                      'A live countdown runs during the exam and answers are submitted automatically when time is up.'],
    ['calendar',  'Exam scheduling',                  'Set the day, date and time. Students who arrive after the start are marked late.'],
    ['usercheck', 'Exam registration',                'Students register for a particular exam first. Only registered students can take it.'],
    ['key',       'Access codes',                     'Give out a code that students must enter before they can start.'],
    ['shuffle',   'Fair exams',                       'Questions and answer options can be shuffled so every student sees a different order.'],
    ['chart',     'Instant results',                  'Every question is marked passed or failed, with the correct answer shown afterwards.'],
    ['download',  'Downloads',                        'Results can be downloaded as PDF or Word, and the admin can export to Excel.'],
    ['award',     'Certificates',                     'Students who pass get a certificate with an ID that anyone can verify online.'],
    ['bell',      'Announcements and email',          'Post announcements and email students about new exams.'],
];

$student_steps = [
    'Create an account and log in.',
    'Register for the exam you want to take.',
    'Start on time, and enter the access code if you were given one.',
    'Answer the questions before the timer ends.',
    'See your score, what you passed or failed, and download your result or certificate.',
];
$admin_steps = [
    'Create an exam and add its questions.',
    'Set the date, time, duration, pass mark and rules.',
    'Open the exam, then email or announce it to students.',
    'See who registered, who was late and who passed.',
    'Mark short answers by hand if needed and export the results.',
];

$secure = [
    ['lock',   'Passwords are protected',   'Passwords are stored in scrambled (hashed) form, never as plain text.'],
    ['clock',  'The server keeps the time', 'Start times and late entry are decided by the server, so changing the computer clock does not help.'],
    ['key',    'Private exams',             'Registration and access codes keep an exam limited to the right students.'],
    ['award',  'Certificates you can check','Each certificate carries a code that anyone can verify on this site.'],
];

$faq = [
    ['How do I take an exam?', 'Log in, find the exam on your dashboard and click "Register for this exam". Once you are registered, press Start when the exam opens. If it has an access code, your teacher will give it to you.'],
    ['What happens if I arrive late?', 'If the admin allows late entry, you can still start, but you are marked late and may have less time. If late entry is closed, the exam can no longer be started.'],
    ['Can I take an exam more than once?', 'That depends on the exam. The admin sets how many attempts each student gets, and your dashboard shows how many you have used.'],
    ['How do I get my certificate?', 'Pass the exam, open your result and click "Certificate". Then choose "Save as PDF" in the print window.'],
    ['I forgot my password. What now?', 'Click "Forgot password?" on the login page and follow the link. You can also change your password any time from "My Profile and Password".'],
    ['How can someone check a certificate is real?', 'Every certificate shows an ID and a check link. Opening that link on this site shows the name, exam, score and date if the certificate is genuine.'],
];

// initials for the avatar
$words = preg_split('/\s+/', trim(DEVELOPER_NAME));
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $initials .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($w, 0, 1));
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<style>
.ab-wrap { color:#0f172a; padding-bottom:30px; }
.ab-wrap * { box-sizing:border-box; }

/* hero */
.ab-hero { position:relative; overflow:hidden; border-radius:26px; padding:56px 48px; color:#fff;
    background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 55%,#2563eb 100%); box-shadow:0 24px 60px rgba(30,58,138,.35); }
.ab-hero::before { content:""; position:absolute; width:380px; height:380px; right:-120px; top:-140px; border-radius:50%; background:rgba(96,165,250,.25); }
.ab-hero::after  { content:""; position:absolute; width:260px; height:260px; left:-90px; bottom:-120px; border-radius:50%; background:rgba(255,255,255,.07); }
.ab-hero > * { position:relative; z-index:1; }
.ab-pill { display:inline-flex; align-items:center; gap:8px; padding:6px 14px; border-radius:999px; font-size:.85rem;
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.28); }
.ab-hero h1 { font-weight:800; letter-spacing:-.5px; font-size:clamp(2.1rem,4.6vw,3.3rem); margin:18px 0 12px; }
.ab-hero .ab-lead { color:rgba(255,255,255,.86); font-size:1.12rem; max-width:520px; }
.ab-btn { display:inline-block; padding:12px 26px; border-radius:12px; font-weight:600; text-decoration:none; transition:.2s; }
.ab-btn-light { background:#fff; color:#1e3a8a; }
.ab-btn-light:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(0,0,0,.25); color:#1e3a8a; }
.ab-btn-ghost { border:1.5px solid rgba(255,255,255,.7); color:#fff; }
.ab-btn-ghost:hover { background:rgba(255,255,255,.14); color:#fff; }
.ab-hero img.ab-art { width:100%; max-width:470px; filter:drop-shadow(0 20px 30px rgba(0,0,0,.25)); }

/* titles */
.ab-title { text-align:center; font-weight:800; letter-spacing:-.3px; margin:64px 0 8px; font-size:clamp(1.5rem,3vw,2rem); }
.ab-sub { text-align:center; color:#64748b; margin:0 auto 30px; max-width:560px; }

/* feature cards */
.ab-card { height:100%; background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding:26px; transition:.25s; }
.ab-card:hover { transform:translateY(-6px); box-shadow:0 18px 40px rgba(15,23,42,.12); border-color:#bfdbfe; }
.ab-card h5 { font-weight:700; font-size:1.05rem; margin:0 0 6px; }
.ab-card p { color:#64748b; margin:0; font-size:.95rem; }
.ab-ico { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; margin-bottom:16px; }
.ab-c0 { background:linear-gradient(135deg,#60a5fa,#2563eb); }
.ab-c1 { background:linear-gradient(135deg,#34d399,#059669); }
.ab-c2 { background:linear-gradient(135deg,#fbbf24,#d97706); }
.ab-c3 { background:linear-gradient(135deg,#a78bfa,#6d28d9); }
.ab-c4 { background:linear-gradient(135deg,#f472b6,#be185d); }
.ab-c5 { background:linear-gradient(135deg,#2dd4bf,#0f766e); }
.ab-c6 { background:linear-gradient(135deg,#818cf8,#3730a3); }
.ab-c7 { background:linear-gradient(135deg,#fb7185,#be123c); }
.ab-c8 { background:linear-gradient(135deg,#facc15,#a16207); }
.ab-c9 { background:linear-gradient(135deg,#22d3ee,#0e7490); }

/* steps */
.ab-panel { height:100%; background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:28px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
.ab-panel h4 { font-weight:800; margin:0 0 20px; display:flex; align-items:center; gap:10px; }
.ab-steps { list-style:none; margin:0; padding:0; counter-reset:step; }
.ab-steps li { position:relative; padding:4px 0 24px 54px; counter-increment:step; }
.ab-steps li::before { content:counter(step); position:absolute; left:0; top:0; width:38px; height:38px; border-radius:50%;
    display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; background:linear-gradient(135deg,#3b82f6,#1e3a8a); }
.ab-steps.dark li::before { background:linear-gradient(135deg,#475569,#0f172a); }
.ab-steps li::after { content:""; position:absolute; left:18px; top:42px; bottom:4px; width:2px; background:#dbeafe; }
.ab-steps li:last-child { padding-bottom:0; }
.ab-steps li:last-child::after { display:none; }

/* security band */
.ab-band { margin-top:64px; border-radius:26px; padding:44px 34px; color:#fff; background:linear-gradient(135deg,#0f172a,#1e293b); }
.ab-band h3 { font-weight:800; text-align:center; margin-bottom:6px; }
.ab-band .ab-bsub { text-align:center; color:#94a3b8; margin-bottom:28px; }
.ab-sec { display:flex; gap:14px; align-items:flex-start; }
.ab-sec .ab-sico { flex:0 0 46px; height:46px; border-radius:12px; background:rgba(96,165,250,.18); color:#93c5fd; display:flex; align-items:center; justify-content:center; }
.ab-sec h6 { margin:0 0 4px; font-weight:700; }
.ab-sec p { margin:0; color:#94a3b8; font-size:.93rem; }

/* faq */
.ab-faq details { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:12px; overflow:hidden; transition:.2s; }
.ab-faq details[open] { border-color:#93c5fd; box-shadow:0 8px 22px rgba(37,99,235,.1); }
.ab-faq summary { cursor:pointer; list-style:none; padding:16px 54px 16px 20px; font-weight:600; position:relative; }
.ab-faq summary::-webkit-details-marker { display:none; }
.ab-faq summary::after { content:"+"; position:absolute; right:20px; top:50%; transform:translateY(-50%); font-size:1.5rem; color:#2563eb; font-weight:400; }
.ab-faq details[open] summary::after { content:"\2212"; }
.ab-faq details p { margin:0; padding:0 20px 18px; color:#64748b; }

/* call to action + developer */
.ab-cta { margin-top:64px; border-radius:26px; padding:46px 30px; text-align:center; color:#fff;
    background:linear-gradient(135deg,#2563eb,#1e3a8a); box-shadow:0 20px 50px rgba(37,99,235,.3); }
.ab-cta h3 { font-weight:800; }
.ab-dev { margin-top:30px; background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:26px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; justify-content:center; text-align:left; }
.ab-avatar { width:76px; height:76px; border-radius:50%; background:linear-gradient(135deg,#3b82f6,#1e3a8a); color:#fff; font-weight:800; font-size:1.7rem; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(37,99,235,.35); }

/* reveal on scroll */
.ab-js .ab-reveal { opacity:0; transform:translateY(22px); transition:opacity .6s ease, transform .6s ease; }
.ab-js .ab-reveal.show { opacity:1; transform:none; }
@media (prefers-reduced-motion: reduce) { .ab-js .ab-reveal { opacity:1; transform:none; transition:none; } }
@media (max-width:767px) { .ab-hero { padding:36px 22px 56px; } }
</style>

<div class="ab-wrap" id="abwrap">

    <!-- hero -->
    <section class="ab-hero">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="ab-pill"><img src="/public/img/logo.svg" width="22" height="22" alt=""> <?php echo ab(SITE_TAGLINE); ?></span>
                <h1>Exams made simple, fair and professional</h1>
                <p class="ab-lead"><?php echo ab(SITE_DESCRIPTION); ?></p>
                <div class="d-flex gap-2 flex-wrap mt-4">
                    <?php if ($logged): ?>
                        <a href="<?php echo $dash; ?>" class="ab-btn ab-btn-light">Go to my dashboard</a>
                    <?php else: ?>
                        <a href="/auth/login.php" class="ab-btn ab-btn-light">Student login</a>
                        <a href="/auth/register.php" class="ab-btn ab-btn-ghost">Create an account</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img class="ab-art" src="/public/img/hero.svg" alt="Illustration of an online exam with a timer, a passed badge and a certificate">
            </div>
        </div>
    </section>

    <!-- features -->
    <h2 class="ab-title ab-reveal">Everything you need to run an exam</h2>
    <p class="ab-sub ab-reveal">From the first question to the final certificate, in one place.</p>
    <div class="row g-4">
        <?php foreach ($features as $i => $f): ?>
            <div class="col-md-6 col-lg-4 ab-reveal">
                <div class="ab-card">
                    <div class="ab-ico ab-c<?php echo $i % 10; ?>"><?php echo ab_icon($icons, $f[0]); ?></div>
                    <h5><?php echo ab($f[1]); ?></h5>
                    <p><?php echo ab($f[2]); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- how it works -->
    <h2 class="ab-title ab-reveal">How it works</h2>
    <p class="ab-sub ab-reveal">Simple steps for students and for administrators.</p>
    <div class="row g-4">
        <div class="col-lg-6 ab-reveal">
            <div class="ab-panel">
                <h4><span class="ab-ico ab-c0 mb-0" style="width:42px;height:42px;"><?php echo ab_icon($icons, 'usercheck', 22); ?></span> For students</h4>
                <ol class="ab-steps">
                    <?php foreach ($student_steps as $s): ?><li><?php echo ab($s); ?></li><?php endforeach; ?>
                </ol>
            </div>
        </div>
        <div class="col-lg-6 ab-reveal">
            <div class="ab-panel">
                <h4><span class="ab-ico ab-c6 mb-0" style="width:42px;height:42px;"><?php echo ab_icon($icons, 'shield', 22); ?></span> For administrators</h4>
                <ol class="ab-steps dark">
                    <?php foreach ($admin_steps as $s): ?><li><?php echo ab($s); ?></li><?php endforeach; ?>
                </ol>
            </div>
        </div>
    </div>

    <!-- security -->
    <section class="ab-band ab-reveal">
        <h3>Built with trust in mind</h3>
        <p class="ab-bsub">Features that keep exams fair and results reliable.</p>
        <div class="row g-4">
            <?php foreach ($secure as $s): ?>
                <div class="col-md-6">
                    <div class="ab-sec">
                        <div class="ab-sico"><?php echo ab_icon($icons, $s[0], 22); ?></div>
                        <div><h6><?php echo ab($s[1]); ?></h6><p><?php echo ab($s[2]); ?></p></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- faq -->
    <h2 class="ab-title ab-reveal">Frequently asked questions</h2>
    <p class="ab-sub ab-reveal">Quick answers to common questions.</p>
    <div class="row justify-content-center">
        <div class="col-lg-9 ab-faq ab-reveal">
            <?php foreach ($faq as $q): ?>
                <details>
                    <summary><?php echo ab($q[0]); ?></summary>
                    <p><?php echo ab($q[1]); ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- call to action -->
    <section class="ab-cta ab-reveal">
        <h3>Ready to get started?</h3>
        <p class="mb-4" style="color:rgba(255,255,255,.85)">Join in a minute and take your next exam with confidence.</p>
        <div class="d-flex gap-2 flex-wrap justify-content-center">
            <?php if ($logged): ?>
                <a href="<?php echo $dash; ?>" class="ab-btn ab-btn-light">Go to my dashboard</a>
            <?php else: ?>
                <a href="/auth/register.php" class="ab-btn ab-btn-light">Create an account</a>
                <a href="/auth/login.php" class="ab-btn ab-btn-ghost">Student login</a>
                <a href="/auth/admin_login.php" class="ab-btn ab-btn-ghost">Admin login</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- developer -->
    <section class="ab-dev ab-reveal">
        <div class="ab-avatar"><?php echo ab($initials); ?></div>
        <div>
            <div class="text-muted small text-uppercase" style="letter-spacing:1px">About the developer</div>
            <h4 class="mb-0 fw-bold"><?php echo ab(DEVELOPER_NAME); ?></h4>
            <div class="text-muted"><?php echo ab(DEVELOPER_ROLE); ?> of <?php echo ab(SITE_NAME); ?></div>
        </div>
        <a href="mailto:<?php echo ab(CONTACT_EMAIL); ?>" class="ab-btn ab-btn-light" style="background:#eff6ff;">Contact: <?php echo ab(CONTACT_EMAIL); ?></a>
    </section>
</div>

<script>
(function () {
    var wrap = document.getElementById('abwrap');
    wrap.classList.add('ab-js');

    // fade-in when scrolling
    var items = document.querySelectorAll('.ab-reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('show'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12 });
        items.forEach(function (el) { io.observe(el); });
    } else {
        items.forEach(function (el) { el.classList.add('show'); });
    }

})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
