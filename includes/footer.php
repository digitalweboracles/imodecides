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
<div class="cookie" id="cookieBar" hidden>
  <h4>We respect your privacy</h4>
  <p>Cookies help us improve your experience, deliver personalized content, and analyze traffic. You can choose which cookies to allow by clicking <b>Customize</b>. Click <b>Accept All</b> to consent or <b>Reject All</b> to decline non-essential cookies.</p>
  <div class="cookie-opts" id="cookieOpts" hidden>
    <label class="check"><input type="checkbox" checked disabled> Necessary Cookies <em>Always Active</em></label>
    <label class="check"><input type="checkbox" value="functional"> Functional Cookies</label>
    <label class="check"><input type="checkbox" value="analytics"> Analytical Cookies</label>
    <label class="check"><input type="checkbox" value="ads"> Advertisement Cookies</label>
  </div>
  <div class="cookie-btns">
    <button type="button" data-cookie="customize">Customize</button>
    <button type="button" data-cookie="reject">Reject All</button>
    <button type="button" data-cookie="accept" class="pri">Accept All</button>
  </div>
</div>
<a href="#" class="go-up" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">&#8593;</a>
<script src="<?= url('assets/js/app.js') ?>?v=11"></script>
</body>
</html>
