import type { User } from './types'

/**
 * UI-ONLY convenience check — used to hide/disable buttons the user almost
 * certainly cannot use, for a smoother experience. This is NOT a security
 * boundary. The backend re-validates every single action independently via
 * Policies regardless of what this function returns, and a denied action
 * will still surface a proper error message from the server if this check
 * is ever wrong or bypassed (e.g. via direct API calls).
 */
export function can(user: User | null | undefined, permission: string): boolean {
  if (!user) return false
  if (user.is_super_admin) return true
  if (user.permissions?.includes(permission)) return true
  return false
}

export function hasOrgWideVisibility(user: User | null | undefined): boolean {
  if (!user) return false
  if (user.is_super_admin) return true
  return !!user.roles?.some((r) => r.org_wide_visibility)
}
