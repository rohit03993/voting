(function () {
  const cfg = window.HCS_RESULTS || {};
  const winnersEl = document.getElementById('winners');
  const resultsEl = document.getElementById('results');
  const totalsEl = document.getElementById('totals');
  const statusLine = document.getElementById('statusLine');

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function render(data) {
    statusLine.textContent = (data.title || 'Election') + ' — Status: ' + String(data.status || 'n/a').toUpperCase()
      + (data.hide_counts ? ' (vote counts hidden after Principal/Director vote)' : '');

    const totals = data.ballot_totals || {};
    totalsEl.innerHTML = ['student', 'staff', 'principal', 'director'].map((k) => {
      return '<div class="stat"><strong>' + (totals[k] || 0) + '</strong><span>' + k + '</span></div>';
    }).join('');

    winnersEl.innerHTML = '';
    Object.keys(data.winners || {}).forEach((pos) => {
      const w = data.winners[pos];
      const votes = w.votes == null ? '' : (w.votes + ' votes');
      const card = document.createElement('div');
      card.className = 'winner-card';
      card.innerHTML =
        '<h3>' + escapeHtml(pos) + '</h3>' +
        '<img src="' + escapeHtml(w.photo) + '" alt="">' +
        '<strong>' + escapeHtml(w.name) + '</strong>' +
        '<div class="muted">' + escapeHtml(w.class || '') + '</div>' +
        '<div>' + escapeHtml(votes) + '</div>';
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
        const votes = c.votes == null ? '' : (c.votes + ' votes');
        let breakdown = '';
        if (c.breakdown) {
          breakdown = '<div class="muted" style="font-size:.8rem">S ' + c.breakdown.student
            + ' · St ' + c.breakdown.staff
            + ' · P ' + c.breakdown.principal
            + ' · D ' + c.breakdown.director + '</div>';
        }
        const card = document.createElement('div');
        card.className = 'result-card';
        card.innerHTML =
          '<img src="' + escapeHtml(c.photo) + '" alt="">' +
          '<strong>' + escapeHtml(c.name) + '</strong>' +
          '<div class="muted">' + escapeHtml(c.class || '') + '</div>' +
          '<div>' + escapeHtml(votes) + '</div>' + breakdown;
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
