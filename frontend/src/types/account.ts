import type {
  Flight,
} from './flight'


export type PrivacyMode =
  | 'private'
  | 'link'
  | 'public'


export interface AccountUser {
  id: number
  email: string
  nick: string
  avatar_url: string
  privacy_mode: PrivacyMode
  share_url: string | null
  public_url: string | null
  public_slug: string | null
}


export interface AuthStateResponse {
  status: 'ok'
  authenticated: boolean
  user: AccountUser | null
}


export interface AuthActionResponse {
  status: 'ok' | 'error'
  message?: string
  authenticated?: boolean
  user?: AccountUser | null
  captcha_required?: boolean
  captcha_planned?: boolean
  existing_account?: boolean
  field?: 'email' | 'nick' | 'password' | 'password_repeat'
}


export interface PublicMapProfile {
  nick: string
  avatar_url: string
  public_url: string | null
}


export interface PublicMapResponse {
  status: 'ok'
  access_mode: 'public' | 'link'
  profile: PublicMapProfile
  count: number
  flights: Flight[]
}
