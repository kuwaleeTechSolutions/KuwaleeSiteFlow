import { useEffect, useState, type FormEvent } from 'react'
import { fetchReferenceList } from '../lib/api'

export type Field = {
  name: string
  label: string
  type?: 'text' | 'number' | 'date' | 'select' | 'textarea' | 'file' | 'reference'
  required?: boolean
  options?: string[]
  placeholder?: string
  step?: string
  // Only used when type === 'reference'. The API endpoint to fetch the
  // dropdown options from, e.g. '/projects', '/sites', '/workers'.
  referenceEndpoint?: string
}

export function EntityForm({
  fields,
  onSubmit,
  onCancel,
  submitLabel = 'Save record',
}: {
  fields: Field[]
  onSubmit: (v: Record<string, FormDataEntryValue>) => void
  onCancel: () => void
  submitLabel?: string
}) {
  const [busy, setBusy] = useState(false)
  const [referenceOptions, setReferenceOptions] = useState<Record<string, { id: string; label: string }[]>>({})
  const [loadingRefs, setLoadingRefs] = useState<Record<string, boolean>>({})

  useEffect(() => {
    fields
      .filter((f) => f.type === 'reference' && f.referenceEndpoint)
      .forEach((f) => {
        setLoadingRefs((prev) => ({ ...prev, [f.name]: true }))
        fetchReferenceList(f.referenceEndpoint!)
          .then((list) => setReferenceOptions((prev) => ({ ...prev, [f.name]: list })))
          .catch(() => setReferenceOptions((prev) => ({ ...prev, [f.name]: [] })))
          .finally(() => setLoadingRefs((prev) => ({ ...prev, [f.name]: false })))
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setBusy(true)
    const data = Object.fromEntries(new FormData(e.currentTarget))
    onSubmit(data)
    setBusy(false)
  }

  return (
    <form className="entity-form" onSubmit={submit}>
      {fields.map((f) => (
        <label key={f.name} className={f.type === 'textarea' || f.type === 'file' ? 'span-2' : undefined}>
          <span>
            {f.label}
            {f.required && ' *'}
          </span>

          {f.type === 'select' ? (
            <select name={f.name} required={f.required} defaultValue="">
              <option value="">Select</option>
              {f.options?.map((x) => (
                <option key={x} value={x}>
                  {x.replaceAll('_', ' ')}
                </option>
              ))}
            </select>
          ) : f.type === 'reference' ? (
            <select name={f.name} required={f.required} disabled={loadingRefs[f.name]} defaultValue="">
              <option value="">{loadingRefs[f.name] ? 'Loading…' : `Select ${f.label.toLowerCase()}`}</option>
              {(referenceOptions[f.name] || []).map((opt) => (
                <option key={opt.id} value={opt.id}>
                  {opt.label}
                </option>
              ))}
            </select>
          ) : f.type === 'textarea' ? (
            <textarea name={f.name} required={f.required} placeholder={f.placeholder} />
          ) : (
            <input name={f.name} type={f.type || 'text'} step={f.step} required={f.required} placeholder={f.placeholder} />
          )}
        </label>
      ))}
      <div className="form-actions">
        <button type="button" className="button secondary" onClick={onCancel}>
          Cancel
        </button>
        <button className="button primary" disabled={busy}>
          {busy ? 'Saving…' : submitLabel}
        </button>
      </div>
    </form>
  )
}
