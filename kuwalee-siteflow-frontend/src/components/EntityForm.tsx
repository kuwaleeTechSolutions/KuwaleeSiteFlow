import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
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
  busy = false,
}: {
  fields: Field[]
  onSubmit: (v: Record<string, FormDataEntryValue>) => void
  onCancel: () => void
  submitLabel?: string
  busy?: boolean
}) {
  const [referenceOptions, setReferenceOptions] = useState<Record<string, { id: string; label: string }[]>>({})
  const [loadingRefs, setLoadingRefs] = useState<Record<string, boolean>>({})
  const formRef = useRef<HTMLFormElement>(null)
  const schema = useMemo(() => z.object(Object.fromEntries(fields.map((field) => [
    field.name,
    field.required
      ? z.any().refine((value) => value instanceof FileList ? value.length > 0 : String(value ?? '').trim().length > 0, `${field.label} is required.`)
      : z.any().optional(),
  ]))).passthrough(), [fields])
  const { handleSubmit, register, formState: { errors } } = useForm<Record<string, unknown>>({
    resolver: zodResolver(schema),
  })

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

  return (
    <form ref={formRef} className="entity-form" onSubmit={handleSubmit(() => {
      // React Hook Form supplies a synthetic event whose currentTarget is
      // not guaranteed after its async resolver runs. Read from the stable
      // form ref so every Save action submits the actual form fields.
      if (formRef.current) onSubmit(Object.fromEntries(new FormData(formRef.current)))
    })}>
      {fields.map((f) => (
        <label key={f.name} className={f.type === 'textarea' || f.type === 'file' ? 'span-2' : undefined}>
          <span>
            {f.label}
            {f.required && ' *'}
          </span>

          {f.type === 'select' ? (
            <select {...register(f.name)} required={f.required} defaultValue="">
              <option value="">Select</option>
              {f.options?.map((x) => (
                <option key={x} value={x}>
                  {x.replaceAll('_', ' ')}
                </option>
              ))}
            </select>
          ) : f.type === 'reference' ? (
            <select {...register(f.name)} required={f.required} disabled={loadingRefs[f.name]} defaultValue="">
              <option value="">{loadingRefs[f.name] ? 'Loading…' : `Select ${f.label.toLowerCase()}`}</option>
              {(referenceOptions[f.name] || []).map((opt) => (
                <option key={opt.id} value={opt.id}>
                  {opt.label}
                </option>
              ))}
            </select>
          ) : f.type === 'textarea' ? (
            <textarea {...register(f.name)} required={f.required} placeholder={f.placeholder} />
          ) : (
            <input {...register(f.name)} type={f.type || 'text'} step={f.step} required={f.required} placeholder={f.placeholder} />
          )}
          {errors[f.name] && <small className="field-error">{String(errors[f.name]?.message)}</small>}
        </label>
      ))}
      <div className="form-actions">
        <button type="button" className="button secondary" onClick={onCancel} disabled={busy}>
          Cancel
        </button>
        <button className="button primary" disabled={busy}>
          {busy ? 'Saving…' : submitLabel}
        </button>
      </div>
    </form>
  )
}
