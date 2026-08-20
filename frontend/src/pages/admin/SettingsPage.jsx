import { useRef, useState } from 'react'
import { ImagePlus, Save, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getSettings, updateSettings } from '../../services/settingsService.js'
import { useTenant } from '../../hooks/useTenant.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

function LogoUploader({ logo, onChange }) {
  const inputRef = useRef(null)

  const handleFile = (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => onChange(reader.result)
    reader.readAsDataURL(file)
    event.target.value = ''
  }

  return (
    <div className="flex items-center gap-4">
      {logo ? (
        <img
          src={logo}
          alt="School logo"
          className="size-20 shrink-0 rounded-2xl border border-slate-200 object-cover"
        />
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="flex size-20 shrink-0 items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 text-slate-400 transition hover:border-brand-400 hover:text-brand-500"
        >
          <ImagePlus className="size-7" aria-hidden="true" />
        </button>
      )}

      <div>
        <input
          ref={inputRef}
          type="file"
          accept="image/png,image/jpeg,image/svg+xml"
          className="hidden"
          onChange={handleFile}
        />
        <Button type="button" variant="secondary" size="sm" onClick={() => inputRef.current?.click()}>
          <ImagePlus className="size-4" aria-hidden="true" />
          {logo ? 'Change logo' : 'Upload logo'}
        </Button>
        <p className="mt-1.5 text-xs text-slate-400">PNG, JPG or SVG — displayed in your portal sidebar.</p>
        {logo ? (
          <button
            type="button"
            onClick={() => onChange(null)}
            className="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:underline"
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
            Remove logo
          </button>
        ) : null}
      </div>
    </div>
  )
}

function SettingsForm({ settings, onSaved }) {
  const [name, setName] = useState(settings?.name ?? '')
  const [gradingScale, setGradingScale] = useState(settings?.grading_scale ?? '0-20')
  const [semesterStructure, setSemesterStructure] = useState(settings?.semester_structure ?? '2 semesters')
  const [timezone, setTimezone] = useState(settings?.timezone ?? 'Africa/Douala')
  const [primaryColor, setPrimaryColor] = useState(settings?.primary_color ?? '#4f46e5')
  const [brandingEnabled, setBrandingEnabled] = useState(Boolean(settings?.custom_branding_enabled))
  const [logo, setLogo] = useState(settings?.logo ?? null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setSaved(false)
    try {
      await updateSettings({
        settings: {
          grading_scale: gradingScale,
          semester_structure: semesterStructure,
          custom_branding_enabled: brandingEnabled,
          primary_color: primaryColor,
          timezone,
        },
        logo,
        name,
      })
      setSaved(true)
      onSaved?.()
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not save settings.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <ErrorDisplay message={error} />
      {saved ? (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-700">
          Settings saved successfully.
        </div>
      ) : null}

      <Card>
        <CardHeader
          title="Branding"
          description="Customize how your school appears across the portal"
        />
        <CardBody className="space-y-5">
          <LogoUploader logo={logo} onChange={setLogo} />
          <Input label="School name" name="name" value={name} onChange={(event) => setName(event.target.value)} />
          <div>
            <label htmlFor="primary_color" className="mb-1.5 block text-sm font-medium text-slate-700">
              Brand color
            </label>
            <div className="flex items-center gap-3">
              <input
                id="primary_color"
                type="color"
                value={primaryColor}
                onChange={(event) => setPrimaryColor(event.target.value)}
                className="h-11 w-20 cursor-pointer rounded-xl border border-slate-300"
              />
              <span className="font-mono text-sm text-slate-500">{primaryColor}</span>
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input
              type="checkbox"
              checked={brandingEnabled}
              onChange={(event) => setBrandingEnabled(event.target.checked)}
              className="size-4 rounded border-slate-300 text-brand-600"
            />
            Enable custom branding
          </label>
        </CardBody>
      </Card>

      <Card>
        <CardHeader title="Academics" description="Grading scale, semesters and timezone" />
        <CardBody className="space-y-5">
          <Input label="Grading scale" name="grading_scale" value={gradingScale} onChange={(event) => setGradingScale(event.target.value)} />
          <Select label="Semester structure" name="semester_structure" value={semesterStructure} onChange={(event) => setSemesterStructure(event.target.value)}>
            <option value="2 semesters">2 semesters</option>
            <option value="3 terms">3 terms</option>
            <option value="4 quarters">4 quarters</option>
          </Select>
          <Select label="Timezone" name="timezone" value={timezone} onChange={(event) => setTimezone(event.target.value)}>
            <option value="Africa/Douala">Africa/Douala (UTC+1)</option>
            <option value="Africa/Lagos">Africa/Lagos (UTC+1)</option>
            <option value="Africa/Nairobi">Africa/Nairobi (UTC+3)</option>
          </Select>
        </CardBody>
      </Card>

      <div className="flex justify-end">
        <Button type="submit" loading={saving}>
          <Save className="size-4" aria-hidden="true" />
          Save settings
        </Button>
      </div>
    </form>
  )
}

export default function SettingsPage() {
  const { data: settings, loading, error, reload } = useAsyncList(getSettings)
  const { refresh } = useTenant()

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="School settings"
          description="Configure grading, branding and preferences for your school."
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load settings.</Card>
        ) : (
          <SettingsForm key="settings-form" settings={settings} onSaved={() => { reload(); refresh() }} />
        )}
      </div>
    </PageContainer>
  )
}
