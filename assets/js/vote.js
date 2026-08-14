(function () {
  const cfg = window.HCS_VOTE || {};
  const form = document.getElementById('votingForm');
  if (!form) return;

  const msg = document.getElementById('instructionMsg');
  const spinner = document.getElementById('spinner');
  const confirmation = document.getElementById('confirmation');
  const voterTypeEl = document.getElementById('voterType');
  const passcodeEl = document.getElementById('passcode');
  const passcodeError = document.getElementById('passcodeError');
  const startBtn = document.getElementById('startBtn');
  const stepStart = document.getElementById('stepStart');
  const stepVote = document.getElementById('stepVote');
  const stepReview = document.getElementById('stepReview');
  const stepTitle = document.getElementById('stepTitle');
  const candidateGrid = document.getElementById('candidateGrid');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const reviewBackBtn = document.getElementById('reviewBackBtn');
  const reviewList = document.getElementById('reviewList');
  const submitBtn = document.getElementById('submitBtn');
  const progressLabel = document.getElementById('progressLabel');
  const progressCount = document.getElementById('progressCount');
  const progressFill = document.getElementById('progressFill');

  let positions = [];
  let stepIndex = 0; // index into positions
  let selections = {}; // positionId -> { candidateId, name, class, photo }
  let mode = 'start'; // start | vote | review | done

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

  function setMsg(text, type) {
    if (!msg) return;
    msg.textContent = text;
    msg.classList.remove('warn', 'ready', 'err', 'ok');
    msg.classList.add(type || 'warn');
  }

  function doneCount() {
    return Object.keys(selections).length;
  }

  function updateProgress() {
    const total = positions.length;
    const done = doneCount();
    const current = mode === 'vote' ? stepIndex + 1 : mode === 'review' ? total : 0;
    const pct = total ? Math.round((done / total) * 100) : 0;

    if (progressFill) progressFill.style.width = pct + '%';
    if (progressCount) {
      progressCount.textContent = done + ' / ' + total + ' done';
    }
    if (progressLabel) {
      if (mode === 'start') progressLabel.textContent = 'Ready to begin';
      else if (mode === 'vote') progressLabel.textContent = 'Position ' + current + ' of ' + total;
      else if (mode === 'review') progressLabel.textContent = 'Review & submit';
      else if (mode === 'done') progressLabel.textContent = 'Completed';
    }
  }

  function showMode(next) {
    mode = next;
    stepStart.hidden = next !== 'start';
    stepVote.hidden = next !== 'vote';
    stepReview.hidden = next !== 'review';
    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function currentSelection() {
    const pos = positions[stepIndex];
    return pos ? selections[pos.id] : null;
  }

  function renderStep() {
    const pos = positions[stepIndex];
    if (!pos) return;

    stepTitle.textContent = pos.name;
    candidateGrid.innerHTML = '';
    const selectedId = selections[pos.id] ? selections[pos.id].candidateId : null;

    pos.candidates.forEach((c) => {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'candidate-card candidate-card-compact';
      if (selectedId === c.id) card.classList.add('selected');
      card.innerHTML =
        '<img src="' + escapeAttr(c.photo) + '" alt="" loading="lazy">' +
        '<div class="candidate-meta">' +
          '<strong>' + escapeHtml(c.name) + '</strong>' +
          '<span class="muted">' + escapeHtml(c.class || '') + '</span>' +
        '</div>';

      card.addEventListener('click', () => {
        selections[pos.id] = {
          candidateId: c.id,
          name: c.name,
          class: c.class || '',
          photo: c.photo
        };
        candidateGrid.querySelectorAll('.candidate-card').forEach((el) => el.classList.remove('selected'));
        card.classList.add('selected');
        nextBtn.disabled = false;
        setMsg('Selected. Tap Next to continue.', 'ready');
        updateProgress();
      });

      candidateGrid.appendChild(card);
    });

    prevBtn.textContent = stepIndex === 0 ? 'Back' : 'Back';
    nextBtn.textContent = stepIndex === positions.length - 1 ? 'Review' : 'Next';
    nextBtn.disabled = !selections[pos.id];
    setMsg('Choose one candidate for this position.', 'warn');
    updateProgress();
  }

  function renderReview() {
    reviewList.innerHTML = '';
    positions.forEach((pos, idx) => {
      const sel = selections[pos.id];
      const row = document.createElement('div');
      row.className = 'review-row';
      row.innerHTML =
        '<div class="review-pos">' +
          '<strong>' + escapeHtml(pos.name) + '</strong>' +
          (sel
            ? '<span>' + escapeHtml(sel.name) + ' · ' + escapeHtml(sel.class) + '</span>'
            : '<span class="err-text">Not selected</span>') +
        '</div>' +
        '<button type="button" class="btn secondary review-edit" data-idx="' + idx + '">Edit</button>';
      reviewList.appendChild(row);
    });

    reviewList.querySelectorAll('.review-edit').forEach((btn) => {
      btn.addEventListener('click', () => {
        stepIndex = Number(btn.getAttribute('data-idx')) || 0;
        showMode('vote');
        renderStep();
      });
    });

    const allDone = positions.every((p) => selections[p.id]);
    submitBtn.disabled = !allDone;
    setMsg(allDone ? 'Check your choices, then submit.' : 'Some positions are missing. Tap Edit.', allDone ? 'ready' : 'warn');
    updateProgress();
  }

  function canStart() {
    if (cfg.requirePasscode) {
      return !!(passcodeEl && passcodeEl.value.trim());
    }
    return !!(voterTypeEl && voterTypeEl.value);
  }

  function syncStartBtn() {
    if (startBtn && !cfg.requirePasscode) {
      startBtn.disabled = !canStart();
    }
  }

  if (voterTypeEl) {
    voterTypeEl.addEventListener('change', syncStartBtn);
  }
  if (passcodeEl) {
    passcodeEl.addEventListener('input', function () {
      if (passcodeError) passcodeError.hidden = true;
    });
  }

  startBtn.addEventListener('click', function () {
    if (!canStart()) {
      setMsg(cfg.requirePasscode ? 'Enter passcode first.' : 'Select voter type first.', 'warn');
      return;
    }
    if (!positions.length) {
      setMsg('No candidates loaded yet. Please wait…', 'warn');
      return;
    }
    stepIndex = 0;
    showMode('vote');
    renderStep();
  });

  prevBtn.addEventListener('click', function () {
    if (stepIndex <= 0) {
      showMode('start');
      setMsg(cfg.requirePasscode ? 'Enter passcode, then continue.' : 'Select voter type, then continue.', 'warn');
      return;
    }
    stepIndex -= 1;
    renderStep();
  });

  nextBtn.addEventListener('click', function () {
    const pos = positions[stepIndex];
    if (!pos || !selections[pos.id]) {
      setMsg('Please select a candidate first.', 'warn');
      return;
    }
    if (stepIndex >= positions.length - 1) {
      showMode('review');
      renderReview();
      return;
    }
    stepIndex += 1;
    renderStep();
  });

  reviewBackBtn.addEventListener('click', function () {
    stepIndex = positions.length - 1;
    showMode('vote');
    renderStep();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (passcodeError) passcodeError.hidden = true;

    const voterType = cfg.voterType || (voterTypeEl && voterTypeEl.value);
    if (!voterType) {
      alert('Please select voter type.');
      showMode('start');
      return;
    }

    const missing = positions.filter((p) => !selections[p.id]);
    if (missing.length) {
      alert('Please select one candidate for every position.');
      stepIndex = positions.findIndex((p) => !selections[p.id]);
      showMode('vote');
      renderStep();
      return;
    }

    const votes = positions.map((p) => ({
      position_id: p.id,
      candidate_id: selections[p.id].candidateId
    }));

    const payload = { voter_type: voterType, votes: votes };
    if (cfg.accessToken) payload.access_token = cfg.accessToken;
    if (cfg.requirePasscode) payload.passcode = passcodeEl ? passcodeEl.value.trim() : '';

    submitBtn.disabled = true;
    spinner.hidden = false;

    api('submit_vote', payload)
      .then((data) => {
        spinner.hidden = true;
        confirmation.hidden = false;
        confirmation.textContent = data.message || 'Vote submitted!';
        selections = {};
        mode = 'done';
        updateProgress();
        stepStart.hidden = true;
        stepVote.hidden = true;
        stepReview.hidden = true;
        setMsg('Thank you! Your vote was recorded.', 'ready');

        // Public kiosk: reset so the next student/staff can vote on this same device.
        if (!cfg.requirePasscode) {
          setTimeout(function () {
            confirmation.hidden = true;
            if (voterTypeEl) voterTypeEl.value = '';
            submitBtn.disabled = false;
            showMode('start');
            syncStartBtn();
            setMsg('Vote recorded. Next voter can start.', 'ok');
          }, 2500);
        }
      })
      .catch((err) => {
        spinner.hidden = true;
        submitBtn.disabled = false;
        if (cfg.requirePasscode && /passcode/i.test(err.message) && passcodeError) {
          passcodeError.hidden = false;
          showMode('start');
        } else if (/already voted/i.test(err.message)) {
          alert(err.message);
          setMsg(err.message, 'warn');
        } else {
          alert(err.message);
        }
      });
  });

  setMsg(cfg.requirePasscode ? 'Enter passcode to start.' : 'Select Student or Staff to start.', 'warn');
  syncStartBtn();
  updateProgress();

  api('get_candidates')
    .then((data) => {
      positions = data.positions || [];
      updateProgress();
      if (!positions.length) {
        setMsg('No candidates available for voting.', 'warn');
        startBtn.disabled = true;
      }
    })
    .catch((err) => {
      setMsg(err.message, 'warn');
      startBtn.disabled = true;
    });
})();
