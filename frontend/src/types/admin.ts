export type AdminSection =
  | 'dashboard'
  | 'users'
  | 'flights'
  | 'airports'
  | 'airlines'
  | 'aircraft'
  | 'aliases'
  | 'duplicates'
  | 'audit'
  | 'quality'
  | 'alerts'
  | 'security'
  | 'service_stats'

export interface PageMeta { status: 'ok'; page: number; per_page: number; total: number; pages: number }
export interface AdminActionResponse { status: 'ok' | 'error'; message?: string; reset_url?: string; [key: string]: unknown }
export interface LookupItem { id: number; label: string }
export interface LookupResponse { status: 'ok'; items: LookupItem[] }

export interface AdminDashboardResponse {
  status: 'ok'
  users: Record<string, number>
  flights: Record<string, number>
  catalogs: { airports: number; airlines: number; aircraft_types: number; countries: number }
  recent_users: Record<string, unknown>[]
  recent_flights: Record<string, unknown>[]
}

export interface AdminUserListItem {
  id: number; nick: string; email: string; is_active: number | boolean; is_admin: number | boolean
  email_verified_at: string | null; privacy_mode: 'private' | 'link' | 'public'; created_at: string
  last_login_at: string | null; flights_count: number | string; distance_km: number | string; duration_seconds: number | string
}
export interface AdminUsersResponse extends PageMeta { users: AdminUserListItem[] }
export interface AdminUserResponse {
  status: 'ok'; user: AdminUserListItem & Record<string, unknown>; stats: Record<string, number | string | null>
  recent_flights: Record<string, unknown>[]; is_current_admin: boolean
}
export interface AdminPreviewResponse { status: 'ok'; read_only: true; user: Record<string, unknown>; flights: Record<string, unknown>[] }

export interface AdminFlightListItem extends Record<string, unknown> { id: number; user_id: number; departure_date: string; user_nick: string }
export interface AdminFlightsResponse extends PageMeta { flights: AdminFlightListItem[] }
export interface AdminFlightResponse { status: 'ok'; flight: Record<string, unknown> }

export interface AdminCatalogResponse extends PageMeta {
  type: 'airports' | 'airlines' | 'aircraft'; columns: string[]; editable_columns: string[]; items: Record<string, unknown>[]
}
export interface AdminCatalogItemResponse { status: 'ok'; item: Record<string, unknown>; columns: string[]; editable_columns: string[] }
export interface AdminAliasResponse { status: 'ok'; aliases: Record<string, unknown>[] }
export interface AdminDuplicateResponse { status: 'ok'; type: string; groups: Array<{ reason: string; key: string; count: number; ids: number[] }> }
export interface AdminAuditResponse extends PageMeta { items: Record<string, unknown>[] }


export interface AdminQualityCategory {
  key: string; label: string; severity: 'error' | 'warning' | 'info'; section: string; count: number; samples: Record<string, unknown>[]
}
export interface AdminQualityResponse { status:'ok'; totals:Record<string,number>; duplicates:Record<string,number>; categories:AdminQualityCategory[]; generated_at:string }
export interface AdminDuplicateDetailsResponse { status:'ok'; type:string; items:Array<Record<string,unknown> & {id:number;usage_count:number;alias_count:number}> }
export interface AdminHistoryResponse { status:'ok'; entity:string; entity_id:number; items:Array<Record<string,unknown> & {changes:Array<{field:string;before:unknown;after:unknown}>}> }
export interface AdminUserActivityResponse { status:'ok'; user:Record<string,unknown>; audit:Record<string,unknown>[] }
export interface AdminSecurityResponse { status:'ok'; summary:Record<string,number>; login_risks:Record<string,unknown>[]; admins:Record<string,unknown>[]; unverified:Record<string,unknown>[]; capabilities:Record<string,boolean> }
export interface AdminServiceStatsResponse { status:'ok'; totals:Record<string,number>; monthly:Record<string,unknown>[]; top_airports:Record<string,unknown>[]; top_airlines:Record<string,unknown>[]; top_aircraft:Record<string,unknown>[]; top_routes:Record<string,unknown>[] }
export interface AdminNotificationItem { key:string;severity:'error'|'warning'|'info';title:string;message:string;section:string;count:number;is_read:boolean;is_dismissed:boolean }
export interface AdminNotificationsResponse { status:'ok'; unread:number; items:AdminNotificationItem[] }
export interface AdminSearchItem { id:number;title:string;subtitle:string;section:string }
export interface AdminSearchResponse { status:'ok';query?:string;groups:Record<string,AdminSearchItem[]> }
