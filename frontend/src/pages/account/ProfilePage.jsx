import { useEffect, useState } from 'react'
import { BellRing, LogOut, ShieldCheck, UserRound } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import {
  changePassword,
  getProfile,
  signOutOtherSessions,
  updateProfile,
} from '../../services/authService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { PasswordRules } from '../../components/forms/PasswordRules.jsx'
import { formatDateTime } from '../../utils/formatters.js'

export default function ProfilePage() {
  const { refresh } = useAuth()

  const [profile, setProfile] = useState(null)
  const [sessions, setSessions] = useState([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [status, setStatus] = useState(null)
  const [error, setError] = useState(null)

  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [passwordError, setPasswordError] = useState(null)
  const [passwordStatus, setPasswordStatus] = useState(null)
  const [rotating, setRotating] = useState(false)

  useEffect(() => {
    let active = true

    getProfile()
      .then((payload) => {
        if (!active) return
        setProfile(payload.data)
        setSessions(payload.sessions ?? [])
      })
      .catch(() => active && setError('Could not load your profile.'))
      .finally(() => active && setLoading(false))

    return () => {
      active = false
    }
  }, [])

  const setField = (field) => (event) =>
    setProfile((current) => ({ ...current, [field]: event.target.value }))

  const toggle = (field) => (event) =>
    setProfile((current) => ({ ...current, [field]: event.target.checked }))

  const handleSave = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setStatus(null)

    try {
      const { data } = await updateProfile({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
        locale: profile.locale ?? 'en',
        notify_email: Boolean(profile.notify_email),
        notify_sms: Boolean(profile.notify_sms),
      })

      setProfile(data)
      setStatus('Profile saved.')
      await refresh?.()
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not save your profile.')
    } finally {
      setSaving(false)
    }
  }

  const handlePassword = async (event) => {
    event.preventDefault()
    setRotating(true)
    setPasswordError(null)
    setPasswordStatus(null)

    try {
      await changePassword(passwordForm)
      setPasswordForm({ current_password: '', password: '', password_confirmation: '' })
      setPasswordStatus('Password updated. Other devices have been signed out.')
    } catch (err) {
      const errors = err?.response?.data?.errors ?? {}
      setPasswordError(
        errors.current_password?.[0] ?? errors.password?.[0] ?? 'Could not update your password.',
      )
    } finally {
      setRotating(false)
    }
  }

  const handleSignOutOthers = async () => {
    await signOutOtherSessions()
    const payload = await getProfile()
    setSessions(payload.sessions ?? [])
  }

  if (loading) {
    return (
      <PageContainer>
        <div className="flex justify-center py-16">
          <Spinner className="size-7" />
        </div>
      </PageContainer>
    )
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="My profile"
          description="Your contact details, language and how SYNAPSE reaches you."
        />

        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader title="Details" description="Used on documents and for notifications" />
            <CardBody>
              <form onSubmit={handleSave} className="space-y-4">
                <Input label="Full name" value={profile?.name ?? ''} onChange={setField('name')} />
                <Input label="Email" type="email" value={profile?.email ?? ''} onChange={setField('email')} />
                <Input
                  label="Phone (SMS)"
                  value={profile?.phone ?? ''}
                  onChange={setField('phone')}
                  placeholder="+237 6XX XXX XXX"
                />
                <Select label="Language" value={profile?.locale ?? 'en'} onChange={setField('locale')}>
                  <option value="en">English</option>
                  <option value="fr">Français</option>
                </Select>

                <fieldset className="space-y-2 rounded-xl bg-slate-50 p-3">
                  <legend className="flex items-center gap-2 px-1 text-xs font-semibold text-slate-600">
                    <BellRing className="size-3.5" aria-hidden="true" /> Notifications
                  </legend>
                  <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input
                      type="checkbox"
                      checked={Boolean(profile?.notify_email)}
                      onChange={toggle('notify_email')}
                      className="size-4 rounded border-slate-300"
                    />
                    Send me e-mails
                  </label>
                  <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input
                      type="checkbox"
                      checked={Boolean(profile?.notify_sms)}
                      onChange={toggle('notify_sms')}
                      className="size-4 rounded border-slate-300"
                    />
                    Send me SMS alerts
                  </label>
                </fieldset>

                <ErrorDisplay message={error} />
                {status ? <p className="text-sm text-emerald-600">{status}</p> : null}

                <Button type="submit" loading={saving}>
                  Save profile
                </Button>
              </form>
            </CardBody>
          </Card>

          <div className="space-y-6">
            <Card>
              <CardHeader title="Password" description="Changing it signs your other devices out" />
              <CardBody>
                <form onSubmit={handlePassword} className="space-y-4">
                  <Input
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    value={passwordForm.current_password}
                    onChange={(event) =>
                      setPasswordForm((form) => ({ ...form, current_password: event.target.value }))
                    }
                  />
                  <Input
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    value={passwordForm.password}
                    onChange={(event) => setPasswordForm((form) => ({ ...form, password: event.target.value }))}
                  />
                  <Input
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    value={passwordForm.password_confirmation}
                    onChange={(event) =>
                      setPasswordForm((form) => ({ ...form, password_confirmation: event.target.value }))
                    }
                  />

                  <PasswordRules
                    value={passwordForm.password}
                    confirmation={passwordForm.password_confirmation}
                  />

                  <ErrorDisplay message={passwordError} />
                  {passwordStatus ? <p className="text-sm text-emerald-600">{passwordStatus}</p> : null}

                  <Button type="submit" variant="secondary" loading={rotating}>
                    <ShieldCheck className="size-4" aria-hidden="true" />
                    Update password
                  </Button>
                </form>
              </CardBody>
            </Card>

            <Card>
              <CardHeader title="Active sessions" description="Devices holding a valid access token" />
              <CardBody>
                <ul className="space-y-2">
                  {sessions.map((session) => (
                    <li
                      key={session.id}
                      className="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    >
                      <span className="flex items-center gap-2 text-slate-700">
                        <UserRound className="size-4 text-slate-400" aria-hidden="true" />
                        <span className="line-clamp-1 max-w-[16rem]">{session.name}</span>
                      </span>
                      <Badge variant="neutral">
                        {session.last_used_at ? formatDateTime(session.last_used_at) : 'never used'}
                      </Badge>
                    </li>
                  ))}
                  {sessions.length === 0 ? (
                    <li className="text-sm text-slate-500">No other sessions.</li>
                  ) : null}
                </ul>

                <Button variant="ghost" className="mt-3" onClick={handleSignOutOthers}>
                  <LogOut className="size-4" aria-hidden="true" />
                  Sign out other devices
                </Button>
              </CardBody>
            </Card>
          </div>
        </div>
      </div>
    </PageContainer>
  )
}
