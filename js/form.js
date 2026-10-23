/* ============================================================
   PROGRAMME APD (ODA) — Logique du formulaire d'inscription
   - Lignes dynamiques « Langues maîtrisées »
   - Sauvegarde automatique locale (brouillon conservé)
   - Validation complète avec fenêtre modale centrée
   - Marquage visuel des champs remplis (couleur uniforme)
   - Notification élégante pour les fichiers refusés
   - Soumission : archivage serveur + ouverture mailto avec ID
   ============================================================ */
(function () {
  'use strict';

  var DRAFT_KEY = 'girh_form_draft_v1';
  var form = document.getElementById('registrationForm');
  if (!form) { return; }

  var t = function (key) {
    return (window.GIRH_I18N && window.GIRH_I18N.t) ? window.GIRH_I18N.t(key) : key;
  };

  /* ---------------------------------------------------------
     1. LANGUES MAÎTRISÉES — lignes dynamiques
  --------------------------------------------------------- */
  var langRowsWrap = document.getElementById('langRows');
  var addLangBtn = document.getElementById('addLangBtn');
  var langCount = 0;

  function makeLangRow(value, checks) {
    langCount++;
    var idx = langCount;
    var row = document.createElement('div');
    row.className = 'lang-row';
    row.innerHTML =
      '<input type="text" name="langue_nom[]" data-langname placeholder="" autocomplete="off">' +
      '<div class="lang-checks">' +
        '<label><input type="checkbox" name="langue_lu_' + idx + '" value="1"><span data-lk="f.lu">Lu</span></label>' +
        '<label><input type="checkbox" name="langue_parle_' + idx + '" value="1"><span data-lk="f.parle">Parlé</span></label>' +
        '<label><input type="checkbox" name="langue_ecrit_' + idx + '" value="1"><span data-lk="f.ecrit">Écrit</span></label>' +
      '</div>' +
      '<button type="button" class="lang-del" title="Retirer">×</button>';

    var input = row.querySelector('[data-langname]');
    input.placeholder = t('f.langPh');
    if (value) { input.value = value; }

    var boxes = row.querySelectorAll('.lang-checks input');
    for (var i = 0; i < boxes.length; i++) {
      if (checks && checks[i]) { boxes[i].checked = true; }
      boxes[i].addEventListener('change', function () {
        this.closest('label').classList.toggle('on', this.checked);
        saveDraft();
        refreshFilled();
      });
      boxes[i].closest('label').classList.toggle('on', boxes[i].checked);
    }
    input.addEventListener('input', function () { saveDraft(); refreshFilled(); });

    row.querySelector('.lang-del').addEventListener('click', function () {
      if (langRowsWrap.querySelectorAll('.lang-row').length > 1) {
        row.remove();
      } else {
        input.value = '';
        var bx = row.querySelectorAll('.lang-checks input');
        for (var j = 0; j < bx.length; j++) { bx[j].checked = false; bx[j].closest('label').classList.remove('on'); }
      }
      saveDraft();
      refreshFilled();
    });

    langRowsWrap.appendChild(row);
    refreshLangLabels();
    return row;
  }

  function refreshLangLabels() {
    var spans = langRowsWrap.querySelectorAll('[data-lk]');
    for (var i = 0; i < spans.length; i++) {
      spans[i].textContent = t(spans[i].getAttribute('data-lk'));
    }
    var inputs = langRowsWrap.querySelectorAll('[data-langname]');
    for (var j = 0; j < inputs.length; j++) {
      inputs[j].placeholder = t('f.langPh');
    }
  }

  addLangBtn.addEventListener('click', function () {
    makeLangRow('', null);
    saveDraft();
    refreshFilled();
  });

  document.addEventListener('girh:langchange', refreshLangLabels);

  /* ---------------------------------------------------------
     2. FICHIERS — affichage du nom + contrôle de type
     Types acceptés : JPG, JPEG, PNG, DOC, DOCX, PDF
     accept= avec extensions explicites (pas de wildcard image/*)
     → ouvre le gestionnaire de fichiers, jamais la galerie.
  --------------------------------------------------------- */
  var ACCEPTED_EXTS = ['.jpg', '.jpeg', '.png', '.doc', '.docx', '.pdf'];
  var ACCEPTED_EXTS_MSG = 'JPG, JPEG, PNG, DOC, DOCX, PDF';
  var FILE_RULES = {
    photo:    { errKey: 'err.fileType' },
    cv:       { errKey: 'err.fileType' },
    lettre:   { errKey: 'err.fileType' },
    identite: { errKey: 'err.fileType' }
  };

  function extOf(name) {
    var p = name.lastIndexOf('.');
    return p >= 0 ? name.slice(p + 1).toLowerCase() : '';
  }

  function acceptedExt(name) {
    var e = '.' + extOf(name);
    return ACCEPTED_EXTS.indexOf(e.toLowerCase()) >= 0;
  }

  /* Toast élégant (remplace le alert() navigateur) */
  var toastTimer = null;
  var toastEl = null;
  function showToast(msg) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'girh-toast';
      toastEl.setAttribute('role', 'alert');
      document.body.appendChild(toastEl);
    }
    clearTimeout(toastTimer);
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    toastTimer = setTimeout(function () {
      toastEl.classList.remove('show');
    }, 4000);
  }

  Object.keys(FILE_RULES).forEach(function (id) {
    var input = document.getElementById(id);
    if (!input) { return; }
    // Extensions explicites (pas de wildcard) —
// force le gestionnaire de fichiers (pas la galerie)
    input.setAttribute('accept', '.jpg,.jpeg,.png,.doc,.docx,.pdf');
    input.addEventListener('change', function () {
      var box = input.closest('.file-box');
      var nameEl = box.querySelector('.file-name');
      if (input.files.length) {
        if (!acceptedExt(input.files[0].name)) {
          nameEl.textContent = '';
          input.value = '';
          input.closest('.field').classList.add('invalid');
          var lab = input.closest('.field').querySelector('label');
          var fieldLabel = lab ? lab.textContent.replace(/\s*\*\s*$/, '').trim() : '';
          showToast(t('err.fileTypeFull')
            .replace('{field}', fieldLabel)
            .replace('{exts}', ACCEPTED_EXTS_MSG));
          refreshFilled();
          return;
        }
        nameEl.textContent = input.files[0].name;
        nameEl.style.color = '';
        nameEl.removeAttribute('data-i18n');
      } else {
        nameEl.textContent = '';
        nameEl.style.color = '';
      }
      input.closest('.field').classList.remove('invalid');
      refreshFilled();
    });
  });

  /* ---------------------------------------------------------
     2 bis. PREMIER CHAMP E-MAIL : SÉLECTION PRIORITAIRE
  --------------------------------------------------------- */
  (function () {
    var emailInput = document.getElementById('email');
    var manualLink = document.getElementById('emailManual');
    if (!emailInput) { return; }
    var manualMode = false;

    function blockTyping(e) {
      if (manualMode) { return; }
      if (e.type === 'keydown') {
        var allowed = ['Tab', 'Shift', 'Escape', 'Enter', 'ArrowUp', 'ArrowDown'];
        if (allowed.indexOf(e.key) === -1) { e.preventDefault(); }
      } else {
        e.preventDefault();
      }
    }
    emailInput.addEventListener('keydown', blockTyping);
    emailInput.addEventListener('paste', blockTyping);
    emailInput.addEventListener('drop', blockTyping);

    if (manualLink) {
      manualLink.addEventListener('click', function (e) {
        e.preventDefault();
        manualMode = true;
        manualLink.style.display = 'none';
        emailInput.placeholder = '';
        emailInput.focus();
      });
    }
  })();

  /* ---------------------------------------------------------
     3. RADIOS / CASES — habillage visuel
  --------------------------------------------------------- */
  var choiceInputs = form.querySelectorAll('.choice input');
  function refreshChoices() {
    for (var i = 0; i < choiceInputs.length; i++) {
      choiceInputs[i].closest('.choice').classList.toggle('checked', choiceInputs[i].checked);
    }
    refreshFilled();
  }
  for (var ci = 0; ci < choiceInputs.length; ci++) {
    choiceInputs[ci].addEventListener('change', function () {
      refreshChoices();
      this.closest('.field').classList.remove('invalid');
      saveDraft();
    });
  }

  /* ---------------------------------------------------------
     4. BROUILLON AUTOMATIQUE (localStorage)
  --------------------------------------------------------- */
  var saveTimer = null;

  function collectDraft() {
    var data = { fields: {}, radios: {}, langs: [], cert: false };
    var els = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], textarea, select');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.hasAttribute('data-langname')) { continue; }
      if (el.name) { data.fields[el.name] = el.value; }
    }
    var radios = form.querySelectorAll('input[type="radio"]:checked');
    for (var r = 0; r < radios.length; r++) {
      data.radios[radios[r].name] = radios[r].value;
    }
    var rows = langRowsWrap.querySelectorAll('.lang-row');
    for (var lr = 0; lr < rows.length; lr++) {
      var name = rows[lr].querySelector('[data-langname]').value;
      var boxes = rows[lr].querySelectorAll('.lang-checks input');
      data.langs.push({
        n: name,
        c: [boxes[0].checked, boxes[1].checked, boxes[2].checked]
      });
    }
    var cert = document.getElementById('certification');
    data.cert = cert ? cert.checked : false;
    return data;
  }

  function saveDraft() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(collectDraft()));
      } catch (e) {}
    }, 250);
  }

  function saveDraftNow() {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(collectDraft()));
    } catch (e) {}
  }

  function restoreDraft() {
    var raw = null;
    try { raw = localStorage.getItem(DRAFT_KEY); } catch (e) {}
    var data = null;
    if (raw) {
      try { data = JSON.parse(raw); } catch (e) { data = null; }
    }

    if (data && data.fields) {
      Object.keys(data.fields).forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el && !el.hasAttribute('data-langname')) { el.value = data.fields[name]; }
      });
    }
    if (data && data.radios) {
      Object.keys(data.radios).forEach(function (name) {
        var el = form.querySelector('input[type="radio"][name="' + name + '"][value="' + data.radios[name] + '"]');
        if (el) { el.checked = true; }
      });
    }
    if (data && data.langs && data.langs.length) {
      for (var i = 0; i < data.langs.length; i++) {
        makeLangRow(data.langs[i].n, data.langs[i].c);
      }
    } else {
      makeLangRow('', null);
    }
    var cert = document.getElementById('certification');
    if (cert && data) { cert.checked = !!data.cert; }
    refreshChoices();
    refreshFilled();
  }

  form.addEventListener('input', function () { saveDraft(); refreshFilled(); });
  form.addEventListener('change', function () { saveDraft(); refreshFilled(); });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') { saveDraftNow(); }
  });
  window.addEventListener('pagehide', saveDraftNow);
  window.addEventListener('beforeunload', saveDraftNow);

  /* ---------------------------------------------------------
     5. MARQUAGE VISUEL DES CHAMPS REMPLIS
  --------------------------------------------------------- */
  function refreshFilled() {
    var fields = form.querySelectorAll('.field[data-field]');
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      var key = f.getAttribute('data-field');

      if (f.classList.contains('invalid')) { f.classList.remove('filled'); continue; }

      var complete = false;

      if (FILE_RULES[key]) {
        var fin = document.getElementById(key);
        complete = !!(fin && fin.files.length);
      } else if (key === 'sexe' || key === 'apt_facteurs' || key === 'depl_facteurs' || key === 'autre_nationalite' || key === 'judiciaire') {
        complete = !!form.querySelector('input[name="' + key + '"]:checked');
      } else if (key === 'certification') {
        var cert = document.getElementById('certification');
        complete = !!(cert && cert.checked);
      } else if (key === 'langues') {
        var rows = langRowsWrap.querySelectorAll('.lang-row');
        for (var lr = 0; lr < rows.length; lr++) {
          var nm = rows[lr].querySelector('[data-langname]').value.trim();
          var bx = rows[lr].querySelectorAll('.lang-checks input:checked');
          if (nm !== '' && bx.length > 0) { complete = true; break; }
        }
      } else {
        var input = f.querySelector('input, textarea, select');
        if (input) {
          var v = input.value.trim();
          complete = v !== '';
          if (complete && input.type === 'email') {
            complete = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
            if (complete && key === 'email_confirme') {
              var first = document.getElementById('email');
              if (first) {
                complete = first.value.trim().toLowerCase() === v.toLowerCase();
              }
            }
          }
        }
      }

      if (complete) { f.classList.add('filled'); }
      else { f.classList.remove('filled'); }
    }
  }

  /* ---------------------------------------------------------
     6. FENÊTRE MODALE CENTRÉE
  --------------------------------------------------------- */
  var overlay = document.getElementById('modalOverlay');
  var card = document.getElementById('modalCard');
  var mIcon = document.getElementById('modalIcon');
  var mTitle = document.getElementById('modalTitle');
  var mBody = document.getElementById('modalBody');
  var mClose = document.getElementById('modalClose');

  function openModal(kind, title, bodyHTML) {
    mClose.style.display = '';
    card.classList.remove('is-error', 'is-success');
    card.classList.add(kind === 'success' ? 'is-success' : 'is-error');
    mIcon.textContent = kind === 'success' ? '✓' : '!';
    mTitle.textContent = title;
    mBody.innerHTML = bodyHTML;
    overlay.classList.add('open');
    mClose.textContent = t('modal.close');
    mClose.focus();
  }
  function closeModal() { overlay.classList.remove('open'); }
  mClose.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeModal(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeModal(); } });

  /* ---------------------------------------------------------
     7. VALIDATION
  --------------------------------------------------------- */
  function fieldLabelText(fieldEl) {
    var lab = fieldEl.querySelector('label:not(.file-box):not(.choice):not(.cert-box), .q-label');
    if (!lab) { lab = fieldEl.querySelector('label'); }
    var txt = lab ? lab.textContent : fieldEl.getAttribute('data-field');
    return txt.replace(/\s*\*\s*$/, '').replace(/\s+/g, ' ').trim();
  }

  function validate() {
    var problems = [];
    var firstInvalid = null;

    var fields = form.querySelectorAll('.field[data-field]');
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      var key = f.getAttribute('data-field');
      f.classList.remove('invalid');
      var ok = true;
      var extra = null;

      if (FILE_RULES[key]) {
        var fin = document.getElementById(key);
        if (!fin.files.length) { ok = false; }
      } else if (key === 'sexe' || key === 'apt_facteurs' || key === 'depl_facteurs' || key === 'autre_nationalite' || key === 'judiciaire') {
        ok = !!form.querySelector('input[name="' + key + '"]:checked');
      } else if (key === 'certification') {
        ok = document.getElementById('certification').checked;
        if (!ok) { extra = t('err.cert'); }
      } else if (key === 'langues') {
        var rows = langRowsWrap.querySelectorAll('.lang-row');
        var langOk = false;
        for (var lr = 0; lr < rows.length; lr++) {
          var nm = rows[lr].querySelector('[data-langname]').value.trim();
          var bx = rows[lr].querySelectorAll('.lang-checks input:checked');
          if (nm !== '' && bx.length > 0) { langOk = true; break; }
        }
        ok = langOk;
        if (!ok) { extra = t('err.langRow'); }
      } else {
        var input = f.querySelector('input, textarea, select');
        if (input) {
          ok = input.value.trim() !== '';
          if (ok && input.type === 'email') {
            var v = input.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) {
              ok = false; extra = t('err.email');
            } else if (key === 'email_confirme') {
              var first = document.getElementById('email');
              if (first && first.value.trim().toLowerCase() !== v.toLowerCase()) {
                ok = false; extra = t('err.emailMatch');
              }
            }
          }
        }
      }

      if (!ok) {
        f.classList.add('invalid');
        f.classList.remove('filled');
        if (!firstInvalid) { firstInvalid = f; }
        problems.push(extra ? (fieldLabelText(f) + ' — ' + extra) : fieldLabelText(f));
      }
    }

    return { problems: problems, firstInvalid: firstInvalid };
  }

  /* ---------------------------------------------------------
     8. SOUMISSION — ARCHIVAGE SERVEUR + OUVERTURE MAILTO
  --------------------------------------------------------- */
  var submitBtn = document.getElementById('submitBtn');
  var DEST_EMAIL = 'afriprogramdev@secretary.net';

  function getMailSubject() {
    return (window.GIRH_I18N && window.GIRH_I18N.getLang() === 'en')
      ? 'My registration' : 'Mon inscription';
  }

  function buildLanguesPayload() {
    var rows = langRowsWrap.querySelectorAll('.lang-row');
    var payload = [];
    for (var lr = 0; lr < rows.length; lr++) {
      var nm = rows[lr].querySelector('[data-langname]').value.trim();
      if (nm === '') { continue; }
      var boxes = rows[lr].querySelectorAll('.lang-checks input');
      payload.push({ langue: nm, lu: boxes[0].checked, parle: boxes[1].checked, ecrit: boxes[2].checked });
    }
    return payload;
  }

  function buildFormData() {
    var fd = new FormData(form);
    fd.delete('langue_nom[]');
    fd.append('langues_json', JSON.stringify(buildLanguesPayload()));
    return fd;
  }

  function openSendModal() {
    var html = '<p>' + t('send.intro') + '</p>'
      + '<div class="send-actions">'
      + '<button type="button" class="send-btn send-primary" id="girhMailBtn">' + t('send.mail') + '</button>'
      + '</div>'
      + '<div class="send-warning">' + t('send.warning') + '</div>';

    openModal('success', t('send.title'), html);

    var foot = document.getElementById('modalClose');
    if (foot) { foot.style.display = 'none'; }

    var mailBtn = document.getElementById('girhMailBtn');
    if (!mailBtn) { return; }

    mailBtn.addEventListener('click', function () {
      mailBtn.disabled = true;
      mailBtn.textContent = t('send.preparing');

      try { sessionStorage.setItem('girh_submitted', '1'); } catch (err) {}

      var fd = buildFormData();

      fetch(form.getAttribute('action'), { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.success && data.id) {
            var uniqueId = data.id;
            var mailSubject = getMailSubject();
            var mailBody = t('send.mailBody') + ' : ' + uniqueId;

            var subjectEnc = encodeURIComponent(mailSubject)
              .replace(/[!'()*]/g, function (c) { return '%' + c.charCodeAt(0).toString(16).toUpperCase(); });
            var bodyEnc   = encodeURIComponent(mailBody)
              .replace(/[!'()*]/g, function (c) { return '%' + c.charCodeAt(0).toString(16).toUpperCase(); });

            var mailtoHref = 'mailto:' + DEST_EMAIL
              + '?subject=' + subjectEnc
              + '&body=' + bodyEnc;

            mailBtn.textContent = t('send.done');
            mailBtn.className = 'send-btn send-done';
            if (foot) { foot.style.display = ''; }

            window.location.href = mailtoHref;
          } else {
            mailBtn.disabled = false;
            mailBtn.textContent = t('send.mail');
          }
        })['catch'](function () {
          mailBtn.disabled = false;
          mailBtn.textContent = t('send.mail');
        });
    });
  }

  function handleSubmit(e) {
    if (e && e.preventDefault) { e.preventDefault(); }

    var now = new Date().getTime();
    if (form.__girhLastRun && (now - form.__girhLastRun) < 600) { return false; }
    form.__girhLastRun = now;

    try {
      var res = validate();
      if (res.problems.length) {
        var list = '<p>' + t('modal.errIntro') + '</p><ul>';
        for (var i = 0; i < res.problems.length; i++) {
          list += '<li>' + res.problems[i].replace(/</g, '&lt;') + '</li>';
        }
        list += '</ul>';
        openModal('error', t('modal.errTitle'), list);
        return false;
      }

      openSendModal();
    } catch (err) {}
    return false;
  }

  form.addEventListener('submit', handleSubmit);
  if (submitBtn) {
    submitBtn.addEventListener('click', handleSubmit);
  }
  window.__girhFormReady = true;

  /* ---------------------------------------------------------
     9. INITIALISATION
  --------------------------------------------------------- */
  if (window.GIRH_I18N && window.GIRH_I18N.ready) {
    window.GIRH_I18N.ready(function () {
      restoreDraft();
      refreshLangLabels();
      refreshFilled();
    });
  } else {
    restoreDraft();
    refreshFilled();
  }
})();
