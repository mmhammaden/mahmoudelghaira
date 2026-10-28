// Check auth on every protected page
async function requireAuth(allowالإدارة = false) {
  const session = await getSession();
  if (!session) {
    window.location.href = '/login.html';
    return null;
  }
  const { data: profile } = await supabase
    .from('profiles').select('*').eq('id', session.user.id).single();
  if (!profile) {
    window.location.href = '/login.html';
    return null;
  }
  if (allowالإدارة && profile.role !== 'admin') {
    window.location.href = '/dashboard.html';
    return null;
  }
  return profile;
}

// Build navbar with logout
async function buildNavbar() {
  const session = await getSession();
  const container = document.getElementById('navLinks');
  if (!container || !session) return;

  const { data: profile } = await supabase
    .from('profiles').select('*').eq('id', session.user.id).single();

  let links = '';
  if (profile.role === 'admin') {
    links = '<a href="/admin/dashboard.html">الإدارة</a><a href="/admin/programs.html">البرامج</a><a href="/admin/users.html">المستخدمون</a><a href="/admin/results.html">النتائج</a>';
  } else {
    links = '<a href="/student/exercises.html">التمارين</a><a href="/student/results.html">نتائجتي</a>';
  }
  links += '<a href="#" class="danger" onclick="event.preventDefault(); signOut()">تسجيل الخروج</a>';
  container.innerHTML = links;
  return profile;
}
