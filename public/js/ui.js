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
  const { data: { session }, error: sessionError } = await supabase.auth.getSession();

  if (sessionError || !session) {
    window.location.href = '/login.html';
    return null;
  }

  const { data: profile, error: profileError } = await supabase
    .from('profiles')
    .select('*')
    .eq('id', session.user.id)
    .single();

  if (profileError || !profile) {
    window.location.href = '/login.html';
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
  await supabase.auth.signOut();
  window.location.href = '/login.html';
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

// Translate common Supabase/auth errors to friendly Arabic
function translateError(errorMessage) {
  const arabicMessages = {
    'Invalid login credentials': 'رقم الهاتف أو كلمة المرور غير صحيحة.',
    'Email not confirmed': 'الحساب غير مؤكد.',
    'User already registered': 'هذا الرقم مسجل بالفعل.',
    'Phone number already exists': 'هذا الرقم مسجل بالفعل.',
    'Unable to validate email address: invalid format': 'صيغة غير صالحة.',
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
