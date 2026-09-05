// Backend helpers — talks to the PHP + MariaDB API in api/. Sign-in is Google or a
// username/password account (invite-code gated); there is no passkey/WebAuthn path in this build.
export const IS_APPLE = /iPhone|iPad|iPod|Macintosh/.test(navigator.userAgent)
export const IS_ANDROID = /Android/.test(navigator.userAgent)

export async function api(path, opts) {
  const r = await fetch(path, Object.assign({ headers: { 'Content-Type': 'application/json' } }, opts))
  const data = await r.json().catch(() => ({}))
  if (!r.ok) { const e = new Error(data.error || ('HTTP ' + r.status)); e.status = r.status; throw e }
  return data
}

export async function registerWithPassword(name, username, password, code) {
  const { user } = await api('/api/register.php', { method: 'POST', body: JSON.stringify({ name, username, password, code }) })
  return user
}

export async function loginWithPassword(username, password) {
  const { user } = await api('/api/login.php', { method: 'POST', body: JSON.stringify({ username, password }) })
  return user
}

// `code` only matters the first time a given Google account signs in — api/auth-google.php
// requires it to create the profile, then ignores it on every later sign-in.
export async function loginWithGoogle(idToken, code) {
  const { user } = await api('/api/auth-google.php', { method: 'POST', body: JSON.stringify({ id_token: idToken, code: code || '' }) })
  return user
}
