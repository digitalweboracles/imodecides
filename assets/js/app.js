// Copy link
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.getAttribute('data-copy');
    (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).catch(function () {
      var i = document.createElement('input'); i.value = t; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove();
    });
    var o = b.textContent; b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = o; }, 1600);
  });
});

// Reply to an answer
var pid = document.getElementById('parentId'), rt = document.getElementById('replyingTo');
document.querySelectorAll('.c-reply').forEach(function (b) {
  b.addEventListener('click', function () {
    pid.value = b.dataset.id; rt.hidden = false; rt.querySelector('b').textContent = b.dataset.name;
    document.getElementById('respond').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.querySelector('#respond textarea').focus();
  });
});
var cr = document.getElementById('cancelReply');
if (cr) cr.addEventListener('click', function (e) { e.preventDefault(); pid.value = ''; rt.hidden = true; });

// Animate result bars on load
document.querySelectorAll('.bar span').forEach(function (s) {
  var w = s.style.width; s.style.width = '0'; requestAnimationFrame(function () { setTimeout(function () { s.style.width = w; }, 60); });
});

// Prevent double vote submit
var vf = document.getElementById('voteForm');
if (vf) vf.addEventListener('submit', function () { var b = vf.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Submitting…'; } });
