// Vote / Results screens (like the original poll widget)
document.querySelectorAll('[data-show]').forEach(function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    var v = document.getElementById('voteForm'), r = document.getElementById('pollResults');
    var showResults = a.dataset.show === 'results';
    if (v) v.hidden = showResults;
    if (r) {
      r.hidden = !showResults;
      if (showResults) r.querySelectorAll('.dem-fill').forEach(function (f) { var w = f.style.width; f.style.width = '0'; setTimeout(function () { f.style.width = w; }, 30); });
    }
  });
});

// "Leave an answer" opens the form
var form = document.getElementById('respond');
document.querySelectorAll('[data-toggle-form]').forEach(function (b) {
  b.addEventListener('click', function (e) {
    e.preventDefault(); form.hidden = false;
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form.querySelector('input[name=name]').focus();
  });
});

// Reply to an answer
var pid = document.getElementById('parentId'), rt = document.getElementById('replyingTo');
document.querySelectorAll('.c-reply').forEach(function (b) {
  b.addEventListener('click', function (e) {
    e.preventDefault(); form.hidden = false;
    pid.value = b.dataset.id; rt.hidden = false; rt.querySelector('b').textContent = b.dataset.name;
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form.querySelector('textarea').focus();
  });
});
var cr = document.getElementById('cancelReply');
if (cr) cr.addEventListener('click', function (e) { e.preventDefault(); pid.value = ''; rt.hidden = true; });
if (form && location.hash === '#respond') form.hidden = false;

// Prevent double vote submit
var vf = document.getElementById('voteForm');
if (vf) vf.addEventListener('submit', function () { var b = vf.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Voting…'; } });

// ---- Q&A actions: like/dislike, follow, favourite ----
function imoToast(msg) { var t = document.createElement('div'); t.className = 'toast'; t.textContent = msg; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 2600); }
function imoAct(data) {
  var fd = new FormData(); fd.append('csrf', IMO.csrf); fd.append('back', IMO.back);
  Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
  return fetch(IMO.act, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (j) { if (j.login) { location.href = j.login; throw 0; } if (j.error) { imoToast(j.error); throw 0; } return j; });
}
if (window.IMO) {
  document.querySelectorAll('.vote-box button').forEach(function (b) {
    b.addEventListener('click', function () {
      var box = b.closest('.vote-box');
      imoAct({ do: b.dataset.do, type: box.dataset.type, id: box.dataset.id }).then(function (j) {
        box.querySelector('.vb-score').textContent = j.score;
        box.querySelector('.vb-up').classList.toggle('on', j.mine === 1);
        box.querySelector('.vb-down').classList.toggle('on', j.mine === -1);
      }).catch(function () {});
    });
  });
  document.querySelectorAll('.fol-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      imoAct({ do: b.dataset.do, id: b.dataset.id }).then(function (j) {
        b.classList.toggle('on', j.on); b.querySelector('.fol-n').textContent = j.count;
        imoToast(b.dataset.do === 'follow' ? (j.on ? 'You are now following this question.' : 'You unfollowed this question.') : (j.on ? 'Added to your favorites.' : 'Removed from your favorites.'));
      }).catch(function () {});
    });
  });
  // report modal
  var rm = document.getElementById('reportModal');
  document.querySelectorAll('.rep-open').forEach(function (b) {
    b.addEventListener('click', function () {
      rm.querySelector('[name=type]').value = b.dataset.type; rm.querySelector('[name=id]').value = b.dataset.id;
      document.getElementById('repLabel').textContent = 'Please briefly explain why you feel this ' + (b.dataset.type === 'comment' ? 'answer' : 'question') + ' should be reported.';
      rm.hidden = false; rm.querySelector('textarea').focus();
    });
  });
  if (rm) {
    rm.addEventListener('click', function (e) { if (e.target === rm || e.target.hasAttribute('data-close')) rm.hidden = true; });
    rm.querySelector('form').addEventListener('submit', function (e) {
      e.preventDefault(); var f = e.target;
      imoAct({ do: 'report', type: f.elements['type'].value, id: f.elements['id'].value, reason: f.elements['reason'].value }).then(function (j) { rm.hidden = true; f.elements['reason'].value = ''; imoToast(j.msg); }).catch(function () {});
    });
  }
}
document.addEventListener('click', function (e) { document.querySelectorAll('.umenu.open').forEach(function (m) { if (!m.contains(e.target)) m.classList.remove('open'); }); });

// ---- cookie consent (like the original site) ----
(function () {
  var bar = document.getElementById('cookieBar'); if (!bar) return;
  var saved = null; try { saved = localStorage.getItem('imo_cookie_consent'); } catch (e) {}
  if (!saved) bar.hidden = false;
  function save(v) { try { localStorage.setItem('imo_cookie_consent', JSON.stringify(v)); } catch (e) {} bar.hidden = true; }
  bar.querySelectorAll('[data-cookie]').forEach(function (b) {
    b.addEventListener('click', function () {
      var a = b.dataset.cookie, opts = document.getElementById('cookieOpts');
      if (a === 'accept') save({ functional: 1, analytics: 1, ads: 1 });
      else if (a === 'reject') save({ functional: 0, analytics: 0, ads: 0 });
      else if (opts.hidden) { opts.hidden = false; b.textContent = 'Save My Preferences'; }
      else { var v = {}; opts.querySelectorAll('input[value]').forEach(function (i) { v[i.value] = i.checked ? 1 : 0; }); save(v); }
    });
  });
})();
