import type {
  AdminActionResponse, AdminAliasResponse, AdminAuditResponse, AdminCatalogItemResponse,
  AdminCatalogResponse, AdminDashboardResponse, AdminDuplicateResponse, AdminFlightResponse,
  AdminFlightsResponse, AdminPreviewResponse, AdminUserResponse, AdminUsersResponse, LookupResponse,
  AdminQualityResponse, AdminDuplicateDetailsResponse, AdminHistoryResponse, AdminUserActivityResponse,
  AdminSecurityResponse, AdminServiceStatsResponse, AdminNotificationsResponse, AdminSearchResponse,
} from '../types/admin'

type Q = string | number | null | undefined
function qs(values: Record<string, Q>): string {
  const p = new URLSearchParams()
  Object.entries(values).forEach(([k,v]) => { if (v !== null && v !== undefined && v !== '') p.set(k,String(v)) })
  const s=p.toString(); return s ? `?${s}` : ''
}
async function req<T>(url:string, options:RequestInit={}):Promise<T>{
  const h=new Headers(options.headers); if(options.body&&!h.has('Content-Type'))h.set('Content-Type','application/json')
  const r=await fetch(url,{...options,credentials:'same-origin',headers:h}); let d:Record<string,unknown>|null=null
  try{d=await r.json()}catch{} if(!r.ok)throw new Error(typeof d?.message==='string'?d.message:`Błąd API: ${r.status}`); return d as T
}

export const getAdminDashboard=()=>req<AdminDashboardResponse>('/api/admin/dashboard')
export const getAdminUsers=(o:Record<string,Q>={})=>req<AdminUsersResponse>('/api/admin/users'+qs({q:o.q,status:o.status,sort:o.sort,page:o.page,per_page:o.perPage}))
export const getAdminUser=(id:number)=>req<AdminUserResponse>(`/api/admin/users/${id}`)
export const updateAdminUser=(id:number,v:Record<string,unknown>)=>req<AdminActionResponse>(`/api/admin/users/${id}`,{method:'PUT',body:JSON.stringify(v)})
export const resetAdminUserSessions=(id:number)=>req<AdminActionResponse>(`/api/admin/users/${id}/reset-sessions`,{method:'POST'})
export const resendAdminActivation=(id:number)=>req<AdminActionResponse>(`/api/admin/users/${id}/resend-activation`,{method:'POST'})
export const createAdminResetLink=(id:number)=>req<AdminActionResponse>(`/api/admin/users/${id}/reset-link`,{method:'POST'})
export const getAdminUserPreview=(id:number)=>req<AdminPreviewResponse>(`/api/admin/users/${id}/preview`)

export const getAdminFlights=(o:Record<string,Q>={})=>req<AdminFlightsResponse>('/api/admin/flights'+qs({q:o.q,scope:o.scope,user_id:o.userId,date_from:o.dateFrom,date_to:o.dateTo,sort:o.sort,page:o.page,per_page:o.perPage}))
export const getAdminFlight=(id:number)=>req<AdminFlightResponse>(`/api/admin/flights/${id}`)
export const updateAdminFlight=(id:number,v:Record<string,unknown>)=>req<AdminActionResponse>(`/api/admin/flights/${id}`,{method:'PUT',body:JSON.stringify(v)})
export const deleteAdminFlight=(id:number)=>req<AdminActionResponse>(`/api/admin/flights/${id}`,{method:'DELETE'})

export const adminLookup=(kind:'users'|'airports'|'airlines'|'aircraft',q:string)=>req<LookupResponse>(`/api/admin/lookups/${kind}`+qs({q}))
export const getAdminCatalog=(type:'airports'|'airlines'|'aircraft',o:Record<string,Q>={})=>req<AdminCatalogResponse>(`/api/admin/catalog/${type}`+qs({q:o.q,sort:o.sort,page:o.page,per_page:o.perPage}))
export const getAdminCatalogItem=(type:'airports'|'airlines'|'aircraft',id:number)=>req<AdminCatalogItemResponse>(`/api/admin/catalog/${type}/${id}`)
export const getAdminCatalogOptions=(type:'airports'|'airlines'|'aircraft',field:string)=>req<LookupResponse>(`/api/admin/catalog/${type}/options/${field}`)
export const saveAdminCatalogItem=(type:'airports'|'airlines'|'aircraft',id:number|null,v:Record<string,unknown>)=>req<AdminActionResponse>(id?`/api/admin/catalog/${type}/${id}`:`/api/admin/catalog/${type}`,{method:id?'PUT':'POST',body:JSON.stringify(v)})
export const getAdminCountries=(q:string)=>req<LookupResponse>('/api/admin/countries'+qs({q}))

export const getAdminAliases=(type:string,q:string,source:string='all')=>req<AdminAliasResponse>('/api/admin/aliases'+qs({type,q,source}))
export const createAdminAlias=(v:Record<string,unknown>)=>req<AdminActionResponse>('/api/admin/aliases',{method:'POST',body:JSON.stringify(v)})
export const updateAdminAlias=(id:number,v:Record<string,unknown>)=>req<AdminActionResponse>(`/api/admin/aliases/${id}`,{method:'PUT',body:JSON.stringify(v)})
export const deleteAdminAlias=(id:number)=>req<AdminActionResponse>(`/api/admin/aliases/${id}`,{method:'DELETE'})
export const getAdminDuplicates=(type:string)=>req<AdminDuplicateResponse>('/api/admin/duplicates'+qs({type}))
export const getAdminAudit=(o:Record<string,Q>={})=>req<AdminAuditResponse>('/api/admin/audit-log'+qs({q:o.q,entity_type:o.entityType,sort:o.sort,page:o.page,per_page:o.perPage}))

export const getAdminQuality=()=>req<AdminQualityResponse>('/api/admin/quality')
export const getAdminDuplicateDetails=(type:string,ids:number[])=>req<AdminDuplicateDetailsResponse>('/api/admin/duplicates/details'+qs({type,ids:ids.join(',')}))
export const mergeAdminDuplicates=(v:Record<string,unknown>)=>req<AdminActionResponse>('/api/admin/duplicates/merge',{method:'POST',body:JSON.stringify(v)})
export const getAdminHistory=(entity:string,id:number)=>req<AdminHistoryResponse>(`/api/admin/history/${entity}/${id}`)
export const getAdminUserActivity=(id:number)=>req<AdminUserActivityResponse>(`/api/admin/users/${id}/activity`)
export const getAdminSecurity=()=>req<AdminSecurityResponse>('/api/admin/security')
export const unlockAdminUser=(id:number)=>req<AdminActionResponse>(`/api/admin/security/users/${id}/unlock`,{method:'POST'})
export const getAdminServiceStats=()=>req<AdminServiceStatsResponse>('/api/admin/service-stats')
export const getAdminNotifications=()=>req<AdminNotificationsResponse>('/api/admin/notifications')
export const setAdminNotificationState=(key:string,v:{is_read?:boolean;is_dismissed?:boolean})=>req<AdminActionResponse>(`/api/admin/notifications/${key}`,{method:'PUT',body:JSON.stringify(v)})
export const searchAdminGlobal=(q:string)=>req<AdminSearchResponse>('/api/admin/search'+qs({q}))
