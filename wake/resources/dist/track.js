/* Wake statistics. Sends page views, reading time, scroll depth and events
   to /hit.php. No cookies unless the visitor agreed to statistics (level 2).
   API: wake('event', 'Name', 'optional label') */
(function () {
  var d = document, w = window, endpoint = d.currentScript.dataset.endpoint;
  var start = Date.now(), active = 0, visibleSince = d.visibilityState === 'visible' ? start : 0, scroll = 0, sent = false;

  function consent() {
    var m = d.cookie.match(/(?:^|; )wake_consent=([^;]*)/);
    try { return m ? JSON.parse(decodeURIComponent(m[1])).c || [] : []; } catch (e) { return []; }
  }

  function level() { return consent().indexOf('statistics') > -1 ? 2 : (consent().length ? 1 : 0); }

  function visitorCookie() {
    if (level() < 2) return 0;
    if (/(?:^|; )wake_v=/.test(d.cookie)) return 0;
    var id = ''; var a = new Uint8Array(16); (w.crypto || w.msCrypto).getRandomValues(a);
    for (var i = 0; i < a.length; i++) id += ('0' + a[i].toString(16)).slice(-2);
    d.cookie = 'wake_v=' + id + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    return 1;
  }

  function send(data) {
    data.p = location.pathname + location.search;
    data.l = d.documentElement.lang || '';
    data.c = level();
    var body = JSON.stringify(data);
    if (navigator.sendBeacon) navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
    else fetch(endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' });
  }

  function measureScroll() {
    var h = d.documentElement, max = h.scrollHeight - h.clientHeight;
    scroll = Math.max(scroll, max > 0 ? Math.round(100 * (h.scrollTop || d.body.scrollTop) / max) : 100);
  }

  function leave() {
    if (sent) return;
    if (visibleSince) { active += Date.now() - visibleSince; visibleSince = 0; }
    measureScroll(); sent = true;
    send({ t: 'leave', d: Math.round(active / 1000), s: scroll });
  }

  w.wake = function (kind, name, label) { if (kind === 'event' && name) send({ t: 'event', e: String(name), x: label ? String(label) : '' }); };

  d.addEventListener('visibilitychange', function () {
    if (d.visibilityState === 'hidden') leave();
    else { visibleSince = Date.now(); sent = false; }
  });
  w.addEventListener('pagehide', leave);
  w.addEventListener('scroll', measureScroll, { passive: true });

  d.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    if (a.host && a.host !== location.host) w.wake('event', 'Outbound link', a.host);
    else if (/\.(pdf|zip|docx?|xlsx?|pptx?|mp3|mp4|epub)$/i.test(a.pathname)) w.wake('event', 'Download', a.pathname.split('/').pop());
  });
  d.addEventListener('submit', function (e) { w.wake('event', 'Form submitted', e.target.getAttribute('name') || e.target.id || ''); });

  w.addEventListener('wake:consent', function () { visitorCookie(); });

  send({ t: 'pageview', r: d.referrer, n: visitorCookie() });
})();
