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
