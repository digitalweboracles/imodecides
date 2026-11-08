<?php
require_once __DIR__ . '/includes/functions.php';
$positions = db()->query('SELECT * FROM positions WHERE hidden = 0 ORDER BY sort')->fetchAll();
$pageTitle = SITE_NAME . ' – ' . SITE_TAGLINE;
$ogTitle = SITE_NAME;
$hero = media_url('og.jpg');
ob_start(); ?>
<section class="hero" <?php if ($hero): ?>style="--hero:url('<?= e($hero) ?>')"<?php endif; ?>>
  <div class="hero-in">
    <div class="hero-txt">
      <h2>Share &amp; grow the world's knowledge!</h2>
      <p>We want to connect the people who have knowledge to the people who need it, to bring together people with different perspectives so they can understand each other better, and to empower everyone to share their knowledge.</p>
    </div>
    <?php if (!current_user()): ?><a class="btn-green" href="<?= url('signup/') ?>">Create A New Account</a><?php else: ?><a class="btn-green" href="<?= url('add-question/') ?>">Ask A Question</a><?php endif; ?>
  </div>
</section>
<?php $heroHtml = ob_get_clean();
require __DIR__ . '/includes/header.php';
?>


<div class="box page-box">
  <div class="home-intro">
    <h2 class="kicker">IMO DECIDES 2027</h2>
    <h1 class="big">Who Should Lead in Imo?</h1>
    <p>Explore the candidates, compare your options and participate in Imo State’s public opinion polls.</p>
    <h1 class="big">Choose a Position</h1>
    <p>Select an election position below to explore the candidates and participate in the poll.</p>
  </div>
  <div class="wpcols">
    <?php foreach ($positions as $p): ?>
      <div class="gcard">
        <h2><?= e($p['title']) ?></h2>
        <p><?= e($p['tagline']) ?></p>
        <a class="gbtn" href="<?= position_url($p) ?>">Explore Candidates</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php $shareUrl = abs_url(''); $shareTxt = 'IMO DECIDES 2027'; include __DIR__ . '/includes/share.php'; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
