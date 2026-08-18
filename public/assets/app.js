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

  /* --- confirm destructive submits --- */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (event) {
      var message = f.getAttribute('data-confirm');
      // The bulk form only needs confirming when something is actually selected.
      if (f.id === 'bulk-form') {
        var n = f.querySelectorAll('.row-check:checked').length;
        if (n === 0) { event.preventDefault(); return; }
        message = message.replace('{n}', String(n));
      }
      if (!window.confirm(message)) event.preventDefault();
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
