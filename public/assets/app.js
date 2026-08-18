/* AlmasryLog — bulk selection, confirmations. No dependencies. */
(function () {
  'use strict';

  function humanBytes(bytes) {
    var units = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
  }

  /* --- bulk select on the dashboard table --- */
  var form = document.getElementById('bulk-form');
  if (form) {
    var checkAll = document.getElementById('check-all');
    var bulkbar  = document.getElementById('bulkbar');
    var countEl  = document.getElementById('bulk-count');
    var sizeEl   = document.getElementById('bulk-size');
    var clearBtn = document.getElementById('bulk-clear');

    var rows = function () {
      return Array.prototype.slice.call(form.querySelectorAll('.row-check:not(:disabled)'));
    };

    var sync = function () {
      var checked = rows().filter(function (c) { return c.checked; });
      var bytes = checked.reduce(function (sum, c) {
        return sum + (parseInt(c.dataset.size, 10) || 0);
      }, 0);

      if (countEl) countEl.textContent = String(checked.length);
      if (sizeEl)  sizeEl.textContent  = humanBytes(bytes);
      if (bulkbar) bulkbar.hidden = checked.length === 0;
      if (checkAll) {
        checkAll.checked = checked.length > 0 && checked.length === rows().length;
        checkAll.indeterminate = checked.length > 0 && checked.length < rows().length;
      }
    };

    form.addEventListener('change', function (event) {
      if (event.target === checkAll) {
        rows().forEach(function (c) { c.checked = checkAll.checked; });
      }
      sync();
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        rows().forEach(function (c) { c.checked = false; });
        if (checkAll) checkAll.checked = false;
        sync();
      });
    }

    sync();
  }

  /* --- confirm destructive submits (in-page modal, not window.confirm) --- */
  var I18N = window.APP_I18N || { title: 'تأكيد العملية', yes: 'نعم، نفّذ', cancel: 'إلغاء' };

  function showConfirm(message, onYes) {
    var overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    var box = document.createElement('div');
    box.className = 'modal-box';

    var head = document.createElement('h3');
    head.textContent = '⚠️ ' + I18N.title;

    var body = document.createElement('p');
    body.textContent = message;

    var actions = document.createElement('div');
    actions.className = 'modal-actions';

    var cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn btn-ghost';
    cancel.textContent = I18N.cancel;

    var yes = document.createElement('button');
    yes.type = 'button';
    yes.className = 'btn btn-danger';
    yes.textContent = I18N.yes;

    var close = function () {
      document.removeEventListener('keydown', onKey);
      overlay.remove();
    };
    var onKey = function (e) { if (e.key === 'Escape') close(); };

    cancel.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', onKey);
    yes.addEventListener('click', function () { close(); onYes(); });

    actions.appendChild(cancel);
    actions.appendChild(yes);
    box.appendChild(head);
    box.appendChild(body);
    box.appendChild(actions);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    cancel.focus();
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (event) {
      var message = f.getAttribute('data-confirm');
      // The bulk form only needs confirming when something is actually selected.
      if (f.id === 'bulk-form') {
        var n = f.querySelectorAll('.row-check:checked').length;
        if (n === 0) { event.preventDefault(); return; }
        message = message.replace('{n}', String(n));
      }
      event.preventDefault();
      showConfirm(message, function () { f.submit(); });
    });
  });

  /* --- light/dark toggle (manual choice beats the system preference) --- */
  var themeBtn = document.getElementById('theme-toggle');
  if (themeBtn) {
    var effectiveTheme = function () {
      return document.documentElement.dataset.theme
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    };
    // Show the mode the click switches TO: a sun while dark, a moon while light.
    var paintIcon = function () {
      themeBtn.textContent = effectiveTheme() === 'dark' ? '☀️' : '🌙';
    };
    themeBtn.addEventListener('click', function () {
      var next = effectiveTheme() === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      try { localStorage.setItem('almasrylog_theme', next); } catch (e) {}
      paintIcon();
    });
    paintIcon();
  }
})();
