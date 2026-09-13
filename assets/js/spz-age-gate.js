/*
 * Secret Pleasurez — 18+ age confirmation.
 *
 * Loaded in <head> so the store is hidden before first paint and no product
 * flashes on screen ahead of the question. Self-contained: it does not rely on
 * spz-modern.js, jQuery or the PrestaShop JS object.
 *
 * This is a click-through confirmation, not identity verification. It records
 * the answer for 30 days in localStorage, with a cookie as a fallback that the
 * server could also read.
 *
 * Failure modes are all "show the store": no JavaScript means no gate, and if
 * the gate cannot be built the page is released after a short timeout.
 * Search-engine crawlers are not gated, so product pages stay indexable.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'spz-age-ok';
  var COOKIE = 'spz_age_ok';
  var DAYS = 30;
  var LEAVE_URL = 'https://www.google.com/';
  var CRAWLERS = /bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|pinterest|preview|lighthouse/i;
  var root = document.documentElement;

  function confirmedRecently() {
    try {
      var at = parseInt(window.localStorage.getItem(STORAGE_KEY), 10);
      if (at && Date.now() - at < DAYS * 864e5) {
        return true;
      }
    } catch (e) {
      /* storage blocked: fall back to the cookie */
    }
    return new RegExp('(?:^|;\\s*)' + COOKIE + '=1').test(document.cookie);
  }

  if (CRAWLERS.test(navigator.userAgent) || confirmedRecently()) {
    return;
  }

  root.classList.add('spz-age-pending');

  function release() {
    root.classList.remove('spz-age-pending');
  }

  function remember() {
    try {
      window.localStorage.setItem(STORAGE_KEY, String(Date.now()));
    } catch (e) {
      /* the cookie below still covers this visitor */
    }
    document.cookie = COOKIE + '=1; max-age=' + DAYS * 86400 + '; path=/; SameSite=Lax';
  }

  function build() {
    if (document.getElementById('spz-age-gate')) {
      return;
    }
    try {
      var gate = document.createElement('div');
      gate.id = 'spz-age-gate';
      gate.className = 'spz-age-gate';
      gate.setAttribute('role', 'dialog');
      gate.setAttribute('aria-modal', 'true');
      gate.setAttribute('aria-labelledby', 'spz-age-title');
      gate.setAttribute('aria-describedby', 'spz-age-desc');
      gate.innerHTML =
        '<div class="spz-age-gate__panel">' +
        '<img class="spz-age-gate__logo" alt="" hidden>' +
        '<p class="spz-age-gate__eyebrow">Adults only</p>' +
        '<h2 class="spz-age-gate__title" id="spz-age-title">Are you 18 or older?</h2>' +
        '<p class="spz-age-gate__text" id="spz-age-desc">Secret Pleasurez sells adult products. ' +
        'You must be of legal age where you live to enter.</p>' +
        '<div class="spz-age-gate__actions">' +
        '<button type="button" class="spz-age-gate__enter" data-spz-enter>Yes, I\'m 18 or older</button>' +
        '<button type="button" class="spz-age-gate__leave" data-spz-leave>No, take me away</button>' +
        '</div>' +
        '</div>';

      // Reuse the store's own logo rather than hard-coding a path.
      var siteLogo = document.querySelector('#_desktop_logo img, img.logo');
      var logo = gate.querySelector('.spz-age-gate__logo');
      if (siteLogo && siteLogo.getAttribute('src')) {
        logo.setAttribute('src', siteLogo.getAttribute('src'));
        logo.setAttribute('alt', 'Secret Pleasurez');
        logo.hidden = false;
      }

      document.body.appendChild(gate);

      var enter = gate.querySelector('[data-spz-enter]');
      var leave = gate.querySelector('[data-spz-leave]');

      enter.addEventListener('click', function () {
        remember();
        release();
        gate.classList.add('spz-age-gate--out');
        window.setTimeout(function () {
          if (gate.parentNode) {
            gate.parentNode.removeChild(gate);
          }
        }, 350);
      });

      leave.addEventListener('click', function () {
        window.location.href = LEAVE_URL;
      });

      // Keep keyboard focus inside the dialog: the page behind is hidden.
      gate.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') {
          return;
        }
        event.preventDefault();
        var order = [enter, leave];
        var i = order.indexOf(document.activeElement);
        order[(i + (event.shiftKey ? order.length - 1 : 1)) % order.length].focus();
      });

      enter.focus();
    } catch (e) {
      release();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }

  // Safety valve: never leave the store hidden if the gate did not appear.
  window.setTimeout(function () {
    if (!document.getElementById('spz-age-gate')) {
      release();
    }
  }, 4000);
})();
