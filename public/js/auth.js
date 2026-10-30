// ============================================================
// Auth helpers — uses ui.js for shared UI
// ============================================================

// Check auth on every protected page
// Returns the profile object or null (redirects to login)
async function requireAuth(allowAdmin = false) {
  if (!localStorage.getItem('ghaira_session')) {
    window.location.href = '/login.html';
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
    window.location.href = '/login.html';
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
    window.location.href = '/login.html';
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
  window.location.href = '/login.html';
}
