  </main>
  <aside class="rside">
    <?php if (!empty($sidebar)) echo $sidebar; ?>
    <div class="widget">
      <h3 class="widget-t">Explore</h3>
      <ul class="widget-l">
        <li><a href="<?= url('') ?>">Home</a></li>
        <?php foreach ($navPositions as $np): ?><li><a href="<?= url($np['slug'] . '/') ?>"><?= e($np['title']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
  </aside>
</div>
<footer class="ftr">
  <div class="ftr-in">
    <span>&copy; Imo Decides</span>
  </div>
</footer>
<a href="#" class="go-up" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">&#8593;</a>
<script src="<?= url('assets/js/app.js') ?>?v=10"></script>
</body>
</html>
