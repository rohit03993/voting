(function () {
  const cfg = window.HCS_VOTE || {};
  const positionsEl = document.getElementById('positions');
  const form = document.getElementById('votingForm');
  const submitBtn = document.getElementById('submitBtn');
  const msg = document.getElementById('instructionMsg');
  const spinner = document.getElementById('spinner');
  const confirmation = document.getElementById('confirmation');
  const voterTypeEl = document.getElementById('voterType');
  const passcodeEl = document.getElementById('passcode');
  const passcodeError = document.getElementById('passcodeError');

  let positions = [];

  function api(action, body) {
    return fetch(cfg.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action }, body || {}))
    }).then(async (res) => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        throw new Error(data.error || 'Request failed');
      }
      return data;
    });
  }

  function checkSelections() {
    if (!positions.length) return;
    const allSelected = positions.every((p) =>
      document.querySelector('input[name="pos_' + p.id + '"]:checked')
    );
    const voterReady = cfg.voterType || (voterTypeEl && voterTypeEl.value);
    if (allSelected && voterReady) {
      submitBtn.hidden = false;
      msg.textContent = 'All selections complete! You can now submit your vote.';
      msg.classList.add('ready');
      msg.classList.remove('warn');
    } else {
      submitBtn.hidden = true;
      msg.textContent = 'Please select one candidate from each category to enable Submit.';
      msg.classList.remove('ready');
      msg.classList.add('warn');
    }
  }

  function render(data) {
    positions = data.positions || [];
    positionsEl.innerHTML = '';
    positions.forEach((pos) => {
      const section = document.createElement('div');
      section.innerHTML = '<h3>' + escapeHtml(pos.name) + '</h3>';
      const grid = document.createElement('div');
      grid.className = 'candidate-section';

      pos.candidates.forEach((c) => {
        const card = document.createElement('label');
        card.className = 'candidate-card';
        card.innerHTML =
          '<input type="radio" name="pos_' + pos.id + '" value="' + c.id + '" required>' +
          '<img src="' + escapeAttr(c.photo) + '" alt="' + escapeAttr(c.name) + '" loading="lazy">' +
          '<div><strong>' + escapeHtml(c.name) + '</strong></div>' +
          '<div class="muted">' + escapeHtml(c.class || '') + '</div>';

        card.addEventListener('click', () => {
          grid.querySelectorAll('.candidate-card').forEach((el) => el.classList.remove('selected'));
          card.classList.add('selected');
          checkSelections();
        });
        grid.appendChild(card);
      });

      section.appendChild(grid);
      positionsEl.appendChild(section);
    });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
  }

  api('get_candidates')
    .then(render)
    .catch((err) => {
      positionsEl.innerHTML = '<div class="alert err">' + escapeHtml(err.message) + '</div>';
    });

  if (voterTypeEl) {
    voterTypeEl.addEventListener('change', checkSelections);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (passcodeError) passcodeError.hidden = true;

    const voterType = cfg.voterType || (voterTypeEl && voterTypeEl.value);
    if (!voterType) {
      alert('Please select voter type.');
      return;
    }

    const votes = positions.map((p) => {
      const selected = document.querySelector('input[name="pos_' + p.id + '"]:checked');
      return selected
        ? { position_id: p.id, candidate_id: Number(selected.value) }
        : null;
    });

    if (votes.some((v) => !v)) {
      alert('Please select one candidate for every position.');
      return;
    }

    const payload = {
      voter_type: voterType,
      votes: votes
    };
    if (cfg.accessToken) payload.access_token = cfg.accessToken;
    if (cfg.requirePasscode) payload.passcode = passcodeEl ? passcodeEl.value.trim() : '';

    submitBtn.hidden = true;
    spinner.hidden = false;

    api('submit_vote', payload)
      .then((data) => {
        spinner.hidden = true;
        confirmation.hidden = false;
        confirmation.textContent = data.message || 'Vote submitted!';
        form.querySelectorAll('input[type="radio"]').forEach((input) => {
          input.checked = false;
          input.parentElement.classList.remove('selected');
        });
        if (passcodeEl) passcodeEl.value = '';
        checkSelections();
        setTimeout(() => { confirmation.hidden = true; }, 3500);
      })
      .catch((err) => {
        spinner.hidden = true;
        if (cfg.requirePasscode && /passcode/i.test(err.message) && passcodeError) {
          passcodeError.hidden = false;
        } else {
          alert(err.message);
        }
        checkSelections();
      });
  });
})();
