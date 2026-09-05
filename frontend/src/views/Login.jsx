import { useStore } from '../store/useStore.js'
import { useUI } from '../store/useUI.js'
import { api, loginWithGoogle, loginWithPassword, registerWithPassword } from '../lib/api.js'
import { hasData } from '../store/useStore.js'
import { t } from '../lib/i18n.js'
import { DEMO, REPO } from '../lib/demo.js'
import { useState, useRef, useEffect } from 'react'
import Icon from '../components/Icon.jsx'
import { Button } from '../components/ui.jsx'

// Loads the Google Identity Services script once and renders its button into `el`. `onToken`
// receives the raw Google ID token (JWT) — verifying it is api/auth-google.php's job, never the
// frontend's, so nothing here trusts the token itself.
function useGoogleButton(clientId, el, onToken) {
  useEffect(() => {
    if (!clientId || !el) return
    let cancelled = false
    const render = () => {
      if (cancelled || !window.google?.accounts?.id) return
      window.google.accounts.id.initialize({ client_id: clientId, callback: r => onToken(r.credential) })
      window.google.accounts.id.renderButton(el, { theme: 'outline', size: 'large', width: 280 })
    }
    if (window.google?.accounts?.id) { render(); return }
    const s = document.createElement('script')
    s.src = 'https://accounts.google.com/gsi/client'
    s.async = true; s.defer = true
    s.onload = render
    document.head.appendChild(s)
    return () => { cancelled = true }
  }, [clientId, el])
}

// A brand-new Google account needs an invite code too — otherwise the invite gate on
// username/password sign-up would be pointless, since anyone could just use Google instead.
// An already-linked Google account never sees this; api/auth-google.php only asks the first time.
function GoogleInviteSheet({ idToken, close }) {
  const { setUser, pullState } = useStore()
  const [code, setCode] = useState('')
  const ref = useRef(null)
  useEffect(() => { setTimeout(() => ref.current?.focus(), 250) }, [])
  const go = async () => {
    if (!code.trim()) { useUI.getState().toast(t('An invite code is required')); return }
    try {
      const u = await loginWithGoogle(idToken, code.trim())
      setUser(u); close(); await pullState()
      useUI.getState().toast(t('Welcome, {0}', u.name))
    } catch (e) { useUI.getState().toast(e.message || t('Sign-in failed')) }
  }
  return <>
    <h3>{t('One more step')}</h3>
    <div className="muted small" style={{ marginBottom: 14 }}>{t('This Google account is new here — enter the invite code you were given to finish creating your profile.')}</div>
    <input ref={ref} className="input" placeholder={t('Invite code')} maxLength={40} value={code}
      onChange={e => setCode(e.target.value.toUpperCase())} style={{ letterSpacing: '.14em', fontWeight: 600, textAlign: 'center' }} />
    <div style={{ height: 12 }} />
    <Button variant="primary" onClick={go}>{t('Continue')}</Button>
  </>
}

function RegisterSheet({ close }) {
  const { setUser, pushState, pullState } = useStore()
  const [name, setName] = useState('')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const ref = useRef(null)
  useEffect(() => { setTimeout(() => ref.current?.focus(), 250) }, [])
  const go = async () => {
    const n = name.trim()
    if (!n) { useUI.getState().toast(t('Enter a name')); return }
    if (!username.trim()) { useUI.getState().toast(t('Enter a username')); return }
    if (password.length < 8) { useUI.getState().toast(t('Password must be at least 8 characters')); return }
    if (!code.trim()) { useUI.getState().toast(t('An invite code is required')); return }
    try {
      const u = await registerWithPassword(n, username.trim(), password, code.trim())
      setUser(u); close()
      if (hasData(useStore.getState().S)) { await pushState(); useUI.getState().toast(t('Profile created — data from this device moved into it')) }
      else { await pullState(); useUI.getState().toast(t('Welcome, {0}', u.name)) }
    } catch (e) { useUI.getState().toast(e.message || t('Registration failed')) }
  }
  return <>
    <h3>{t('Create your profile')}</h3>
    <input ref={ref} className="input" placeholder={t('Your name')} maxLength={40} value={name} onChange={e => setName(e.target.value)} />
    <div style={{ height: 10 }} />
    <input className="input" placeholder={t('Username')} maxLength={40} value={username}
      onChange={e => setUsername(e.target.value)} autoCapitalize="none" autoCorrect="off" />
    <div style={{ height: 10 }} />
    <input className="input" type="password" placeholder={t('Password (8+ characters)')} value={password} onChange={e => setPassword(e.target.value)} />
    <div style={{ height: 10 }} />
    <input className="input" placeholder={t('Invite code')} maxLength={40} value={code}
      onChange={e => setCode(e.target.value.toUpperCase())} style={{ letterSpacing: '.14em', fontWeight: 600, textAlign: 'center' }} />
    <div className="dim small" style={{ marginTop: 6 }}>{t('This app is invite-only — enter the code you were given.')}</div>
    <div style={{ height: 12 }} />
    <Button variant="primary" onClick={go}>{t('Create profile')}</Button>
  </>
}

export default function Login() {
  const { setUser, pullState, setGuest } = useStore()
  const [googleClientId, setGoogleClientId] = useState('')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [btnEl, setBtnEl] = useState(null)

  useEffect(() => { api('/api/app-config.php').then(c => setGoogleClientId(c.google_client_id || '')).catch(() => {}) }, [])

  const onGoogleToken = async idToken => {
    try {
      const u = await loginWithGoogle(idToken)
      setUser(u); await pullState()
      useUI.getState().toast(t('Welcome, {0}', u.name))
    } catch (e) {
      if (e.status === 403) useUI.getState().openSheet(close => <GoogleInviteSheet idToken={idToken} close={close} />)
      else useUI.getState().toast(e.message || t('Sign-in failed'))
    }
  }
  useGoogleButton(googleClientId, btnEl, onGoogleToken)

  const signIn = async () => {
    if (!username.trim() || !password) { useUI.getState().toast(t('Enter your username and password')); return }
    setBusy(true)
    try {
      const u = await loginWithPassword(username.trim(), password)
      setUser(u); await pullState()
      useUI.getState().toast(t('Welcome back, {0}', u.name))
    } catch (e) { useUI.getState().toast(e.message || t('Sign-in failed')) }
    setBusy(false)
  }

  const head = <>
    <div style={{ fontSize: 54, display: 'flex', justifyContent: 'center', color: 'var(--acc)' }}><Icon name="dumbbell" /></div>
    <h1 style={{ fontSize: 34, fontWeight: 700, letterSpacing: '-.028em', margin: '10px 0 4px' }}>O Ximnasio do Pardelo</h1>
  </>
  const wrap = { display: 'flex', flexDirection: 'column', justifyContent: 'center', minHeight: '78vh', textAlign: 'center' }

  // Demo build: no backend to sign in against — the only way in is the local guest profile.
  if (DEMO) return (
    <div className="narrow" style={wrap}>
      {head}
      <div className="muted" style={{ marginBottom: 30 }}>{t('Live demo — everything stays in this browser.')}</div>
      <Button variant="primary" icon="sparkles" onClick={() => setGuest(true)}>{t('Start the demo')}</Button>
      <div className="card small muted" style={{ textAlign: 'left', marginTop: 16 }}>
        {t('This demo runs entirely in your browser on example data — nothing is sent anywhere. Sign-in and sync across your devices come with the O Ximnasio do Pardelo server, which you get by self-hosting it.')}
      </div>
      <div className="dim small" style={{ marginTop: 22, lineHeight: 1.6 }}>
        <a href={REPO} target="_blank" rel="noopener">{t('Self-host it in a minute →')}</a>
      </div>
    </div>
  )

  return (
    <div className="narrow" style={wrap}>
      {head}
      <div className="muted" style={{ marginBottom: 26 }}>{t('Your workouts. Your weights. Your profile.')}</div>

      {googleClientId && <>
        <div style={{ display: 'flex', justifyContent: 'center' }} ref={setBtnEl} />
        <div className="dim small" style={{ margin: '16px 0' }}>{t('or')}</div>
      </>}

      <input className="input" placeholder={t('Username')} maxLength={40} value={username}
        onChange={e => setUsername(e.target.value)} autoCapitalize="none" autoCorrect="off" />
      <div style={{ height: 10 }} />
      <input className="input" type="password" placeholder={t('Password')} value={password}
        onChange={e => setPassword(e.target.value)} onKeyDown={e => e.key === 'Enter' && signIn()} />
      <div style={{ height: 10 }} />
      <Button variant="primary" icon="person" onClick={signIn} disabled={busy}>{t('Sign in')}</Button>
      <div style={{ height: 10 }} />
      <Button icon="sparkles" onClick={() => useUI.getState().openSheet(close => <RegisterSheet close={close} />)}>{t('Create new profile')}</Button>

      <div className="dim small" style={{ marginTop: 26, lineHeight: 1.5 }}>
        Desarrollado por <a href="https://ipardelo.es" target="_blank" rel="noopener">ipardelo</a>
      </div>
    </div>
  )
}
