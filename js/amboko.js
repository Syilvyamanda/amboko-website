/* AMBOKO - skrip bersama */
(function () {
  // Menu akun
  var btn = document.getElementById('user-menu-btn');
  var panel = document.getElementById('user-menu-panel');
  if (btn && panel) {
    btn.addEventListener('click', function (e) { e.stopPropagation(); panel.classList.toggle('hidden'); });
    document.addEventListener('click', function (e) { if (!panel.contains(e.target)) panel.classList.add('hidden'); });
  }

  // Tombol × pada kotak pencarian
  document.querySelectorAll('.search-form').forEach(function (form) {
    var clear = form.querySelector('.search-clear');
    var input = form.querySelector('input[name=q]');
    if (!clear || !input) return;
    clear.addEventListener('click', function () {
      var had = input.value !== '';
      input.value = '';
      input.focus();
      if (had && location.pathname.indexOf('katalog.php') !== -1) form.submit();
    });
  });

  // Toast otomatis hilang
  var toasts = document.querySelectorAll('.toast');
  toasts.forEach(function (t) {
    setTimeout(function () { t.classList.add('is-hiding'); setTimeout(function () { t.remove(); }, 350); }, 3800);
  });

  // Wishlist (hati) – dipakai di katalog & kartu "Complete the look"
  document.querySelectorAll('[data-wish]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      ev.preventDefault(); ev.stopPropagation();
      var id = el.getAttribute('data-wish');
      var body = new URLSearchParams({ product_id: id, csrf: el.getAttribute('data-csrf') || '' });
      fetch('wishlist.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.login) { location.href = 'login.php?next=' + encodeURIComponent(location.pathname.split('/').pop() + location.search); return; }
          if (j.active) { el.classList.remove('text-white'); el.classList.add('text-red-500'); }
          else { el.classList.remove('text-red-500'); el.classList.add('text-white'); }
        });
    });
  });
  // Magazine carousel (Home)
  var magTrack = document.getElementById('magTrack');
  if (magTrack) {
    var step = function () { var f = magTrack.querySelector('figure'); return f ? f.offsetWidth + 16 : 300; };
    var prev = document.getElementById('magPrev'), next = document.getElementById('magNext');
    if (prev) prev.addEventListener('click', function () { magTrack.scrollBy({ left: -step(), behavior: 'smooth' }); });
    if (next) next.addEventListener('click', function () { magTrack.scrollBy({ left: step(), behavior: 'smooth' }); });
  }
})();
