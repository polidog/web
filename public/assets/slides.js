/*
 * スライドの全画面プレゼンモード。/slides/<slug>/ だけが読む。
 *
 * 通常表示（ページを縦に積んだもの）は CSS と SVG の viewBox だけで
 * 成り立っていて、JS は要らない。ここでやるのは「1 枚ずつ画面いっぱいに
 * 出して、キーとクリックで送る」だけ。
 *
 * 状態は data 属性で持つ（`data-mode` / `data-current`）。見た目の切り替えは
 * public/assets/marp.css の `.marp-view[data-mode="present"]`。
 *
 *   [data-slides]          容器。data-mode が scroll / present
 *   [data-slides-present]  プレゼンモードに入るボタン
 *   [data-slides-stage]    全画面にする要素（スライドと HUD の親）
 *   [data-slides-hud]      プレゼン中だけ出す操作部（既定は hidden）
 *   [data-slides-counter]  「3 / 24」
 *   [data-slides-prev] / [data-slides-next] / [data-slides-close]
 *
 * Fullscreen API はあれば使い、無ければ（iPhone）固定配置のまま動かす。
 * どちらでも Esc・閉じるボタンで戻れる。
 */
(function () {
  'use strict';

  var view = document.querySelector('[data-slides]');
  if (!view) {
    return;
  }

  var stage = view.querySelector('[data-slides-stage]');
  var hud = view.querySelector('[data-slides-hud]');
  var counter = view.querySelector('[data-slides-counter]');
  var slides = view.querySelectorAll('.marp-slide');
  var total = slides.length;
  var current = 0;
  var presenting = false;

  if (!stage || total === 0) {
    return;
  }

  function show(index) {
    current = Math.max(0, Math.min(total - 1, index));
    for (var i = 0; i < total; i++) {
      if (i === current) {
        slides[i].setAttribute('data-current', 'true');
      } else {
        slides[i].removeAttribute('data-current');
      }
    }
    if (counter) {
      counter.textContent = current + 1 + ' / ' + total;
    }
    // URL の hash で「今のページ」を持たせる。Marp の HTML 出力と同じく
    // `#3` が 3 枚目。リロードや共有でそのページから開ける。
    if (presenting && window.history.replaceState) {
      window.history.replaceState(null, '', '#' + (current + 1));
    }
  }

  function inFullscreen() {
    return document.fullscreenElement === stage || document.webkitFullscreenElement === stage;
  }

  function enter(index) {
    presenting = true;
    view.setAttribute('data-mode', 'present');
    if (hud) {
      hud.removeAttribute('hidden');
    }
    show(index);

    if (stage.requestFullscreen) {
      stage.requestFullscreen().catch(function () {
        /* 断られても固定配置で動く */
      });
    } else if (stage.webkitRequestFullscreen) {
      stage.webkitRequestFullscreen();
    }

    stage.focus();
  }

  function leave() {
    if (!presenting) {
      return;
    }
    presenting = false;
    view.setAttribute('data-mode', 'scroll');
    if (hud) {
      hud.setAttribute('hidden', '');
    }
    for (var i = 0; i < total; i++) {
      slides[i].removeAttribute('data-current');
    }
    if (inFullscreen()) {
      if (document.exitFullscreen) {
        document.exitFullscreen().catch(function () {});
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    }
    // 見ていたページの位置まで戻す。全画面から戻ると縦積みの先頭に
    // 置き去りにされるので。
    if (slides[current] && slides[current].scrollIntoView) {
      slides[current].scrollIntoView({ block: 'center' });
    }
  }

  function on(selector, handler) {
    var elements = view.querySelectorAll(selector);
    for (var i = 0; i < elements.length; i++) {
      elements[i].addEventListener('click', handler);
    }
  }

  on('[data-slides-present]', function () {
    enter(pageFromHash());
  });
  on('[data-slides-prev]', function (event) {
    event.stopPropagation();
    show(current - 1);
  });
  on('[data-slides-next]', function (event) {
    event.stopPropagation();
    show(current + 1);
  });
  on('[data-slides-close]', function (event) {
    event.stopPropagation();
    leave();
  });

  // スライドの右半分で進む、左半分で戻る。
  stage.addEventListener('click', function (event) {
    if (!presenting || (hud && hud.contains(event.target))) {
      return;
    }
    var rect = stage.getBoundingClientRect();
    show(event.clientX - rect.left < rect.width / 3 ? current - 1 : current + 1);
  });

  document.addEventListener('keydown', function (event) {
    if (!presenting || event.metaKey || event.ctrlKey || event.altKey) {
      return;
    }
    switch (event.key) {
      case 'ArrowRight':
      case 'ArrowDown':
      case 'PageDown':
      case ' ':
      case 'j':
      case 'l':
        show(current + 1);
        break;
      case 'ArrowLeft':
      case 'ArrowUp':
      case 'PageUp':
      case 'k':
      case 'h':
        show(current - 1);
        break;
      case 'Home':
        show(0);
        break;
      case 'End':
        show(total - 1);
        break;
      case 'Escape':
        leave();
        break;
      default:
        return;
    }
    event.preventDefault();
  });

  // ブラウザ側で全画面を抜けた（Esc）ときも状態を揃える。
  function onFullscreenChange() {
    if (presenting && !inFullscreen()) {
      leave();
    }
  }
  document.addEventListener('fullscreenchange', onFullscreenChange);
  document.addEventListener('webkitfullscreenchange', onFullscreenChange);

  // スワイプ。横に 40px 以上動いたら送る。
  var touchX = null;
  stage.addEventListener(
    'touchstart',
    function (event) {
      touchX = event.touches.length === 1 ? event.touches[0].clientX : null;
    },
    { passive: true }
  );
  stage.addEventListener(
    'touchend',
    function (event) {
      if (!presenting || touchX === null || event.changedTouches.length !== 1) {
        return;
      }
      var delta = event.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(delta) >= 40) {
        show(delta < 0 ? current + 1 : current - 1);
      }
    },
    { passive: true }
  );

  function pageFromHash() {
    var n = parseInt(window.location.hash.replace('#', ''), 10);
    return isNaN(n) ? 0 : n - 1;
  }
})();
