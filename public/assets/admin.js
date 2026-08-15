/*
 * 管理画面のクライアントスクリプト。
 *
 * 公開側と同じ理由でインライン <script> が書けない（usePHP の Renderer は
 * children の文字列を必ずエスケープする）ので、JS はこのファイルに寄せ、
 * サーバからの値は data 属性で受け取る。読み込むのは AdminLayout だけ
 * （公開側のページには 1 バイトも足さない）。
 *
 * 触る相手は AdminComponents::editor() が出す DOM で、契約は data 属性:
 *
 *   [data-editor]                フォーム本体。data-preview / data-preview-url /
 *                                data-upload-url を持つ
 *   [data-editor-body]           本文の textarea
 *   [data-editor-preview]        プレビューの差し込み先
 *   [data-editor-preview-toggle] プレビューの開閉
 *   [data-editor-count]          文字数
 *   [data-editor-status]         アップロードなどの途中経過
 *   [data-editor-dirty]          未保存インジケータ
 *   [data-md]                    Markdown の記法ボタン
 *   [data-copy]                  クリップボードにコピー（画像一覧）
 *   [data-confirm]               押す前に確認する（削除）
 */
(function () {
  'use strict';

  var PREVIEW_KEY = 'admin:preview';

  function initConfirm() {
    var buttons = document.querySelectorAll('[data-confirm]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function (event) {
        if (!window.confirm(event.currentTarget.dataset.confirm)) {
          event.preventDefault();
        }
      });
    }
  }

  function initCopy() {
    var buttons = document.querySelectorAll('[data-copy]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function (event) {
        var button = event.currentTarget;
        var label = button.textContent;

        // クリップボード API は https か localhost でしか使えない。
        // 使えない環境では黙って失敗せず、その旨を出す。
        if (!navigator.clipboard) {
          button.textContent = 'コピーできません';
          window.setTimeout(function () {
            button.textContent = label;
          }, 1500);
          return;
        }

        navigator.clipboard.writeText(button.dataset.copy).then(function () {
          button.textContent = 'コピーしました';
          window.setTimeout(function () {
            button.textContent = label;
          }, 1500);
        });
      });
    }
  }

  function initEditor() {
    var form = document.querySelector('[data-editor]');
    if (!form) {
      return;
    }

    var body = form.querySelector('[data-editor-body]');
    var preview = form.querySelector('[data-editor-preview]');
    var toggle = form.querySelector('[data-editor-preview-toggle]');
    var count = form.querySelector('[data-editor-count]');
    var status = form.querySelector('[data-editor-status]');
    var dirtyMark = form.querySelector('[data-editor-dirty]');
    var dirty = false;
    var timer = null;
    var rendered = null;

    function setStatus(text) {
      if (status) {
        status.textContent = text;
      }
    }

    function updateCount() {
      if (count) {
        count.textContent = body.value.length.toLocaleString('ja-JP') + ' 字';
      }
    }

    function markDirty() {
      dirty = true;
      if (dirtyMark) {
        dirtyMark.removeAttribute('hidden');
      }
    }

    /*
     * プレビューはサーバの MarkdownRenderer に投げる。ブラウザ側にパーサを
     * 持たないのは、保存時と変換結果がずれると「プレビューでは正しかった
     * のに」が起きるため（/admin/preview の route.php にも同じ注意がある）。
     */
    function render() {
      if (form.dataset.preview !== 'on' || body.value === rendered) {
        return;
      }

      rendered = body.value;

      window
        .fetch(form.dataset.previewUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: 'body=' + encodeURIComponent(rendered),
        })
        .then(function (response) {
          if (!response.ok) {
            throw new Error(String(response.status));
          }
          return response.json();
        })
        .then(function (data) {
          // 中身は自分が書いた Markdown をサーバが変換した HTML。
          // 記事本文と同じものなので、記事と同じように表示する。
          preview.innerHTML = data.html;
        })
        .catch(function () {
          preview.textContent = 'プレビューを読み込めませんでした。';
        });
    }

    function schedule() {
      window.clearTimeout(timer);
      timer = window.setTimeout(render, 400);
    }

    function setPreview(on) {
      form.dataset.preview = on ? 'on' : 'off';
      if (toggle) {
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
      }
      try {
        window.localStorage.setItem(PREVIEW_KEY, on ? 'on' : 'off');
      } catch (e) {
        /* プライベートブラウジングでは覚えられないが、開閉自体は効く */
      }
      if (on) {
        render();
      }
    }

    /*
     * 挿入は execCommand を通す。deprecated だが、textarea の undo 履歴に
     * 残る挿入手段はこれしかない（value を直接書き換えると Ctrl+Z で
     * 戻せなくなり、書いている最中のエディタとしては致命的）。
     * 使えない環境では setRangeText に落とす。
     */
    function insert(text) {
      body.focus();
      if (!document.execCommand || !document.execCommand('insertText', false, text)) {
        var start = body.selectionStart;
        body.setRangeText(text, start, body.selectionEnd, 'end');
      }
      markDirty();
      updateCount();
      schedule();
    }

    function surround(before, after) {
      var selected = body.value.slice(body.selectionStart, body.selectionEnd);
      var caret = body.selectionStart + before.length + selected.length;
      insert(before + selected + after);
      if ('' === selected) {
        // 何も選んでいなければ、囲みの内側にカーソルを置く。
        body.setSelectionRange(caret, caret);
      }
    }

    // 行頭の記法（見出し・引用・箇条書き）。選択が複数行なら全部の行に付ける。
    function prefixLines(prefix) {
      var start = body.value.lastIndexOf('\n', body.selectionStart - 1) + 1;
      var end = body.selectionEnd;
      var lines = body.value.slice(start, end).split('\n');

      body.setSelectionRange(start, end);
      insert(
        lines
          .map(function (line) {
            return line.indexOf(prefix) === 0 ? line : prefix + line;
          })
          .join('\n')
      );
    }

    function applyTool(key) {
      if (key === 'bold') {
        surround('**', '**');
      } else if (key === 'italic') {
        surround('*', '*');
      } else if (key === 'link') {
        surround('[', '](https://)');
      } else if (key === 'code') {
        surround('\n```\n', '\n```\n');
      } else if (key === 'h2') {
        prefixLines('## ');
      } else if (key === 'quote') {
        prefixLines('> ');
      } else if (key === 'list') {
        prefixLines('- ');
      }
    }

    /*
     * 画像。ドロップと貼り付けの両方から同じ口に流す。アップロード先は
     * /admin/media/upload で、CSRF トークンはフォームに埋まっているものを
     * そのまま渡す（route.php は CSRF を自動では見ないので、受け側で
     * 明示的に検証している）。
     */
    function uploadFiles(files) {
      var token = form.querySelector('input[name="_usephp_csrf"]');
      var images = [];

      for (var i = 0; i < files.length; i++) {
        if (files[i].type.indexOf('image/') === 0) {
          images.push(files[i]);
        }
      }

      if (images.length === 0 || !token) {
        return;
      }

      setStatus('アップロード中…');

      var pending = images.map(function (file) {
        var data = new FormData();
        data.append('file', file);
        data.append('_usephp_csrf', token.value);

        return window
          .fetch(form.dataset.uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
          })
          .then(function (response) {
            return response.json().then(function (json) {
              if (!response.ok) {
                throw new Error(json.error || String(response.status));
              }
              return json.url;
            });
          });
      });

      Promise.all(pending)
        .then(function (urls) {
          insert(
            urls
              .map(function (url) {
                return '![](' + url + ')';
              })
              .join('\n') + '\n'
          );
          setStatus('');
        })
        .catch(function (error) {
          setStatus('アップロードに失敗: ' + error.message);
        });
    }

    body.addEventListener('input', function () {
      markDirty();
      updateCount();
      schedule();
    });

    body.addEventListener('dragover', function (event) {
      event.preventDefault();
    });

    body.addEventListener('drop', function (event) {
      if (event.dataTransfer && event.dataTransfer.files.length > 0) {
        event.preventDefault();
        uploadFiles(event.dataTransfer.files);
      }
    });

    body.addEventListener('paste', function (event) {
      if (event.clipboardData && event.clipboardData.files.length > 0) {
        event.preventDefault();
        uploadFiles(event.clipboardData.files);
      }
    });

    if (toggle) {
      toggle.addEventListener('click', function () {
        setPreview(form.dataset.preview !== 'on');
      });
    }

    var tools = form.querySelectorAll('[data-md]');
    for (var i = 0; i < tools.length; i++) {
      tools[i].addEventListener('click', function (event) {
        applyTool(event.currentTarget.dataset.md);
      });
    }

    form.addEventListener('input', markDirty);
    form.addEventListener('submit', function () {
      dirty = false;
    });

    window.addEventListener('beforeunload', function (event) {
      if (dirty) {
        event.preventDefault();
        event.returnValue = '';
      }
    });

    // Ctrl/Cmd + S で保存。本文が長いほどマウスに手を伸ばす回数が効いてくる。
    document.addEventListener('keydown', function (event) {
      if ((event.metaKey || event.ctrlKey) && event.key === 's') {
        event.preventDefault();
        form.requestSubmit();
      }
    });

    updateCount();

    var stored = null;
    try {
      stored = window.localStorage.getItem(PREVIEW_KEY);
    } catch (e) {
      /* 読めなくても既定（閉じている）で動く */
    }
    if (stored === 'on') {
      setPreview(true);
    }
  }

  initConfirm();
  initCopy();
  initEditor();
})();
