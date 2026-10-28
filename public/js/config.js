// ============================================
// SUPABASE CONFIG — REPLACE WITH YOUR VALUES
// Get these from Supabase Dashboard > Settings > API
// ============================================
const SUPABASE_URL = 'https://vjzogmivmohrygundsgk.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZqem9nbWl2bW9ocnlndW5kc2drIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTMxMDg3MDAsImV4cCI6MjEwODY4NDcwMH0.JM7tnIIwZaWyBQko6mXAPfdayNCKt9AP5BoBGeJSf_Q';

// The CDN exposes the Supabase library at `window.supabase`.
// We must call createClient() on it to get the configured client.
window.supabase = window.supabase.createClient(
  SUPABASE_URL,
  SUPABASE_ANON_KEY
);

// Auth helpers
async function getSession() {
    const { data: { session } } = await supabase.auth.getSession();
    return session;
}

async function signOut() {
    await supabase.auth.signOut();
    window.location.href = '/login.html';
}

function showAlert(message, type = 'error') {
    const existing = document.querySelector('.alert');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'alert alert-' + type;
    div.textContent = message;
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(div, container.firstChild);
    setTimeout(() => div.remove(), 5000);
}

function formatDate(iso) {
    if (!iso) return '-';
    return new Date(iso).toLocaleString();
}

// Escape a string for safe insertion into HTML (prevents stored XSS)
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
