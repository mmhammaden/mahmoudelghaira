// ============================================================
// API helper — talks to the Flask API (Google Cloud Run + Neon).
// ============================================================

// Set this to your Cloud Run service URL, e.g.
// 'https://ghaira-api-XXXX.run.app'. Leave empty to use the same origin.
const API_BASE_URL = '';

async function apiRequest(path, options = {}) {
  const token = localStorage.getItem('ghaira_session');

  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {})
  };

  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers });
  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(body?.error || 'تعذر إكمال الطلب.');
  }

  return body;
}

// Convenience wrappers
async function apiGet(path) {
  return apiRequest(path, { method: 'GET' });
}

async function apiPost(path, body) {
  return apiRequest(path, {
    method: 'POST',
    body: JSON.stringify(body)
  });
}

async function apiPut(path, body) {
  return apiRequest(path, {
    method: 'PUT',
    body: JSON.stringify(body)
  });
}

async function apiDelete(path) {
  return apiRequest(path, { method: 'DELETE' });
}
