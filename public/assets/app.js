/* LogFlow — Next-Gen SaaS Dashboard Interactivity */
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
      if (f.id === 'bulk-form') {
        var n = f.querySelectorAll('.row-check:checked').length;
        if (n === 0) { event.preventDefault(); return; }
        message = message.replace('{n}', String(n));
      }
      event.preventDefault();
      showConfirm(message, function () { f.submit(); });
    });
  });

  /* --- light/dark toggle --- */
  var themeBtns = [document.getElementById('theme-toggle'), document.getElementById('theme-toggle-header')].filter(Boolean);
  if (themeBtns.length) {
    var effectiveTheme = function () {
      return document.documentElement.dataset.theme
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    };
    var paintIcon = function () {
      themeBtns.forEach(function (btn) {
        btn.textContent = effectiveTheme() === 'dark' ? '☀️' : '🌙';
      });
    };
    themeBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = effectiveTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('logflow_theme', next); } catch (e) {}
        paintIcon();
      });
    });
    paintIcon();
  }

  /* --- toggle password visibility --- */
  var togglePwdBtn = document.getElementById('toggle-password-btn');
  var pwdInput = document.getElementById('input-password');
  if (togglePwdBtn && pwdInput) {
    togglePwdBtn.addEventListener('click', function () {
      var isPwd = pwdInput.type === 'password';
      pwdInput.type = isPwd ? 'text' : 'password';
      var eyeShow = togglePwdBtn.querySelector('.eye-show');
      var eyeHide = togglePwdBtn.querySelector('.eye-hide');
      if (eyeShow && eyeHide) {
        eyeShow.style.display = isPwd ? 'none' : 'block';
        eyeHide.style.display = isPwd ? 'block' : 'none';
      }
    });
  }

  /* --- Time comparison & live clock ticking --- */
  var hdrServerTime = document.getElementById('hdr-server-time');
  var hdrClientTime = document.getElementById('hdr-client-time');
  var hdrOffsetVal  = document.getElementById('hdr-offset-val');
  var hdrOffsetBadge= document.getElementById('hdr-offset-badge');

  var initialServerTsEl = hdrServerTime || document.getElementById('server-clock-val');
  if (initialServerTsEl) {
    var initialServerTs = parseInt(initialServerTsEl.getAttribute('data-server-ts'), 10) * 1000;
    var initialClientTs = Date.now();
    var diffMs = initialServerTs - initialClientTs;

    var pad = function (n) { return String(n).padStart(2, '0'); };

    var updateClocks = function () {
      var nowClient = new Date();
      var nowServer = new Date(nowClient.getTime() + diffMs);

      // Format Client Time
      var cTimeStr = pad(nowClient.getHours()) + ':' + pad(nowClient.getMinutes()) + ':' + pad(nowClient.getSeconds());
      if (hdrClientTime) hdrClientTime.textContent = cTimeStr;

      // Format Server Time
      var sTimeStr = pad(nowServer.getHours()) + ':' + pad(nowServer.getMinutes()) + ':' + pad(nowServer.getSeconds());
      if (hdrServerTime) hdrServerTime.textContent = sTimeStr;

      // Difference formatting
      var absDiffSec = Math.abs(Math.round(diffMs / 1000));
      var hours = Math.floor(absDiffSec / 3600);
      var minutes = Math.floor((absDiffSec % 3600) / 60);
      var seconds = absDiffSec % 60;

      var sign = diffMs >= 0 ? '+' : '-';
      var formattedDiff = sign + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
      
      if (hdrOffsetVal) hdrOffsetVal.textContent = formattedDiff;

      var isSynced = absDiffSec < 60;
      var statusText = isSynced 
        ? (I18N.synced || 'مُتزامن') 
        : (I18N.offsetHours ? I18N.offsetHours.replace('%d', String(hours)) : ('فرق ' + hours + ' ساعة'));
      var statusClass = isSynced ? 'tag tag-sm tag-success' : 'tag tag-sm tag-warn';

      if (hdrOffsetBadge) { hdrOffsetBadge.textContent = statusText; hdrOffsetBadge.className = statusClass; }
    };

    updateClocks();
    setInterval(updateClocks, 1000);
  }

  /* --- Log Search Up/Down Match Navigation --- */
  var searchInput = document.getElementById('log-search-input');
  var prevBtn     = document.getElementById('btn-search-prev');
  var nextBtn     = document.getElementById('btn-search-next');
  var counterBadge= document.getElementById('search-counter-badge');
  var logTable    = document.querySelector('.logtable');

  if (logTable && prevBtn && nextBtn) {
    var matches = Array.prototype.slice.call(logTable.querySelectorAll('mark'));
    var currentIndex = -1;

    var updateMatchSelection = function (index) {
      if (!matches.length) return;
      
      // Wrap index around
      if (index < 0) index = matches.length - 1;
      if (index >= matches.length) index = 0;
      currentIndex = index;

      // Remove active class from all marks
      matches.forEach(function (m) { m.classList.remove('mark-active'); });

      // Highlight active mark
      var target = matches[currentIndex];
      target.classList.add('mark-active');

      // Scroll ONLY the inner .logview container, leaving outer window position still!
      var logView = logTable.closest('.logview') || logTable.parentElement;
      if (logView) {
        var targetRect = target.getBoundingClientRect();
        var containerRect = logView.getBoundingClientRect();
        var scrollTop = logView.scrollTop + (targetRect.top - containerRect.top) - (containerRect.height / 2) + (targetRect.height / 2);
        logView.scrollTo({ top: scrollTop, behavior: 'smooth' });
      }

      // Update counter badge
      if (counterBadge) {
        counterBadge.textContent = (currentIndex + 1) + ' / ' + matches.length;
        counterBadge.style.display = 'inline-block';
      }
    };

    if (matches.length > 0) {
      prevBtn.disabled = false;
      nextBtn.disabled = false;
      updateMatchSelection(0);
    } else if (counterBadge) {
      counterBadge.style.display = 'none';
      prevBtn.disabled = true;
      nextBtn.disabled = true;
    }

    nextBtn.addEventListener('click', function (e) {
      e.preventDefault();
      updateMatchSelection(currentIndex + 1);
    });

    prevBtn.addEventListener('click', function (e) {
      e.preventDefault();
      updateMatchSelection(currentIndex - 1);
    });

    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          if (matches.length > 0) {
            e.preventDefault();
            if (e.shiftKey) {
              updateMatchSelection(currentIndex - 1);
            } else {
              updateMatchSelection(currentIndex + 1);
            }
          }
        }
      });
    }
  }

  /* --- Real-Time Live Log Stream (tail -f) --- */
  var liveBtn  = document.getElementById('btn-live-stream');
  var logTable = document.querySelector('.logtable');
  var logView  = document.querySelector('.logview');

  if (liveBtn && logTable && logView) {
    var isLive        = false;
    var streamTimer   = null;
    var currentOffset = parseInt(liveBtn.getAttribute('data-offset'), 10) || 0;
    var fileRel       = liveBtn.getAttribute('data-file');
    var liveDot       = liveBtn.querySelector('.live-dot');
    var liveLabel     = liveBtn.querySelector('.live-label');

    var getMaxLineNo = function () {
      var firstRow = logTable.querySelector('tbody tr:first-child');
      if (!firstRow) return 0;
      var noTd = firstRow.querySelector('.lineno');
      return noTd ? (parseInt(noTd.textContent, 10) || 0) : 0;
    };

    var appendLogLines = function (lines) {
      if (!lines || !lines.length) return;

      var tbody = logTable.querySelector('tbody');
      if (!tbody) return;

      var currentMaxLine = getMaxLineNo();
      var searchInput = document.getElementById('log-search-input');
      var needle = searchInput ? searchInput.value.trim() : '';

      lines.forEach(function (rawLine, idx) {
        var lineNo = currentMaxLine + idx + 1;
        var tr = document.createElement('tr');
        
        var classes = ['line-live-new'];
        var plain = rawLine.toLowerCase();
        if (plain.indexOf('error') !== -1 || plain.indexOf('fatal') !== -1 || plain.indexOf('exception') !== -1 || plain.indexOf('critical') !== -1) {
          classes.push('line-error');
        } else if (plain.indexOf('warn') !== -1) {
          classes.push('line-warn');
        } else if (plain.indexOf('notice') !== -1 || plain.indexOf('info') !== -1) {
          classes.push('line-info');
        }
        tr.className = classes.join(' ');

        var tdNo = document.createElement('td');
        tdNo.className = 'lineno';
        tdNo.textContent = String(lineNo);

        var tdText = document.createElement('td');
        tdText.className = 'linetext';

        if (needle) {
          try {
            var escapedNeedle = needle.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
            var regex = new RegExp('(' + escapedNeedle + ')', 'gi');
            tdText.innerHTML = rawLine.replace(regex, '<mark>$1</mark>');
          } catch (e) {
            tdText.innerHTML = rawLine;
          }
        } else {
          tdText.innerHTML = rawLine;
        }

        tr.appendChild(tdNo);
        tr.appendChild(tdText);

        // Prepend newest line at the TOP of tbody!
        if (tbody.firstChild) {
          tbody.insertBefore(tr, tbody.firstChild);
        } else {
          tbody.appendChild(tr);
        }
      });

      // Smooth scroll container to the top to follow newest incoming records
      logView.scrollTo({ top: 0, behavior: 'smooth' });
    };

    var pollStream = function () {
      fetch('?page=stream_log&file=' + encodeURIComponent(fileRel) + '&offset=' + currentOffset)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            currentOffset = data.offset;
            liveBtn.setAttribute('data-offset', String(currentOffset));
            if (data.new_lines && data.new_lines.length > 0) {
              appendLogLines(data.new_lines);
            }
          }
        })
        .catch(function (err) {
          console.warn('Live stream polling error:', err);
        });
    };

    var clearLiveHighlights = function () {
      var liveRows = logTable.querySelectorAll('tr.line-live-new');
      Array.prototype.forEach.call(liveRows, function (row) {
        row.classList.remove('line-live-new');
      });
    };

    var toggleLiveStream = function () {
      isLive = !isLive;
      if (isLive) {
        liveBtn.style.background = 'var(--accent-gradient)';
        liveBtn.style.color = '#ffffff';
        liveBtn.style.boxShadow = 'var(--accent-glow)';
        if (liveDot) { liveDot.style.background = '#22c55e'; liveDot.className = 'pulse-dot-green'; }
        if (liveLabel) liveLabel.textContent = I18N.stopLiveStream || '⏸ Pause Live Stream';
        
        logView.scrollTo({ top: 0, behavior: 'smooth' });
        pollStream();
        streamTimer = setInterval(pollStream, 1500);
      } else {
        clearInterval(streamTimer);
        liveBtn.style.background = '';
        liveBtn.style.color = 'var(--accent)';
        liveBtn.style.boxShadow = '';
        if (liveDot) { liveDot.style.background = '#22c55e'; liveDot.className = ''; }
        if (liveLabel) liveLabel.textContent = I18N.startLiveStream || '▶️ Live Stream';
        
        // Revert orange highlights of live records back to normal text color!
        clearLiveHighlights();
      }
    };

    liveBtn.addEventListener('click', toggleLiveStream);
  }

  /* --- Real-Time Live Server Monitor (1-second Interval) --- */
  var liveServerBtn = document.getElementById('btn-live-server');
  if (liveServerBtn) {
    var isLiveServer  = false;
    var serverTimer   = null;
    var psort         = liveServerBtn.getAttribute('data-psort') || 'cpu';
    var liveDot       = liveServerBtn.querySelector('.live-dot');
    var liveLabel     = liveServerBtn.querySelector('.live-label');

    var pollServerMetrics = function () {
      fetch('?page=stream_server&psort=' + encodeURIComponent(psort))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;

          // 1. CPU Usage
          var cpuVal = document.getElementById('srv-cpu-val');
          var cpuBar = document.getElementById('srv-cpu-bar');
          if (cpuVal) cpuVal.textContent = data.cpu_all + '%';
          if (cpuBar) cpuBar.style.width = data.cpu_bar + '%';

          // 2. Load Average
          var loadVal = document.getElementById('srv-load-val');
          var loadBadge = document.getElementById('srv-load-badge');
          if (loadVal) loadVal.textContent = data.load1 + ' · ' + data.load5 + ' · ' + data.load15;
          if (loadBadge) loadBadge.textContent = data.load_pct + '% Load';

          // 3. Memory RAM
          var memVal = document.getElementById('srv-mem-val');
          var memBar = document.getElementById('srv-mem-bar');
          var memBadge = document.getElementById('srv-mem-badge');
          if (memVal) memVal.innerHTML = data.mem_used + ' <small class="muted">/ ' + data.mem_total + '</small>';
          if (memBar) memBar.style.width = data.mem_pct + '%';
          if (memBadge) memBadge.textContent = data.mem_pct + '%';

          // 4. Swap Storage
          var swapVal = document.getElementById('srv-swap-val');
          var swapBar = document.getElementById('srv-swap-bar');
          var swapBadge = document.getElementById('srv-swap-badge');
          if (swapVal) swapVal.innerHTML = data.swap_used + ' <small class="muted">/ ' + data.swap_total + '</small>';
          if (swapBar) swapBar.style.width = data.swap_pct + '%';
          if (swapBadge) swapBadge.textContent = data.swap_pct + '%';

          // 5. Uptime & Active Process Count
          var uptimeNproc = document.getElementById('server-uptime-nproc');
          if (uptimeNproc) {
            uptimeNproc.textContent = (I18N.runningSince || 'شغال منذ') + ' ' + data.uptime + ' · ' + data.nproc + ' ' + (I18N.activeProcess || 'عملية نشطة');
          }

          // 6. Individual Cores
          if (data.cores && data.cores.length > 0) {
            data.cores.forEach(function (c) {
              var coreVal = document.getElementById('srv-core-val-' + c.id);
              var coreBar = document.getElementById('srv-core-bar-' + c.id);
              if (coreVal) coreVal.textContent = c.pct + '%';
              if (coreBar) coreBar.style.width = Math.min(100, c.pct) + '%';
            });
          }

          // 7. Top Active Processes Table
          var procsTbody = document.getElementById('srv-procs-tbody');
          if (procsTbody && data.procs && data.procs.length > 0) {
            var html = '';
            data.procs.forEach(function (p) {
              html += '<tr>' +
                '<td class="col-num mono">' + p.pid + '</td>' +
                '<td class="mono ltr">' + p.user + '</td>' +
                '<td class="col-num mono"><strong style="color: var(--text);">' + p.cpu + '</strong></td>' +
                '<td class="col-num mono">' + p.mem + '</td>' +
                '<td class="col-num mono">' + p.rss + '</td>' +
                '<td class="mono ltr">' + p.stat + '</td>' +
                '<td class="mono ltr">' + p.etime + '</td>' +
                '<td style="max-width: 320px;"><code class="mono ltr" style="font-size: 0.8rem; word-break: break-all; opacity: 0.85; color: var(--text);">' + p.cmd + '</code></td>' +
              '</tr>';
            });
            procsTbody.innerHTML = html;
          }
        })
        .catch(function (err) {
          console.warn('Live server metrics polling error:', err);
        });
    };

    var toggleLiveServer = function () {
      isLiveServer = !isLiveServer;
      if (isLiveServer) {
        liveServerBtn.style.background = 'var(--accent-gradient)';
        liveServerBtn.style.color = '#ffffff';
        liveServerBtn.style.boxShadow = 'var(--accent-glow)';
        if (liveDot) { liveDot.style.background = '#22c55e'; liveDot.className = 'pulse-dot-green'; }
        if (liveLabel) liveLabel.textContent = I18N.stopLiveServer || '⏸ Pause Live Refresh';

        pollServerMetrics();
        serverTimer = setInterval(pollServerMetrics, 1000);
      } else {
        clearInterval(serverTimer);
        liveServerBtn.style.background = '';
        liveServerBtn.style.color = 'var(--accent)';
        liveServerBtn.style.boxShadow = '';
        if (liveDot) { liveDot.style.background = '#22c55e'; liveDot.className = ''; }
        if (liveLabel) liveLabel.textContent = I18N.startLiveServer || '▶️ Live Refresh (1 sec)';
      }
    };

    liveServerBtn.addEventListener('click', toggleLiveServer);
  }
})();
