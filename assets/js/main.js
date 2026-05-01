// Smart Hostel Management — Main JS

document.addEventListener('DOMContentLoaded', () => {

  // ── Mobile sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
  };
  if (toggle && sidebar) {
    toggle.addEventListener('click', e => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      overlay?.classList.toggle('open');
    });
    overlay?.addEventListener('click', closeSidebar);
    // Close when a nav link is tapped on mobile
    sidebar.querySelectorAll('.nav-item').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
      });
    });
    // Close when viewport grows past breakpoint
    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) closeSidebar();
    });
  }

  // ── File drop zone
  const fileDrop = document.querySelector('.file-drop');
  const fileInput = fileDrop?.querySelector('input[type="file"]');
  const fileLabel = document.getElementById('fileLabel');

  if (fileDrop && fileInput) {
    fileDrop.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          showAlert('File must be under 5MB', 'danger');
          fileInput.value = '';
          return;
        }
        if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
          showAlert('Only JPEG, PNG or GIF images allowed', 'danger');
          fileInput.value = '';
          return;
        }
        if (fileLabel) fileLabel.textContent = '📎 ' + file.name;

        // Preview
        const reader = new FileReader();
        reader.onload = e => {
          let preview = document.getElementById('imagePreview');
          if (!preview) {
            preview = document.createElement('img');
            preview.id = 'imagePreview';
            preview.style.cssText = 'max-width:100%;max-height:180px;border-radius:8px;margin-top:12px;display:block;';
            fileDrop.parentNode.insertBefore(preview, fileDrop.nextSibling);
          }
          preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });

    ['dragover', 'dragenter'].forEach(evt => {
      fileDrop.addEventListener(evt, e => { e.preventDefault(); fileDrop.style.borderColor = '#8B0000'; });
    });
    ['dragleave', 'drop'].forEach(evt => {
      fileDrop.addEventListener(evt, e => {
        e.preventDefault();
        fileDrop.style.borderColor = '';
        if (evt === 'drop' && e.dataTransfer.files.length) {
          fileInput.files = e.dataTransfer.files;
          fileInput.dispatchEvent(new Event('change'));
        }
      });
    });
  }

  // ── Auto-dismiss alerts
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 3500);
    setTimeout(() => el.remove(), 3800);
  });

  // ── Confirm dialogs
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Search filter for tables
  const tableSearch = document.getElementById('tableSearch');
  const tableBody   = document.getElementById('tableBody');
  if (tableSearch && tableBody) {
    tableSearch.addEventListener('input', () => {
      const q = tableSearch.value.toLowerCase();
      tableBody.querySelectorAll('tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  // ── Live password strength meter (register page)
  const pwInput  = document.getElementById('passwordInput');
  const pwWrap   = document.getElementById('pwStrength');
  const pwBar    = document.getElementById('pwBar');
  const pwLabel  = document.getElementById('pwLabel');
  if (pwInput && pwWrap && pwBar && pwLabel) {
    pwInput.addEventListener('input', () => {
      const v = pwInput.value;
      if (!v) { pwWrap.style.display = 'none'; return; }
      pwWrap.style.display = 'block';

      let score = 0;
      if (v.length >= 8)           score++;
      if (/[A-Z]/.test(v))         score++;
      if (/[a-z]/.test(v))         score++;
      if (/\d/.test(v))            score++;
      if (/[^A-Za-z0-9]/.test(v))  score++;
      if (v.length >= 12)          score++;

      const levels = [
        { pct:  0, color: '#DC3545', label: 'Too weak' },
        { pct: 20, color: '#DC3545', label: 'Very weak' },
        { pct: 40, color: '#FFC107', label: 'Weak — add more variety' },
        { pct: 60, color: '#FFC107', label: 'Fair — almost there' },
        { pct: 80, color: '#28A745', label: 'Strong' },
        { pct: 95, color: '#28A745', label: 'Very strong' },
        { pct:100, color: '#28A745', label: 'Excellent' },
      ];
      const level = levels[Math.min(score, levels.length - 1)];
      pwBar.style.width     = level.pct + '%';
      pwBar.style.background = level.color;
      pwLabel.textContent   = level.label;
      pwLabel.style.color   = level.color;
    });
  }

  // ── Inline form validation
  const forms = document.querySelectorAll('form[data-validate]');
  forms.forEach(form => {
    form.addEventListener('submit', e => {
      let valid = true;
      form.querySelectorAll('[required]').forEach(input => {
        clearError(input);
        if (!input.value.trim()) {
          showError(input, 'This field is required');
          valid = false;
        }
      });
      const pass    = form.querySelector('input[name="password"]');
      const confirm = form.querySelector('input[name="confirm_password"]');
      if (pass && confirm && pass.value !== confirm.value) {
        showError(confirm, 'Passwords do not match');
        valid = false;
      }
      const email = form.querySelector('input[name="email"]');
      if (email && !email.value.includes('@')) {
        showError(email, 'Please enter a valid email');
        valid = false;
      }
      if (!valid) e.preventDefault();
    });
  });

  function showError(input, msg) {
    input.classList.add('is-invalid');
    const err = document.createElement('p');
    err.className = 'form-error';
    err.innerHTML = `⚠ ${msg}`;
    input.parentNode.appendChild(err);
  }

  function clearError(input) {
    input.classList.remove('is-invalid');
    input.parentNode.querySelectorAll('.form-error').forEach(e => e.remove());
  }

  function showAlert(msg, type = 'danger') {
    const a = document.createElement('div');
    a.className = `alert alert-${type}`;
    a.textContent = msg;
    document.querySelector('.page-body, .auth-form-box')?.prepend(a);
    setTimeout(() => a.remove(), 4000);
  }

});
