// ============================================================
// Auth helpers — uses ui.js for shared UI
// ============================================================

// Check auth on every protected page
// Returns the profile object or null (redirects to login)
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

// Build navbar with logout — uses ui.js buildNavbar
async function buildNavbar() {
  const { data: { session } } = await supabase.auth.getSession();
  const container = document.getElementById('navLinks');
  if (!container || !session) return;

  const { data: profile } = await supabase
    .from('profiles')
    .select('*')
    .eq('id', session.user.id)
    .single();

  if (!profile) {
    window.location.href = '/login.html';
    return null;
  }

  // Delegate to ui.js buildNavbar for consistent markup
  const links = await buildNavbarLinks(profile);
  container.innerHTML = links;
  return profile;
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
  await supabase.auth.signOut();
  window.location.href = '/login.html';
}
