/*
 * Secret Pleasurez — modern storefront layer.
 *
 * Progressive enhancement only. Every feature checks for the elements and
 * browser APIs it needs, and without this script the store works unchanged.
 * Each feature runs in isolation, so one failure cannot take the others down.
 *
 * Styles live in assets/css/spz-modern.css. The reveal and shimmer styles only
 * apply once this script sets html.spz-enhanced, so if it never runs, nothing
 * is left hidden.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var body = document.body;
  var ps = window.prestashop || {};
  var pages = (ps.urls && ps.urls.pages) || {};
  var baseUrl = (ps.urls && ps.urls.base_url) || '/';
  var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var phone = window.matchMedia ? window.matchMedia('(max-width: 767px)') : { matches: false };

  root.classList.add('spz-enhanced');

  function safely(name, fn) {
    try {
      fn();
    } catch (err) {
      if (window.console) {
        window.console.warn('[spz] ' + name + ' disabled:', err);
      }
    }
  }

  function onPrestaShop(eventName, handler) {
    if (typeof ps.on === 'function') {
      ps.on(eventName, handler);
    }
  }

  function each(selector, fn, scope) {
    Array.prototype.forEach.call((scope || document).querySelectorAll(selector), fn);
  }

  function isPage(name) {
    return body.id === name || body.classList.contains('page-' + name);
  }

  /* 1. Compact glass header once the full header has scrolled away. ------ */
  function stickyHeader() {
    var header = document.getElementById('header');
    var bar = header && header.querySelector('.header-top');
    if (!bar) {
      return;
    }

    var stuck = false;
    var ticking = false;
    var barHeight = 0;
    var threshold = 0;

    function measure() {
      if (stuck) {
        return;
      }
      barHeight = bar.offsetHeight;
      threshold = header.offsetTop + header.offsetHeight;
    }

    function update() {
      ticking = false;
      var shouldStick = !phone.matches && window.scrollY > threshold;
      if (shouldStick === stuck) {
        return;
      }
      stuck = shouldStick;
      // Hold the bar's space open so content does not jump when it leaves the flow.
      header.style.paddingBottom = stuck ? barHeight + 'px' : '';
      root.classList.toggle('spz-stuck', stuck);
      if (!stuck) {
        measure();
      }
    }

    measure();
    window.addEventListener('scroll', function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }, { passive: true });
    window.addEventListener('resize', function () {
      measure();
      update();
    });
    window.addEventListener('load', measure);
  }

  /* 2. Shimmer placeholders until each product photo has loaded. --------- */
  function photoShimmer() {
    var hosts = '.product-miniature, .product-cover';

    function markReady(img) {
      var host = img.closest && img.closest(hosts);
      if (host) {
        host.classList.add('spz-img-ready');
      }
    }

    // Capture phase catches images added later too: carousel clones, faceted
    // search results, and lazy images as they arrive.
    ['load', 'error'].forEach(function (type) {
      document.addEventListener(type, function (event) {
        if (event.target && event.target.tagName === 'IMG') {
          markReady(event.target);
        }
      }, true);
    });

    function sweep() {
      each(hosts, function (host) {
        var img = host.querySelector('img');
        if (!img || img.complete) {
          host.classList.add('spz-img-ready');
        }
      });
    }

    sweep();
    window.addEventListener('load', function () {
      window.setTimeout(sweep, 1200);
    });
  }

  /* 3. Reveal grids and section headings as they scroll into view. ------- */
  function scrollReveal() {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('spz-in');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    function scan() {
      var index = 0;
      each('#js-product-list .product-miniature, .main-title, .block-category, .tabs', function (el) {
        // Carousel items move by transform; leave them to the carousel.
        if (el.classList.contains('spz-reveal') || el.closest('.owl-carousel')) {
          return;
        }
        el.classList.add('spz-reveal');
        el.style.transitionDelay = (index++ % 4) * 60 + 'ms';
        observer.observe(el);
      });
    }

    scan();

    // Faceted search replaces the product list without a page load.
    var list = document.getElementById('js-product-list');
    if (list && 'MutationObserver' in window) {
      new MutationObserver(scan).observe(list, { childList: true, subtree: true });
    }

    // Never leave on-screen content hidden if an observer callback is missed.
    window.setTimeout(function () {
      each('.spz-reveal:not(.spz-in)', function (el) {
        if (el.getBoundingClientRect().top < window.innerHeight) {
          el.classList.add('spz-in');
        }
      });
    }, 2500);
  }

  /* 4. App-style bottom bar on phones. ------------------------------------ */
  var ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    browse: '<path d="M4 6h16M4 12h16M4 18h10"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    account: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    cart: '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/>' +
      '<circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>'
  };

  function icon(name) {
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ' +
      'focusable="false">' + ICONS[name] + '</svg>';
  }

  function bottomBar() {
    if (document.querySelector('.spz-bottom-nav')) {
      return;
    }

    var nav = document.createElement('nav');
    nav.className = 'spz-bottom-nav';
    nav.setAttribute('aria-label', 'Shop navigation');
    nav.innerHTML =
      '<a class="spz-bottom-nav__item" data-spz="home">' + icon('home') + '<span>Home</span></a>' +
      '<button type="button" class="spz-bottom-nav__item" data-spz="browse">' + icon('browse') + '<span>Browse</span></button>' +
      '<button type="button" class="spz-bottom-nav__item" data-spz="search">' + icon('search') + '<span>Search</span></button>' +
      '<a class="spz-bottom-nav__item" data-spz="account">' + icon('account') + '<span>Account</span></a>' +
      '<a class="spz-bottom-nav__item" data-spz="cart">' + icon('cart') + '<span>Cart</span>' +
      '<span class="spz-bottom-nav__badge" hidden>0</span></a>';

    var item = function (key) {
      return nav.querySelector('[data-spz="' + key + '"]');
    };

    // URLs come from PrestaShop's own routing; set as attributes, never HTML.
    var cartUrl = pages.cart
      ? pages.cart + (pages.cart.indexOf('?') > -1 ? '&' : '?') + 'action=show'
      : baseUrl + 'index.php?controller=cart&action=show';
    item('home').setAttribute('href', baseUrl);
    item('account').setAttribute('href', pages.my_account || baseUrl + 'index.php?controller=my-account');
    item('cart').setAttribute('href', cartUrl);

    if (isPage('index')) {
      item('home').setAttribute('aria-current', 'page');
    } else if (isPage('cart')) {
      item('cart').setAttribute('aria-current', 'page');
    } else if (isPage('my-account') || isPage('authentication')) {
      item('account').setAttribute('aria-current', 'page');
    }

    item('browse').addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      var toggle = document.getElementById('menu-icon');
      if (toggle) {
        toggle.click();
      }
    });

    item('search').addEventListener('click', function () {
      var input = document.querySelector('#search_widget input[name="s"], #search_widget input[type="text"]');
      if (!input) {
        window.location.href = baseUrl + 'index.php?controller=search';
        return;
      }
      // Focus inside the tap so phones open the keyboard, then bring it into view.
      input.focus({ preventScroll: true });
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });

    body.appendChild(nav);
    root.classList.add('spz-has-bottom-nav');

    var badge = nav.querySelector('.spz-bottom-nav__badge');

    function syncCart() {
      var count = document.querySelector('.blockcart .cart-products-count, .cart-products-count');
      var n = count ? parseInt(count.textContent.replace(/\D+/g, ''), 10) || 0 : 0;
      badge.textContent = n > 99 ? '99+' : String(n);
      badge.hidden = n === 0;
      item('cart').setAttribute('aria-label', 'Cart, ' + n + (n === 1 ? ' item' : ' items'));
    }

    syncCart();
    onPrestaShop('updateCart', function () {
      window.setTimeout(syncCart, 700);
    });

    // PrestaShop swaps the whole cart block on update, so watch its parent.
    var blockcart = document.querySelector('.blockcart');
    if (blockcart && blockcart.parentNode && 'MutationObserver' in window) {
      new MutationObserver(syncCart).observe(blockcart.parentNode, { childList: true, subtree: true, characterData: true });
    }
  }

  /* 5. Sticky add-to-cart on phones once the real button leaves view. ---- */
  function stickyAddToCart() {
    if (!isPage('product') || !('IntersectionObserver' in window)) {
      return;
    }

    var bar = document.createElement('div');
    bar.className = 'spz-sticky-atc';
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML =
      '<div class="spz-sticky-atc__info">' +
      '<span class="spz-sticky-atc__name"></span><span class="spz-sticky-atc__price"></span>' +
      '</div>' +
      '<button type="button" class="spz-sticky-atc__btn" tabindex="-1"></button>';
    body.appendChild(bar);

    var nameEl = bar.querySelector('.spz-sticky-atc__name');
    var priceEl = bar.querySelector('.spz-sticky-atc__price');
    var cta = bar.querySelector('.spz-sticky-atc__btn');
    var observed = null;
    var realVisible = true;

    var observer = new IntersectionObserver(function (entries) {
      realVisible = entries[entries.length - 1].isIntersecting;
      render();
    });

    function realButton() {
      return document.querySelector('.product-add-to-cart .add-to-cart');
    }

    function render() {
      var show = phone.matches && !realVisible && observed !== null;
      bar.classList.toggle('spz-sticky-atc--show', show);
      bar.setAttribute('aria-hidden', show ? 'false' : 'true');
      cta.tabIndex = show ? 0 : -1;
    }

    // PrestaShop re-renders the price block and button when options change.
    function refresh() {
      var button = realButton();
      var title = document.querySelector('#product h1.h1, h1[itemprop="name"]');
      var price = document.querySelector('.product-prices .current-price span, .current-price-value');
      nameEl.textContent = title ? title.textContent.trim() : '';
      priceEl.textContent = price ? price.textContent.trim() : '';

      var unavailable = !button || button.disabled;
      cta.disabled = unavailable;
      cta.textContent = unavailable ? 'Unavailable' : 'Add to cart';

      if (button && button !== observed) {
        if (observed) {
          observer.unobserve(observed);
        }
        observer.observe(button);
        observed = button;
      }
      render();
    }

    // Delegate to the real button so PrestaShop's own cart flow still runs.
    cta.addEventListener('click', function () {
      var button = realButton();
      if (button && !button.disabled) {
        button.click();
      }
    });

    refresh();
    onPrestaShop('updatedProduct', function () {
      window.setTimeout(refresh, 50);
    });
    if (phone.addEventListener) {
      phone.addEventListener('change', render);
    }
  }

  safely('sticky header', stickyHeader);
  safely('photo shimmer', photoShimmer);
  safely('scroll reveal', scrollReveal);
  safely('bottom bar', bottomBar);
  safely('sticky add-to-cart', stickyAddToCart);
})();
