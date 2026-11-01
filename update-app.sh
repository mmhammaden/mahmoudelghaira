#!/usr/bin/env bash
# Ghaira app update — run from the root of ~/Desktop/ghaira
set -e

echo "== 1/3 writing frontend files =="
cat > public/index.html <<'GHAIRA_FILE_EOF'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ثانوية الغايرة – منصة التمارين</title>
  <link rel="stylesheet" href="js/common.css">
  <style>
    .auth-card { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-top: 1rem; }
    .auth-col { background: #fff; border-radius: 14px; padding: 1.5rem; border: 2px solid transparent; opacity: .55; cursor: pointer; transition: opacity .2s, border-color .2s; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
    .auth-col.active { opacity: 1; border-color: #2e7d32; cursor: default; }
    .auth-col h2 { margin-top: 0; font-size: 1.2rem; }
    .auth-col form { margin-top: 1rem; }
    .auth-col .btn-block { margin-top: .75rem; }
    .switch-hint { font-size: .8rem; color: #777; margin-top: .75rem; }
    @media (max-width: 720px) { .auth-card { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <nav class="navbar">
    <a href="index.html" class="brand">ثانوية الغايرة</a>
  </nav>

  <div class="container">
    <header class="text-center mb-2">
      <h1>ثانوية الغايرة</h1>
      <p class="text-muted large-note">منصة إلكترونية للتمارين والاختبارات للطلاب والمعلمين.</p>
    </header>

    <div class="auth-card" id="authCard">
      <div class="auth-col active" id="colLogin">
        <h2>تسجيل الدخول</h2>
        <form id="loginForm">
          <label for="phone">رقم الهاتف</label>
          <input type="tel" id="phone" name="phone" inputmode="numeric" autocomplete="tel" placeholder="اكتب رقم هاتفك هنا" required>
          <p class="small-note">ثمانية أرقام ويبدأ بـ 2 أو 3 أو 4.</p>

          <div class="field-group" style="position: relative;">
            <label for="password">كلمة المرور</label>
            <input type="password" id="password" placeholder="••••••••" required>
            <button type="button" id="togglePassword" style="position:absolute; bottom:.4rem; inset-inline-start:.5rem; background:none; border:none; color:#2e7d32; cursor:pointer;">إظهار</button>
          </div>

          <button type="submit" class="btn btn-primary btn-block" id="loginBtn">تسجيل الدخول</button>
        </form>
        <p class="switch-hint">ليس لديك حساب؟ اضغط على خانة «إنشاء حساب».</p>
      </div>

      <div class="auth-col" id="colRegister">
        <h2>إنشاء حساب</h2>
        <form id="registerForm">
          <label for="regName">الاسم الكامل</label>
          <input type="text" id="regName" placeholder="اكتب اسمك هنا" required>

          <label for="regPhone">رقم الهاتف</label>
          <input type="tel" id="regPhone" inputmode="numeric" autocomplete="tel" placeholder="اكتب رقم هاتفك هنا" required>
          <p class="small-note">رقم الهاتف مكون من 8 أرقام يبدأ بـ 2 أو 3 أو 4.</p>

          <label for="regPassword">كلمة المرور</label>
          <input type="password" id="regPassword" placeholder="••••••••" required>
          <p class="small-note">6 أحرف على الأقل.</p>

          <label for="regClass">الصف الدراسي</label>
          <select id="regClass" required>
            <option value="">جارٍ تحميل الصفوف...</option>
          </select>

          <button type="submit" class="btn btn-outline btn-block" id="registerBtn">إنشاء الحساب</button>
        </form>
        <p class="switch-hint">لديك حساب بالفعل؟ اضغط على خانة «تسجيل الدخول».</p>
      </div>
    </div>
  </div>

  <footer class="footer">
    ثانوية الغايرة &mdash; جميع الحقوق محفوظة.
  </footer>

  <script src="js/api.js"></script>
  <script src="js/ui.js"></script>
  <script>
    // ---------- Column switching ----------
    const colLogin = document.getElementById('colLogin');
    const colRegister = document.getElementById('colRegister');
    function activate(which) {
      colLogin.classList.toggle('active', which === 'login');
      colRegister.classList.toggle('active', which === 'register');
    }
    colLogin.addEventListener('click', () => activate('login'));
    colRegister.addEventListener('click', () => activate('register'));

    // ---------- Already logged in? go straight to dashboard ----------
    (async function () {
      if (!localStorage.getItem('ghaira_session')) return;
      try {
        const profile = await apiGet('/api/profile/me');
        if (profile && profile.id) window.location.href = 'dashboard.html';
      } catch (_) { localStorage.removeItem('ghaira_session'); }
    })();

    // ---------- Password visibility ----------
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    toggleBtn.addEventListener('click', () => {
      const show = passwordInput.type === 'password';
      passwordInput.type = show ? 'text' : 'password';
      toggleBtn.textContent = show ? 'إخفاء' : 'إظهار';
    });

    // ---------- Alert helper ----------
    function authAlert(message, type) {
      let box = document.getElementById('authAlert');
      if (!box) {
        box = document.createElement('div');
        box.id = 'authAlert';
        const container = document.querySelector('.container');
        container.insertBefore(box, container.firstChild);
      }
      box.innerHTML = '<div class="alert alert-' + (type || 'error') + '" role="alert">' + escapeHtml(message) + '</div>';
    }

    // ---------- Login ----------
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('loginBtn');
      const phone = document.getElementById('phone').value.trim();
      const password = document.getElementById('password').value;

      btn.disabled = true;
      btn.textContent = 'جارٍ تسجيل الدخول...';

      try {
        if (!/^[234]\d{7}$/.test(phone)) throw new Error('رقم الهاتف غير صالح.');
        const data = await apiPost('/api/auth/login', { phone, password });
        if (!data || !data.token) throw new Error('تعذر إنشاء جلسة تسجيل الدخول. حاول مرة أخرى.');
        localStorage.setItem('ghaira_session', data.token);
        window.location.href = 'dashboard.html';
      } catch (err) {
        authAlert(translateError(err.message));
        btn.disabled = false;
        btn.textContent = 'تسجيل الدخول';
      }
    });

    // ---------- Register ----------
    const classSelect = document.getElementById('regClass');
    (async function loadClasses() {
      try {
        const data = await apiGet('/api/classes');
        classSelect.innerHTML = '<option value="">اختر صفاً</option>' +
          (data.classes || []).map(c => '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>').join('');
      } catch (err) {
        classSelect.innerHTML = '<option value="">تعذر تحميل الصفوف</option>';
      }
    })();

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('registerBtn');
      const name = document.getElementById('regName').value.trim();
      const phone = document.getElementById('regPhone').value.trim();
      const password = document.getElementById('regPassword').value;
      const class_id = classSelect.value;

      btn.disabled = true;
      btn.textContent = 'جارٍ إنشاء الحساب...';

      try {
        if (!name) throw new Error('الرجاء إدخال الاسم الكامل.');
        if (!/^[234]\d{7}$/.test(phone)) throw new Error('رقم الهاتف غير صالح. يجب أن يكون 8 أرقام ويبدأ بـ 2 أو 3 أو 4.');
        if (!password || password.length < 6) throw new Error('يجب ألا تقل كلمة المرور عن 6 أحرف.');
        if (!class_id) throw new Error('الرجاء اختيار الصف.');

        await apiPost('/api/auth/register', { name, phone, password, class_id: Number(class_id) });

        authAlert('تم إنشاء الحساب بنجاح. يمكنك الآن تسجيل الدخول.', 'success');
        document.getElementById('phone').value = phone;
        activate('login');
        document.getElementById('password').focus();
      } catch (err) {
        authAlert(translateError(err.message));
      } finally {
        btn.disabled = false;
        btn.textContent = 'إنشاء الحساب';
      }
    });
  </script>
</body>
</html>
GHAIRA_FILE_EOF
echo "  wrote public/index.html"
cat > public/admin/dashboard.html <<'GHAIRA_FILE_EOF'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة التحكم للمدير – ثانوية الغايرة</title>
<link rel="stylesheet" href="../js/common.css">
<script src="../js/ui.js"></script>
<script src="../js/auth.js"></script>
<script src="../js/api.js"></script>
</head>
<body>
<nav class="navbar">
  <a href="../index.html" class="brand">ثانوية الغايرة</a>
  <div class="nav-links" id="navLinks"></div>
</nav>

<div class="container">
  <div id="alert-box"></div>

  <div class="loading-text" id="loadingState">
    <div class="loading"></div>
    <span>جارٍ التحميل...</span>
  </div>

  <div id="dashboardContent" style="display:none;">
    <h2>لوحة التحكم للمدير</h2>

    <div class="stat-row" id="stats">
      <div class="stat"><div class="value">0</div><div class="label">الطلاب</div></div>
      <div class="stat"><div class="value">0</div><div class="label">الصفوف</div></div>
      <div class="stat"><div class="value">0</div><div class="label">التمارين</div></div>
      <div class="stat"><div class="value">0</div><div class="label">النتائج</div></div>
    </div>

    <div class="card">
      <h2>الطلاب ونتائجهم</h2>
      <p class="small-note">اضغط على اسم الطالب لعرض التمارين التي أنجزها ونتائجه.</p>
      <div id="studentsList"></div>
    </div>

    <div class="card mt-2">
      <h2>أحدث النتائج</h2>
      <div class="table-wrapper">
        <table id="recentTable">
          <thead>
            <tr><th>الطالب</th><th>الصف</th><th>التمرين</th><th>النتيجة</th><th>تاريخ الإرسال</th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<footer class="footer">
  ثانوية الغايرة &mdash; جميع الحقوق محفوظة.
</footer>

<style>
  details.student-box { border: 1px solid #e3e6ea; border-radius: 10px; padding: .75rem 1rem; margin-bottom: .6rem; background: #fafbfc; }
  details.student-box summary { cursor: pointer; font-weight: bold; display: flex; flex-wrap: wrap; gap: .4rem .9rem; align-items: center; }
  details.student-box summary .meta { font-weight: normal; color: #667; font-size: .85rem; }
  details.student-box[open] summary { margin-bottom: .75rem; }
  .score-pill { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .8rem; background: #e8f5e9; color: #1b5e20; }
</style>

<script>
let profile;

async function init() {
  profile = await requireAuth(true);
  if (!profile) return;
  await buildNavbar();

  document.getElementById('loadingState').style.display = 'none';
  document.getElementById('dashboardContent').style.display = 'block';
  await Promise.all([loadStats(), loadStudents(), loadRecent()]);
}

async function loadStats() {
  try {
    const [uRes, cRes, eRes, rRes] = await Promise.all([
      apiGet('/api/admin/users'),
      apiGet('/api/classes'),
      apiGet('/api/admin/exercises'),
      apiGet('/api/admin/results')
    ]);

    const users = uRes.users || [];
    const studentCount = users.filter(u => u.role === 'student').length;

    document.querySelectorAll('.stat .value')[0].textContent = studentCount;
    document.querySelectorAll('.stat .value')[1].textContent = (cRes.classes || []).length;
    document.querySelectorAll('.stat .value')[2].textContent = (eRes.exercises || []).length;
    document.querySelectorAll('.stat .value')[3].textContent = (rRes.results || []).length;
  } catch (err) {
    showAlert(err.message, 'error');
  }
}

async function loadStudents() {
  const container = document.getElementById('studentsList');
  try {
    const [uRes, rRes] = await Promise.all([
      apiGet('/api/admin/users'),
      apiGet('/api/admin/results')
    ]);
    const students = (uRes.users || []).filter(u => u.role === 'student');
    const results = rRes.results || [];

    if (!students.length) {
      container.innerHTML = '<div class="empty-state"><p class="fw-bold">لا يوجد طلاب بعد.</p></div>';
      return;
    }

    // Group results by student
    const byStudent = {};
    for (const r of results) {
      (byStudent[r.user_id] = byStudent[r.user_id] || []).push(r);
    }

    container.innerHTML = students.map(s => {
      const own = byStudent[s.id] || [];
      const avg = own.length
        ? Math.round(own.reduce((sum, r) => sum + (r.total ? (r.score / r.total) * 100 : 0), 0) / own.length)
        : null;
      const rows = own.map(r => `
        <tr>
          <td>${escapeHtml(r.exercise_title || ('التمرين #' + r.exercise_id))}</td>
          <td>${escapeHtml(r.score)} / ${escapeHtml(r.total)}</td>
          <td>${escapeHtml(formatDate(r.submitted_at))}</td>
        </tr>`).join('');

      return `
        <details class="student-box">
          <summary>
            <span>${escapeHtml(s.name)}</span>
            <span class="meta">${escapeHtml(s.class_name || 'بدون صف')}</span>
            <span class="meta">${own.length} تمرين منجز</span>
            ${avg !== null ? `<span class="score-pill">متوسط ${avg}%</span>` : ''}
          </summary>
          ${own.length ? `
            <div class="table-wrapper">
              <table>
                <thead><tr><th>التمرين</th><th>النتيجة</th><th>التاريخ</th></tr></thead>
                <tbody>${rows}</tbody>
              </table>
            </div>` : '<p class="small-note">لم ينجز هذا الطالب أي تمرين بعد.</p>'}
        </details>`;
    }).join('');
  } catch (err) {
    showAlert(err.message, 'error');
  }
}

async function loadRecent() {
  try {
    const data = await apiGet('/api/admin/results');
    const results = data.results || [];

    const tbody = document.querySelector('#recentTable tbody');
    tbody.innerHTML = (results || []).slice(0, 10).map(r => {
      return `<tr>
        <td>${escapeHtml(r.student_name)}</td>
        <td>${escapeHtml(r.class_name) || '-'}</td>
        <td>${escapeHtml(r.exercise_title || ('التمرين #' + r.exercise_id))}</td>
        <td>${escapeHtml(r.score)} / ${escapeHtml(r.total)}</td>
        <td>${escapeHtml(formatDate(r.submitted_at))}</td>
      </tr>`;
    }).join('');
  } catch (err) {
    showAlert(err.message, 'error');
  }
}

init();
</script>
</body>
</html>
GHAIRA_FILE_EOF
echo "  wrote public/admin/dashboard.html"
cat > public/login.html <<'GHAIRA_FILE_EOF'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="0; url=/">
<script>window.location.href = '/';</script>
</head>
<body></body>
</html>
GHAIRA_FILE_EOF
echo "  wrote public/login.html"
cat > public/register.html <<'GHAIRA_FILE_EOF'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="0; url=/">
<script>window.location.href = '/';</script>
</head>
<body></body>
</html>
GHAIRA_FILE_EOF
echo "  wrote public/register.html"
cat > public/js/ui.js <<'GHAIRA_FILE_EOF'
// ============================================================
// ثانوية الغايرة — Reusable UI Components
// Shared header, navigation, loading, alerts, etc.
// ============================================================

// Escape HTML to prevent XSS
function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Format an ISO date into a friendly Arabic string
function formatDate(iso) {
  if (!iso) return '-';
  try {
    return new Date(iso).toLocaleString('ar-SA', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return iso;
  }
}

// Show a user-friendly alert box
function showAlert(message, type = 'error') {
  const existing = document.querySelector('.alert');
  if (existing) existing.remove();

  const div = document.createElement('div');
  div.className = `alert alert-${type}`;
  div.setAttribute('role', 'alert');
  div.textContent = message;

  const container = document.querySelector('.container') || document.body;
  container.insertBefore(div, container.firstChild);

  setTimeout(() => div.remove(), 6000);
}

// Show a loading spinner in a container
function showLoading(containerId, message = 'جارٍ التحميل...') {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = `
    <div class="loading-text">
      <div class="loading"></div>
      <span>${escapeHtml(message)}</span>
    </div>
  `;
}

// Show an empty state
function showEmpty(containerId, message, subtext = '') {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = `
    <div class="empty-state">
      <p class="fw-bold">${escapeHtml(message)}</p>
      ${subtext ? `<p class="small-note">${escapeHtml(subtext)}</p>` : ''}
    </div>
  `;
}

// Build the top navigation bar based on user role
async function buildNavbar(profile) {
  const container = document.getElementById('navLinks');
  if (!container) return;

  let links = '';

  if (profile.role === 'admin') {
    links = `
      <a href="/admin/dashboard.html" class="${isCurrentPage('admin/dashboard.html') ? 'active' : ''}">لوحة التحكم</a>
      <a href="/admin/programs.html" class="${isCurrentPage('admin/programs.html') ? 'active' : ''}">التمارين</a>
      <a href="/admin/users.html" class="${isCurrentPage('admin/users.html') ? 'active' : ''}">المستخدمون</a>
      <a href="/admin/results.html" class="${isCurrentPage('admin/results.html') ? 'active' : ''}">النتائج</a>
    `;
  } else {
    links = `
      <a href="/student/exercises.html" class="${isCurrentPage('student/exercises.html') ? 'active' : ''}">تماريني</a>
      <a href="/student/results.html" class="${isCurrentPage('student/results.html') ? 'active' : ''}">نتائجتي</a>
    `;
  }

  links += `<a href="#" class="danger" onclick="event.preventDefault(); signOut()">تسجيل الخروج</a>`;
  container.innerHTML = links;
}

// Check if current page matches a given path
function isCurrentPage(path) {
  return window.location.pathname.endsWith(path);
}

// Require authentication — redirects to login if not authenticated
// Returns the profile object or null
async function requireAuth(allowAdmin = false) {
  if (!localStorage.getItem('ghaira_session')) {
    window.location.href = '/';
    return null;
  }

  let profile;
  try {
    profile = await apiGet('/api/profile/me');
  } catch (err) {
    localStorage.removeItem('ghaira_session');
    window.location.href = '/';
    return null;
  }

  if (allowAdmin && profile.role !== 'admin') {
    window.location.href = '/dashboard.html';
    return null;
  }

  return profile;
}

// Sign out the user
async function signOut() {
  try { await apiPost('/api/auth/logout', {}); } catch (err) { /* Clear local state regardless. */ }
  localStorage.removeItem('ghaira_session');
  window.location.href = '/';
}

// Create a confirmation dialog
function confirmAction(message) {
  return window.confirm(message);
}

// Create a status badge HTML
function statusBadge(completed) {
  if (completed) {
    return '<span class="status-badge status-completed">مكتمل</span>';
  }
  return '<span class="status-badge status-pending">غير مكتمل</span>';
}

// Translate common authentication errors to friendly Arabic
function translateError(errorMessage) {
  const arabicMessages = {
    'Invalid phone number or password': 'رقم الهاتف أو كلمة المرور غير صحيحة.',
    'Phone number already exists': 'هذا الرقم مسجل بالفعل.',
    'Network error': 'خطأ في الاتصال بالشبكة. يرجى المحاولة لاحقًا.',
    'session not found': 'انتهت الجلسة. يرجى تسجيل الدخول مرة أخرى.',
  };

  for (const [en, ar] of Object.entries(arabicMessages)) {
    if (errorMessage && errorMessage.includes(en)) {
      return ar;
    }
  }

  return errorMessage || 'حدث خطأ غير متوقع.';
}
GHAIRA_FILE_EOF
echo "  wrote public/js/ui.js"
cat > public/js/auth.js <<'GHAIRA_FILE_EOF'
// ============================================================
// Auth helpers — uses ui.js for shared UI
// ============================================================

// Check auth on every protected page
// Returns the profile object or null (redirects to login)
async function requireAuth(allowAdmin = false) {
  if (!localStorage.getItem('ghaira_session')) {
    window.location.href = '/';
    return null;
  }

  try {
    const profile = await apiGet('/api/profile/me');
    if (allowAdmin && profile.role !== 'admin') {
      window.location.href = '/dashboard.html';
      return null;
    }
    return profile;
  } catch (err) {
    localStorage.removeItem('ghaira_session');
    window.location.href = '/';
    return null;
  }
}

// Build navbar with logout — uses ui.js buildNavbar
async function buildNavbar() {
  const container = document.getElementById('navLinks');
  if (!container || !localStorage.getItem('ghaira_session')) return;

  try {
    const profile = await apiGet('/api/profile/me');
    const links = await buildNavbarLinks(profile);
    container.innerHTML = links;
    return profile;
  } catch (err) {
    localStorage.removeItem('ghaira_session');
    window.location.href = '/';
    return null;
  }
}

// Build navigation links HTML based on role
async function buildNavbarLinks(profile) {
  let links = '';

  if (profile.role === 'admin') {
    links = `
      <a href="/admin/dashboard.html" class="${window.location.pathname.endsWith('admin/dashboard.html') ? 'active' : ''}">لوحة التحكم</a>
      <a href="/admin/programs.html" class="${window.location.pathname.endsWith('admin/programs.html') ? 'active' : ''}">التمارين</a>
      <a href="/admin/users.html" class="${window.location.pathname.endsWith('admin/users.html') ? 'active' : ''}">المستخدمون</a>
      <a href="/admin/results.html" class="${window.location.pathname.endsWith('admin/results.html') ? 'active' : ''}">النتائج</a>
    `;
  } else {
    links = `
      <a href="/student/exercises.html" class="${window.location.pathname.endsWith('student/exercises.html') ? 'active' : ''}">تماريني</a>
      <a href="/student/results.html" class="${window.location.pathname.endsWith('student/results.html') ? 'active' : ''}">نتائجتي</a>
    `;
  }

  links += `<a href="#" class="danger" onclick="event.preventDefault(); signOut()">تسجيل الخروج</a>`;
  return links;
}

// Sign out the user
async function signOut() {
  try { await apiPost('/api/auth/logout', {}); } catch (err) { /* Clear local state regardless. */ }
  localStorage.removeItem('ghaira_session');
  window.location.href = '/';
}
GHAIRA_FILE_EOF
echo "  wrote public/js/auth.js"

echo "== 2/3 patching app.py =="
python3 - <<'GHAIRA_PY_EOF'
import sys
p = 'app.py'
s = open(p, encoding='utf-8').read()
changed = False

anchor = '''            "FOREIGN KEY (user_id) REFERENCES users(id), "
            "FOREIGN KEY (exercise_id) REFERENCES exercises(id))"
        )
        db.commit()
        cur.close()'''
chapters_fix = '''            "FOREIGN KEY (user_id) REFERENCES users(id), "
            "FOREIGN KEY (exercise_id) REFERENCES exercises(id))"
        )
        cur.execute(
            "CREATE TABLE IF NOT EXISTS chapters ("
            "id SERIAL PRIMARY KEY, "
            "name TEXT NOT NULL, "
            "class_id INTEGER NOT NULL, "
            "exercise_count INTEGER DEFAULT 0, "
            "created_at TEXT DEFAULT CURRENT_TIMESTAMP, "
            "FOREIGN KEY (class_id) REFERENCES classes(id))"
        )
        cur.execute(
            "ALTER TABLE exercises ADD COLUMN IF NOT EXISTS chapter_id INTEGER "
            "REFERENCES chapters(id)"
        )
        cur.execute(
            "CREATE INDEX IF NOT EXISTS idx_exercises_chapter ON exercises(chapter_id)"
        )
        db.commit()
        cur.close()

        # Backfill chapters: every class gets a default chapter, and every
        # exercise is attached to the first chapter of its class.
        cur = db.cursor()
        cur.execute(
            "INSERT INTO chapters (name, class_id) "
            "SELECT 'الفصل الأول', c.id FROM classes c "
            "WHERE NOT EXISTS (SELECT 1 FROM chapters ch WHERE ch.class_id = c.id)"
        )
        cur.execute(
            "UPDATE exercises e SET chapter_id = ch.id FROM chapters ch "
            "WHERE ch.class_id = e.class_id AND e.chapter_id IS NULL"
        )
        db.commit()'''
if 'CREATE TABLE IF NOT EXISTS chapters' not in s:
    assert anchor in s, 'init_db anchor not found in app.py'
    s = s.replace(anchor, chapters_fix, 1)
    changed = True

stats_anchor = '@app.route("/api/classes")'
stats_code = '''# Public stats
@app.route("/api/stats")
def api_stats():
    exercises = query_db("SELECT COUNT(*) AS c FROM exercises", one=True)["c"]
    results = query_db("SELECT COUNT(*) AS c FROM results", one=True)["c"]
    classes = query_db("SELECT COUNT(*) AS c FROM classes", one=True)["c"]
    return jsonify(exercises=exercises, results=results, classes=classes)


@app.route("/api/classes")'''
if '"/api/stats"' not in s:
    assert stats_anchor in s, 'api/classes anchor not found in app.py'
    s = s.replace(stats_anchor, stats_code, 1)
    changed = True

if changed:
    open(p, 'w', encoding='utf-8').write(s)
    print('app.py patched')
else:
    print('app.py already patched, skipped')
GHAIRA_PY_EOF

echo "== 3/3 cleaning stray files from the repo =="
git rm -r -q --cached src work admin-browser-audit.py legacy-parity.patch backend/schema.sql.backup backend/schema.sql.bak .firebase 2>/dev/null || true
grep -q '^src/$' .gitignore 2>/dev/null || printf 'src/\nwork/\nadmin-browser-audit.py\nlegacy-parity.patch\nbackend/schema.sql.backup\nbackend/schema.sql.bak\n.firebase/\n' >> .gitignore

echo ""
echo "All done. Now run:"
echo "  git add -A && git commit -m \"feat: simplified app - auth page + admin student results\""
echo "  git push origin cloudflare-api-migration"
echo "  firebase deploy --only hosting:exercice7"
