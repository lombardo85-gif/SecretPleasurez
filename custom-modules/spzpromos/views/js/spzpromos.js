/*
 * Homepage offers carousel (module spzpromos).
 *
 * The slides are a native scroll-snap strip, so swipe, trackpad and keyboard
 * scrolling work without this script. It adds arrows, dots, autoplay and
 * copy-to-clipboard for codes.
 *
 * Autoplay advances every 6s. It stops for good when the visitor presses
 * pause; it holds while the pointer or keyboard focus is inside the carousel
 * or the tab is hidden; with reduced motion it never starts.
 */
(function () {
  'use strict';

  var INTERVAL = 6000;

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      var ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        /* reported below */
      }
      document.body.removeChild(area);
      if (ok) {
        resolve();
      } else {
        reject(new Error('copy failed'));
      }
    });
  }

  function initCodes(root) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-spz-code]'), function (button) {
      var hint = button.querySelector('.spz-code__hint');
      var resetTimer = null;
      button.addEventListener('click', function () {
        copyText(button.getAttribute('data-spz-code')).then(
          function () {
            hint.textContent = 'Copied';
          },
          function () {
            hint.textContent = 'Select to copy';
          }
        );
        window.clearTimeout(resetTimer);
        resetTimer = window.setTimeout(function () {
          hint.textContent = 'Copy';
        }, 2200);
      });
    });
  }

  function initCarousel(root) {
    var track = root.querySelector('[data-spz-track]');
    var slides = track ? Array.prototype.slice.call(track.children) : [];
    if (slides.length < 2) {
      return;
    }

    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-spz-dot]'));
    var toggle = root.querySelector('[data-spz-toggle]');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var index = 0;
    var timer = null;
    var stopped = reduceMotion;
    var hovering = false;
    var focused = false;

    root.style.setProperty('--spz-promo-interval', INTERVAL + 'ms');

    function mark(active) {
      slides.forEach(function (slide, i) {
        // Off-screen slides leave the tab order, so keyboard users are not
        // walked through links they cannot see.
        slide.inert = i !== active;
      });
      dots.forEach(function (dot, i) {
        if (i === active) {
          dot.setAttribute('aria-current', 'true');
        } else {
          dot.removeAttribute('aria-current');
        }
      });
    }

    function schedule() {
      window.clearTimeout(timer);
      var playing = !stopped && !hovering && !focused && !document.hidden;
      // Restart the progress bar on the active dot.
      root.classList.remove('is-playing');
      if (playing) {
        void root.offsetWidth;
        root.classList.add('is-playing');
        timer = window.setTimeout(function () {
          show(index + 1);
        }, INTERVAL);
      }
    }

    function show(i, instant) {
      index = (i + slides.length) % slides.length;
      track.scrollTo({
        left: slides[index].offsetLeft,
        behavior: instant || reduceMotion ? 'auto' : 'smooth'
      });
      mark(index);
      schedule();
    }

    // Keep dots in step when the visitor swipes or scrolls the strip.
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            var i = slides.indexOf(entry.target);
            if (entry.isIntersecting && i !== index) {
              index = i;
              mark(index);
              schedule();
            }
          });
        },
        { root: track, threshold: 0.6 }
      );
      slides.forEach(function (slide) {
        observer.observe(slide);
      });
    }

    root.querySelector('[data-spz-prev]').addEventListener('click', function () {
      show(index - 1);
    });
    root.querySelector('[data-spz-next]').addEventListener('click', function () {
      show(index + 1);
    });
    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () {
        show(i);
      });
    });

    if (toggle) {
      toggle.setAttribute('aria-pressed', stopped ? 'true' : 'false');
      toggle.addEventListener('click', function () {
        stopped = !stopped;
        toggle.setAttribute('aria-pressed', stopped ? 'true' : 'false');
        schedule();
      });
    }

    track.addEventListener('keydown', function (event) {
      if (event.target !== track) {
        return;
      }
      if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        show(index + (event.key === 'ArrowRight' ? 1 : -1));
      }
    });

    root.addEventListener('pointerenter', function (event) {
      if (event.pointerType === 'mouse') {
        hovering = true;
        schedule();
      }
    });
    root.addEventListener('pointerleave', function (event) {
      if (event.pointerType === 'mouse') {
        hovering = false;
        schedule();
      }
    });
    root.addEventListener('focusin', function () {
      focused = true;
      schedule();
    });
    root.addEventListener('focusout', function (event) {
      if (!root.contains(event.relatedTarget)) {
        focused = false;
        schedule();
      }
    });
    document.addEventListener('visibilitychange', schedule);

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () {
        show(index, true);
      }, 150);
    });

    mark(0);
    schedule();
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-spz-promos]'), function (root) {
      initCodes(root);
      initCarousel(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
