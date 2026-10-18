/* Wake consent manager. Shows the banner when needed, stores the choice in
   the necessary cookie wake_consent, sends an anonymous proof to /hit.php,
   unlocks <script type="text/plain" data-wake-consent="…"> and two-click
   embeds. "Decline" is exactly as prominent as "Accept". */
(function () {
  var d = document, w = window;
  var cfg = JSON.parse(d.getElementById('wake-consent-config').textContent);
  var t = cfg.texts;

  function read() {
    var m = d.cookie.match(/(?:^|; )wake_consent=([^;]*)/);
    try { return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; }
  }

  function id() {
    var a = new Uint8Array(12), s = ''; w.crypto.getRandomValues(a);
    for (var i = 0; i < a.length; i++) s += ('0' + a[i].toString(16)).slice(-2);
    return s;
  }

  function has(category) { var c = read(); return !!c && c.c.indexOf(category) > -1; }

  function save(categories, action, extraServices) {
    var old = read() || {};
    var choice = { id: old.id || id(), c: ['necessary'].concat(categories), v: cfg.version, t: Date.now(), s: (old.s || []).concat(extraServices || []) };
    d.cookie = 'wake_consent=' + encodeURIComponent(JSON.stringify(choice)) + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    var body = JSON.stringify({ t: 'consent', id: choice.id, c: choice.c, a: action, v: cfg.version, l: d.documentElement.lang || '' });
    if (navigator.sendBeacon) navigator.sendBeacon(cfg.endpoint, new Blob([body], { type: 'application/json' }));
    if (categories.indexOf('statistics') < 0) d.cookie = 'wake_v=; Max-Age=0; Path=/';
    apply();
    w.dispatchEvent(new CustomEvent('wake:consent', { detail: choice }));
  }

  function apply() {
    d.querySelectorAll('script[type="text/plain"][data-wake-consent]').forEach(function (old) {
      if (!has(old.getAttribute('data-wake-consent'))) return;
      var s = d.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) { var a = old.attributes[i]; if (a.name !== 'type') s.setAttribute(a.name, a.value); }
      s.text = old.text; old.parentNode.replaceChild(s, old);
    });
    d.querySelectorAll('[data-wake-embed]').forEach(function (box) {
      var c = read();
      if (has('media') || (c && (c.s || []).indexOf(box.dataset.service) > -1)) load(box);
    });
  }

  function load(box) {
    var f = d.createElement('iframe');
    f.src = box.dataset.src; f.loading = 'lazy'; f.allowFullscreen = true; f.title = box.dataset.title || '';
    f.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
    box.innerHTML = ''; box.appendChild(f); box.removeAttribute('data-wake-embed'); box.classList.add('is-loaded');
  }

  function el(tag, cls, text) { var e = d.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; }

  function banner(detailed) {
    close();
    var box = el('div', 'wake-consent wake-consent--' + cfg.position);
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', t.title); box.id = 'wake-consent';
    box.appendChild(el('strong', 'wake-consent__title', t.title));
    box.appendChild(el('p', 'wake-consent__text', t.text));
    var current = read(), boxes = {};
    if (detailed) {
      var list = el('div', 'wake-consent__list');
      ['necessary'].concat(cfg.categories).forEach(function (c) {
        var row = el('label', 'wake-consent__row'), input = el('input');
        input.type = 'checkbox'; input.checked = c === 'necessary' || (current ? current.c.indexOf(c) > -1 : false); input.disabled = c === 'necessary';
        boxes[c] = input; row.appendChild(input);
        var txt = el('span'); txt.appendChild(el('b', '', t[c])); txt.appendChild(el('small', '', t[c + '_text'])); row.appendChild(txt);
        list.appendChild(row);
      });
      box.appendChild(list);
    }
    var actions = el('div', 'wake-consent__actions');
    var decline = el('button', 'wake-consent__button', t.decline), accept = el('button', 'wake-consent__button', t.accept);
    decline.type = accept.type = 'button';
    decline.onclick = function () { save([], 'decline'); close(); };
    accept.onclick = function () { save(cfg.categories, 'accept'); close(); };
    actions.appendChild(decline); actions.appendChild(accept);
    var more = el('button', 'wake-consent__link', detailed ? t.save : t.settings); more.type = 'button';
    more.onclick = detailed
      ? function () { save(cfg.categories.filter(function (c) { return boxes[c] && boxes[c].checked; }), 'custom'); close(); }
      : function () { banner(true); };
    actions.appendChild(more);
    if (cfg.privacy) { var p = el('a', 'wake-consent__link', t.privacy); p.href = cfg.privacy; actions.appendChild(p); }
    box.appendChild(actions);
    d.body.appendChild(box);
    accept.focus({ preventScroll: true });
  }

  function close() { var b = d.getElementById('wake-consent'); if (b) b.remove(); }

  d.addEventListener('click', function (e) {
    var open = e.target.closest('[data-wake-consent-open]');
    if (open) { e.preventDefault(); banner(true); return; }
    var btn = e.target.closest('[data-wake-embed-load]');
    if (btn) {
      var box = btn.closest('[data-wake-embed]'), always = box.querySelector('[data-wake-embed-always]');
      if (always && always.checked) save((read() || { c: [] }).c.filter(function (c) { return c !== 'necessary'; }), 'custom', [box.dataset.service]);
      load(box);
    }
  });

  var c = read();
  if (cfg.banner && (!c || c.v < cfg.version)) {
    if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { banner(false); }); else banner(false);
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', apply); else apply();
  w.wakeConsent = { open: function () { banner(true); }, has: has };
})();
