/* Store Notice Plus – smooth slide-left with dual buffers */
(function () {
  if (!window.SNP_DATA) return;

  var banner = document.getElementById('snp-banner');
  if (!banner) return;

  // --- Optional relocation into header ---
  if (SNP_DATA && SNP_DATA.renderHook === 'header') {
    var selector = (SNP_DATA.headerSelector || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
    var target = null;

    for (var i = 0; i < selector.length; i++) {
      var t = document.querySelector(selector[i]);
      if (t) { target = t; break; }
    }

    if (target) {
      // Prepend inside header so it appears at the very top of the header block
      if (target.firstChild) {
        target.insertBefore(banner, target.firstChild);
      } else {
        target.appendChild(banner);
      }
    } // else: no match → gracefully keep it at body open
  }


  var closeBtn = banner.querySelector('.snp-close');
  var wrap = banner.querySelector('.snp-messages');
  if (!wrap) return;

  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var intervalSec = parseInt(SNP_DATA.interval, 10) || 6;
  var dismissDays = parseInt(SNP_DATA.dismissDays, 10) || 7;

  // Pull messages from server-rendered spans
  var pool = [].slice.call(banner.querySelectorAll('.snp-message'))
    .map(function (el) { return el.innerHTML; })
    .filter(Boolean);
  if (!pool.length) return;

  // Build two layers
  wrap.innerHTML = '<span class="snp-layer snp-a is-active" aria-hidden="false"></span>' +
                   '<span class="snp-layer snp-b enter-from-right" aria-hidden="true"></span>';
  var a = wrap.querySelector('.snp-a');
  var b = wrap.querySelector('.snp-b');
  var active = a, next = b;

  var idx = 0;
  active.innerHTML = pool[idx];

  function setHeightTo(el) { wrap.style.height = el.offsetHeight + 'px'; }
  setHeightTo(active);

  function setAria(activeEl, nextEl) {
    activeEl.setAttribute('aria-hidden', 'false');
    nextEl.setAttribute('aria-hidden', 'true');
  }
  setAria(active, next);

  function slideTo(nextIndex) {
    if (pool.length <= 1) return;

    // Prepare next message off-screen to the right
    next.innerHTML = pool[nextIndex];
    next.className = 'snp-layer enter-from-right';    // reset & place right

    // Animate container height to the next message height
    setHeightTo(next);

    if (reducedMotion) {
      // Instant swap
      active.className = 'snp-layer';
      next.className   = 'snp-layer is-active';
      var tmp = active; active = next; next = tmp;
      setAria(active, next);
      next.innerHTML = '';
      return;
    }

    // Force layout so transforms will transition
    void next.offsetWidth;

    // Start slide: next slides in from right, active exits to left
    next.classList.remove('enter-from-right');
    next.classList.add('is-active');
    active.classList.add('exit-to-left');

    var done = false;
    function onEnd(e) {
      if (done) return;
      if (e.propertyName !== 'transform') return;
      done = true;

      // Swap roles
      active.className = 'snp-layer';     // now off-screen buffer
      next.className   = 'snp-layer is-active';

      // Lock height to the new active content
      setHeightTo(next);

      // Clear old buffer content to avoid tab/selection issues
      active.innerHTML = '';

      // Swap refs
      var tmp = active; active = next; next = tmp;
      setAria(active, next);

      next.removeEventListener('transitionend', onEnd);
      active.removeEventListener('transitionend', onEnd);
    }

    next.addEventListener('transitionend', onEnd);
    active.addEventListener('transitionend', onEnd);
  }

  // Rotation timer
  var timer = null;
  function startRotation() {
    if (reducedMotion || pool.length <= 1) return;
    stopRotation();
    timer = setInterval(function () {
      idx = (idx + 1) % pool.length;
      slideTo(idx);
    }, intervalSec * 1000);
  }
  function stopRotation() { if (timer) clearInterval(timer); timer = null; }

  // Pause on hover/focus (a11y)
  banner.addEventListener('mouseenter', stopRotation);
  banner.addEventListener('mouseleave', startRotation);
  banner.addEventListener('focusin', stopRotation);
  banner.addEventListener('focusout', startRotation);

  // Keep height in sync on resize
  window.addEventListener('resize', function () { setHeightTo(active); });

  startRotation();

  // Dismiss handling
  function setDismissCookie(days) {
    var seconds = Math.max(1, days) * 86400;
    var cookie = 'snp_dismissed=1; Max-Age=' + seconds + '; Path=/; SameSite=Lax';
    if (location.protocol === 'https:') cookie += '; Secure';
    document.cookie = cookie;
    try { localStorage.setItem('snp_dismissed', '1'); } catch (e) {}
  }
  closeBtn.addEventListener('click', function () {
    setDismissCookie(dismissDays);
    banner.parentNode && banner.parentNode.removeChild(banner);
    stopRotation();
  });
})();
