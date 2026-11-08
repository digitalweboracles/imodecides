<?php
require_once __DIR__ . '/includes/functions.php';
$p = $_GET['p'] ?? '';
$pages = [
    'terms' => ['Terms of Service', [
        'Imo Decides is a public opinion platform where people explore candidates for the 2027 elections in Imo State, take part in online polls and share their views.',
        'Poll results reflect the choices of visitors to this website. They are not official election results and are not conducted or endorsed by INEC or any political party.',
        'Vote once per poll. Attempts to inflate votes (multiple devices, scripts or fake accounts) may lead to votes being removed and accounts suspended.',
        'Questions and answers must be respectful. Hate speech, threats, personal attacks, false claims about individuals, spam and illegal content will be removed.',
        'You are responsible for what you post. By posting, you allow Imo Decides to display your question or answer on the website.',
        'We may update these terms. Continued use of the website means you accept the current terms.',
    ]],
    'privacy-policy' => ['Privacy Policy', [
        'When you create an account we store your username, email address and a securely hashed password. We never display your email address publicly.',
        'To keep polls fair we store an anonymous voting cookie and a one-way hash of your network address. We do not store your raw IP address with your vote.',
        'Questions, answers and the name you post under are public.',
        'Necessary cookies keep you signed in and remember your cookie choices. You can accept or reject other cookies using the cookie banner.',
        'We do not sell your personal information. You can ask us to delete your account at any time.',
    ]],
];
if (!isset($pages[$p])) { http_response_code(404); $p = null; }
$pageTitle = ($p ? $pages[$p][0] : 'Page not found') . ' – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> <?= e($p ? $pages[$p][0] : '404') ?></div>
  <div class="page-box">
    <?php if (!$p): ?><div class="empty"><h1 class="big">404</h1><p>Oops! Page Not Found.</p></div>
    <?php else: ?>
      <h1 class="page-h"><?= e($pages[$p][0]) ?></h1>
      <?php foreach ($pages[$p][1] as $para): ?><p style="font-size:14px"><?= e($para) ?></p><?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
