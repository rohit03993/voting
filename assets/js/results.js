(function () {
  const cfg = window.HCS_RESULTS || {};
  const winnersEl = document.getElementById('winners');
  const resultsEl = document.getElementById('results');
  const totalsEl = document.getElementById('totals');
  const statusLine = document.getElementById('statusLine');
  const totalsSection = totalsEl ? totalsEl.closest('.panel') : null;

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function render(data) {
    const hide = !!data.hide_counts;
    const decidedBy = data.decided_by; // principal | director | null

    let statusExtra = '';
    if (decidedBy === 'director') {
      statusExtra = ' — Final winners set by Director';
    } else if (decidedBy === 'principal') {
      statusExtra = ' — Final winners set by Principal';
    } else if (hide) {
      statusExtra = ' — Vote counts hidden';
    }

    statusLine.textContent = (data.title || 'Election')
      + ' — Status: ' + String(data.status || 'n/a').toUpperCase()
      + statusExtra;

    // Hide S/St/P/D ballot totals after Principal/Director vote
    if (totalsSection) {
      totalsSection.hidden = hide;
    }
    if (!hide) {
      const totals = data.ballot_totals || {};
      totalsEl.innerHTML = ['student', 'staff', 'principal', 'director'].map((k) => {
        return '<div class="stat"><strong>' + (totals[k] || 0) + '</strong><span>' + k + '</span></div>';
      }).join('');
    } else {
      totalsEl.innerHTML = '';
    }

    winnersEl.innerHTML = '';
    Object.keys(data.winners || {}).forEach((pos) => {
      const w = data.winners[pos];
      const votes = (!hide && w.votes != null) ? (w.votes + ' votes') : '';
      const card = document.createElement('div');
      card.className = 'winner-card';
      card.innerHTML =
        '<h3>' + escapeHtml(pos) + '</h3>' +
        '<img src="' + escapeHtml(w.photo) + '" alt="">' +
        '<strong>' + escapeHtml(w.name) + '</strong>' +
        '<div class="muted">' + escapeHtml(w.class || '') + '</div>' +
        (votes ? '<div>' + escapeHtml(votes) + '</div>' : '');
      winnersEl.appendChild(card);
    });

    resultsEl.innerHTML = '';
    Object.keys(data.results || {}).forEach((pos) => {
      const section = document.createElement('div');
      section.className = 'result-section';
      section.innerHTML = '<h3>' + escapeHtml(pos) + '</h3>';
      const grid = document.createElement('div');
      grid.className = 'results-grid';
      (data.results[pos] || []).forEach((c) => {
        const votes = (!hide && c.votes != null) ? (c.votes + ' votes') : '';
        // Never show S/St/P/D breakdown after special vote
        let breakdown = '';
        if (!hide && c.breakdown) {
          breakdown = '<div class="muted" style="font-size:.8rem">S ' + c.breakdown.student
            + ' · St ' + c.breakdown.staff
            + ' · P ' + c.breakdown.principal
            + ' · D ' + c.breakdown.director + '</div>';
        }
        const card = document.createElement('div');
        card.className = 'result-card' + (c.is_winner ? ' is-winner' : '');
        card.innerHTML =
          '<img src="' + escapeHtml(c.photo) + '" alt="">' +
          '<strong>' + escapeHtml(c.name) + '</strong>' +
          '<div class="muted">' + escapeHtml(c.class || '') + '</div>' +
          (votes ? '<div>' + escapeHtml(votes) + '</div>' : '') +
          (c.is_winner && hide ? '<div class="winner-tag">Winner</div>' : '') +
          breakdown;
        grid.appendChild(card);
      });
      section.appendChild(grid);
      resultsEl.appendChild(section);
    });
  }

  function load() {
    fetch(cfg.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_results' })
    })
      .then((r) => r.json())
      .then(render)
      .catch(() => {
        statusLine.textContent = 'Could not load results.';
      });
  }

  load();
  setInterval(load, 4000);
})();
