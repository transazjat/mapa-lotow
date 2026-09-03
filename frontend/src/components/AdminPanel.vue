<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Map as MapLibreMap } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import {
  adminLookup, createAdminAlias, createAdminResetLink, deleteAdminAlias, deleteAdminFlight,
  getAdminAliases, getAdminAudit, getAdminCatalog, getAdminCatalogItem, getAdminCatalogOptions, getAdminDashboard,
  getAdminCountries, getAdminDuplicates, getAdminDuplicateDetails, getAdminFlight, getAdminFlights,
  getAdminHistory, getAdminNotifications, getAdminQuality, getAdminSecurity, getAdminServiceStats,
  getAdminUser, getAdminUserActivity, getAdminUserPreview, getAdminUsers, mergeAdminDuplicates,
  resendAdminActivation, resetAdminUserSessions, saveAdminCatalogItem, searchAdminGlobal,
  setAdminNotificationState, unlockAdminUser, updateAdminAlias, updateAdminFlight, updateAdminUser,
} from '../services/adminApi'
import type { AdminSection } from '../types/admin'
import type { AccountUser } from '../types/account'
import runwayLogo from '../assets/branding/mapa-lotow-symbol.png'

const props = defineProps<{ user: AccountUser | null }>()
const emit = defineEmits<{ close: [] }>()

const section = ref<AdminSection>('dashboard')
const loading = ref(false)
const error = ref('')
const message = ref('')
const dashboard = ref<any>(null)
const users = ref<any[]>([]), userPage=ref(1), userPages=ref(1), userTotal=ref(0), userQ=ref(''), userStatus=ref('all'), userSort=ref('created_desc'), userPer=ref(25)
const selectedUser=ref<any>(null), userDraft=ref<any>({}), resetUrl=ref('')
const flights=ref<any[]>([]), flightPage=ref(1), flightPages=ref(1), flightTotal=ref(0), flightQ=ref(''), flightScope=ref('all'), flightUserId=ref(''), flightDateFrom=ref(''), flightDateTo=ref(''), flightSort=ref('date_desc'), flightPer=ref(25)
const selectedFlight=ref<any>(null), flightDraft=ref<any>({})
const catalogType=computed(() => section.value==='airports'?'airports':section.value==='airlines'?'airlines':'aircraft')
const catalogItems=ref<any[]>([]), catalogPage=ref(1), catalogPages=ref(1), catalogTotal=ref(0), catalogQ=ref(''), catalogSort=ref('name_asc'), catalogPer=ref(25), catalogEditable=ref<string[]>([]), catalogDraft=ref<any>(null)
const aliases=ref<any[]>([]), aliasType=ref('all'), aliasQ=ref(''), aliasDraft=ref({entity_type:'airport',entity_id:'',alias:''}), aliasTargetLabel=ref('')
const duplicates=ref<any[]>([]), duplicateType=ref('airports')
const auditItems=ref<any[]>([]), auditPage=ref(1), auditPages=ref(1), auditTotal=ref(0), auditQ=ref(''), auditEntity=ref(''), auditSort=ref('date_desc'), auditPer=ref(50)
const aliasSort=ref('date_desc'), aliasSource=ref('all'), selectedAlias=ref<any>(null), aliasEditDraft=ref<any>({})
const quality=ref<any>(null), security=ref<any>(null), serviceStats=ref<any>(null), notifications=ref<any>({unread:0,items:[]})
const duplicateDetails=ref<any[]>([]), duplicatePrimaryId=ref<number|null>(null), duplicateGroup=ref<any>(null)
const historyPanel=ref<any>(null), userActivity=ref<any>(null)
const globalQ=ref(''), globalGroups=ref<Record<string,any[]>>({}), globalOpen=ref(false), globalTimer=ref<number|null>(null)
const preview=ref<any>(null), previewMapEl=ref<HTMLDivElement|null>(null); let previewMap:MapLibreMap|null=null
const lookupOpen=ref(''), lookupItems=ref<any[]>([]), lookupTimer=ref<number|null>(null), lookupActive=ref(-1)
const countryLookupItems=ref<any[]>([]), countryLookupOpen=ref(false), countryLookupActive=ref(-1)
const catalogFieldOptions=ref<Record<string,string[]>>({manufacturer:[],family:[]})
const closePrompt=ref(false)

const travelClassOptions=[
  {value:'',label:'Brak danych'},
  {value:'economy',label:'Economy'},
  {value:'premium_economy',label:'Premium Economy'},
  {value:'business',label:'Business'},
  {value:'first',label:'First'},
]
const seatTypeOptions=[
  {value:'',label:'Brak danych'},
  {value:'window',label:'Okno'},
  {value:'middle',label:'Środek'},
  {value:'aisle',label:'Przejście'},
]
const travelReasonOptions=[
  {value:'p',label:'Prywatny'},
  {value:'b',label:'Biznesowy'},
]
function normalizeTravelReason(value:unknown){
  const v=String(value??'').trim().toLowerCase()
  if(['p','private','personal','prywatny'].includes(v))return 'p'
  if(['b','business','biznesowy'].includes(v))return 'b'
  return v
}

const timezoneOptions=computed<string[]>(()=>{
  try{
    const values=(Intl as any).supportedValuesOf?.('timeZone') as string[]|undefined
    return values?.length ? values : ['UTC','Europe/Warsaw','Europe/London','America/New_York','Asia/Dubai','Asia/Bangkok','Asia/Tokyo','Australia/Sydney']
  }catch{return ['UTC','Europe/Warsaw','Europe/London','America/New_York','Asia/Dubai','Asia/Bangkok','Asia/Tokyo','Australia/Sydney']}
})
const aliasesSorted=computed(()=>{
  const rows=[...aliases.value]
  const [field,dir]=aliasSort.value.split('_')
  const key=(a:any)=>{
    if(field==='alias')return String(a.alias||'').toLocaleLowerCase('pl')
    if(field==='type')return String(a.entity_type||'')
    if(field==='target')return String(a.target_label||'').toLocaleLowerCase('pl')
    if(field==='admin')return String(a.created_by_nick||'').toLocaleLowerCase('pl')
    return String(a.created_at||'')
  }
  rows.sort((a,b)=>key(a).localeCompare(key(b),'pl',{numeric:true})*(dir==='desc'?-1:1))
  return rows
})
function sortMark(current:string,base:string){return current===`${base}_asc`?'▲':current===`${base}_desc`?'▼':''}
function toggleUserSort(base:string){userSort.value=userSort.value===`${base}_asc`?`${base}_desc`:`${base}_asc`;userPage.value=1;void loadUsers()}
function toggleFlightSort(base:string){flightSort.value=flightSort.value===`${base}_asc`?`${base}_desc`:`${base}_asc`;flightPage.value=1;void loadFlights()}
function toggleCatalogSort(base:string){catalogSort.value=catalogSort.value===`${base}_asc`?`${base}_desc`:`${base}_asc`;catalogPage.value=1;void loadCatalog()}
function toggleAuditSort(base:string){auditSort.value=auditSort.value===`${base}_asc`?`${base}_desc`:`${base}_asc`;auditPage.value=1;void loadAudit()}
function toggleAliasSort(base:string){aliasSort.value=aliasSort.value===`${base}_asc`?`${base}_desc`:`${base}_asc`}
function catalogHint(field:string){
  if(section.value!=='aircraft')return ''
  const hints:Record<string,string>={
    name:'Pełna, czytelna nazwa typu wyświetlana użytkownikowi, np. Airbus A350-900.',
    manufacturer:'Wybierz producenta z wartości już stosowanych w bazie.',
    model:'Podstawowy model samolotu, np. A350, 737, E190.',
    variant:'Dokładniejsza odmiana modelu, np. -900, 8 MAX, 200LR. Jeśli brak odrębnego wariantu, pozostaw puste.',
    family:'Wybierz rodzinę konstrukcyjną stosowaną w bazie, np. A350 lub 737.',
    is_active:'Nieaktywnego typu nie proponujemy do nowych wpisów, ale pozostaje w danych historycznych.',
  }
  return hints[field]||''
}
const catalogLabels:Record<string,string>={
  name:'Nazwa',iata_code:'Kod IATA',icao_code:'Kod ICAO',city:'Miasto',
  country_id:'Państwo',country_name:'Nazwa państwa',latitude:'Szerokość geograficzna',
  longitude:'Długość geograficzna',timezone_name:'Strefa czasowa',callsign:'Callsign',
  is_active:'Status',manufacturer:'Producent',model:'Model',variant:'Wariant',family:'Rodzina',
}
function catalogLabel(field:string){return catalogLabels[field]||field}
const orderedCatalogEditable=computed(()=>{
  const fields=[...catalogEditable.value]
  if(section.value!=='airports')return fields

  const preferred=[
    'name','city',
    'iata_code','icao_code',
    'country_id','timezone_name',
    'longitude','latitude',
  ]

  const ordered=preferred.filter(field=>fields.includes(field))
  const remaining=fields.filter(field=>!preferred.includes(field))
  return [...ordered,...remaining]
})
function hasOption(options:Array<{value:string}>, value:unknown){return value===null||value===undefined||value===''||options.some(o=>o.value===String(value))}
function clearFlightLookup(kind:'airline'|'aircraft'){
  if(kind==='airline'){flightDraft.value.airline_id=null;flightDraft.value.airline_label=''}
  else{flightDraft.value.aircraft_type_id=null;flightDraft.value.aircraft_label=''}
}


function clearLookupMenus(){lookupOpen.value='';lookupItems.value=[];lookupActive.value=-1;countryLookupOpen.value=false;countryLookupItems.value=[];countryLookupActive.value=-1}
function openDatePicker(event:MouseEvent){
  const button=event.currentTarget as HTMLButtonElement
  const input=button.previousElementSibling as HTMLInputElement|null
  if(!input)return
  try{
    if(typeof (input as any).showPicker==='function')(input as any).showPicker()
    else input.focus()
  }catch{input.focus()}
}
function requestAppClose(){closePrompt.value=true}
function cancelAppClose(){closePrompt.value=false}
function confirmAppClose(){closePrompt.value=false;clearLookupMenus();emit('close')}
function closeUserEditor(){selectedUser.value=null;clearLookupMenus()}
function closeFlightEditor(){selectedFlight.value=null;clearLookupMenus()}
function closeCatalogEditor(){catalogDraft.value=null;clearLookupMenus()}
async function resetUserFilters(){userQ.value='';userStatus.value='all';userSort.value='created_desc';userPer.value=25;userPage.value=1;clearLookupMenus();await loadUsers()}
async function resetFlightFilters(){flightQ.value='';flightScope.value='all';flightUserId.value='';flightDateFrom.value='';flightDateTo.value='';flightSort.value='date_desc';flightPer.value=25;flightPage.value=1;clearLookupMenus();await loadFlights()}
async function resetCatalogFilters(){catalogQ.value='';catalogSort.value='name_asc';catalogPer.value=25;catalogPage.value=1;clearLookupMenus();await loadCatalog()}
async function resetAliasFilters(){aliasType.value='all';aliasSource.value='all';aliasQ.value='';aliasSort.value='date_desc';clearLookupMenus();await loadAliases()}
async function resetDuplicateFilters(){duplicateType.value='airports';duplicates.value=[];await loadDuplicates()}
async function resetAuditFilters(){auditQ.value='';auditEntity.value='';auditSort.value='date_desc';auditPer.value=50;auditPage.value=1;await loadAudit()}
const nav:Array<{id:AdminSection,label:string,sub:string}>=[
  {id:'dashboard',label:'Dashboard',sub:'Stan serwisu'}, {id:'users',label:'Użytkownicy',sub:'Konta i uprawnienia'},
  {id:'flights',label:'Loty',sub:'Wszystkie wpisy'}, {id:'airports',label:'Lotniska',sub:'11 tys. rekordów'},
  {id:'airlines',label:'Linie lotnicze',sub:'Przewoźnicy'}, {id:'aircraft',label:'Samoloty',sub:'Typy i warianty'},
  {id:'quality',label:'Jakość danych',sub:'Problemy i niespójności'},
  {id:'duplicates',label:'Duplikaty',sub:'Porównanie i scalanie'}, {id:'aliases',label:'Aliasy',sub:'Aliasowanie 2.0'},
  {id:'alerts',label:'Alerty',sub:'Wymaga uwagi'}, {id:'security',label:'Bezpieczeństwo',sub:'Logowania i blokady'},
  {id:'service_stats',label:'Statystyki serwisu',sub:'Cała Mapa Lotów'}, {id:'audit',label:'Log zmian',sub:'Historia administracji'},
]
function fmt(n:unknown){return new Intl.NumberFormat('pl-PL').format(Number(n)||0)}
function duration(s:unknown){let x=Number(s)||0;return `${Math.floor(x/3600).toLocaleString('pl-PL')} h ${Math.floor((x%3600)/60)} min`}
function date(v:unknown){if(!v)return '—';const d=new Date(String(v));return Number.isNaN(d.getTime())?String(v):d.toLocaleString('pl-PL')}
function val(v:unknown){return v===null||v===undefined||v===''?'—':String(v)}
function flash(m:string){message.value=m;window.setTimeout(()=>{if(message.value===m)message.value=''},3500)}
async function run(fn:()=>Promise<void>){loading.value=true;error.value='';try{await fn()}catch(e){error.value=e instanceof Error?e.message:'Nieznany błąd.'}finally{loading.value=false}}

async function loadDashboard(){await run(async()=>{const [d,st,q,n]=await Promise.all([getAdminDashboard(),getAdminServiceStats(),getAdminQuality(),getAdminNotifications()]);dashboard.value=d;serviceStats.value=st;quality.value=q;notifications.value=n})}
async function loadQuality(){await run(async()=>{quality.value=await getAdminQuality()})}
async function loadSecurity(){await run(async()=>{security.value=await getAdminSecurity()})}
async function loadServiceStats(){await run(async()=>{serviceStats.value=await getAdminServiceStats()})}
async function loadNotifications(){await run(async()=>{notifications.value=await getAdminNotifications()})}
async function loadUsers(){await run(async()=>{const r=await getAdminUsers({q:userQ.value,status:userStatus.value,sort:userSort.value,page:userPage.value,perPage:userPer.value});users.value=r.users;userPages.value=r.pages;userTotal.value=r.total})}
async function openUser(id:number){await run(async()=>{const [detail,activity]=await Promise.all([getAdminUser(id),getAdminUserActivity(id)]);selectedUser.value=detail;userActivity.value=activity;const u=selectedUser.value.user;userDraft.value={nick:u.nick,email:u.email,is_active:Boolean(Number(u.is_active)),is_admin:Boolean(Number(u.is_admin)),email_verified:Boolean(u.email_verified_at)};resetUrl.value=''})}
async function saveUser(){if(!selectedUser.value)return;await run(async()=>{const id=Number(selectedUser.value.user.id);const r=await updateAdminUser(id,userDraft.value);flash(r.message||'Zapisano.');selectedUser.value=null;clearLookupMenus();await loadUsers()})}
async function userAction(action:'sessions'|'activation'|'reset'){if(!selectedUser.value)return;const id=Number(selectedUser.value.user.id);await run(async()=>{if(action==='sessions'){if(!confirm('Unieważnić wszystkie sesje tego użytkownika?'))return;const r=await resetAdminUserSessions(id);flash(r.message||'Sesje unieważnione.')}if(action==='activation'){const r=await resendAdminActivation(id);flash(r.message||'Wysłano link.')}if(action==='reset'){const r=await createAdminResetLink(id);resetUrl.value=String(r.reset_url||'');flash(r.message||'Wygenerowano link.')}})}
async function showPreview(id:number){await run(async()=>{preview.value=await getAdminUserPreview(id);selectedUser.value=null;await nextTick();drawPreviewMap()})}
function closePreview(){preview.value=null;if(previewMap){previewMap.remove();previewMap=null}}
function drawPreviewMap(){
  if(!previewMapEl.value||!preview.value)return
  previewMap?.remove()
  const key=import.meta.env.VITE_MAPTILER_KEY
  previewMap=new MapLibreMap({container:previewMapEl.value,style:`https://api.maptiler.com/maps/topo-v2/style.json?key=${key}`,center:[15,30],zoom:1.4})
  previewMap.on('load',()=>{
    const valid=(lon:unknown,lat:unknown)=>Number.isFinite(Number(lon))&&Number.isFinite(Number(lat))
    const lineFeatures=(preview.value.flights||[]).filter((f:any)=>valid(f.departure_longitude,f.departure_latitude)&&valid(f.arrival_longitude,f.arrival_latitude)).map((f:any)=>({type:'Feature',properties:{},geometry:{type:'LineString',coordinates:[[Number(f.departure_longitude),Number(f.departure_latitude)],[Number(f.arrival_longitude),Number(f.arrival_latitude)]]}}))
    const airportMap=new Map<string,any>()
    for(const f of preview.value.flights||[]){
      if(valid(f.departure_longitude,f.departure_latitude)){const k=`${f.departure_airport_id||''}:${f.departure_longitude}:${f.departure_latitude}`;airportMap.set(k,{type:'Feature',properties:{code:f.departure_iata||f.departure_icao||''},geometry:{type:'Point',coordinates:[Number(f.departure_longitude),Number(f.departure_latitude)]}})}
      if(valid(f.arrival_longitude,f.arrival_latitude)){const k=`${f.arrival_airport_id||''}:${f.arrival_longitude}:${f.arrival_latitude}`;airportMap.set(k,{type:'Feature',properties:{code:f.arrival_iata||f.arrival_icao||''},geometry:{type:'Point',coordinates:[Number(f.arrival_longitude),Number(f.arrival_latitude)]}})}
    }
    const airportFeatures=[...airportMap.values()]
    previewMap?.addSource('preview-flights',{type:'geojson',data:{type:'FeatureCollection',features:lineFeatures} as any})
    previewMap?.addLayer({id:'preview-flights',type:'line',source:'preview-flights',paint:{'line-color':'#d62828','line-width':1.4,'line-opacity':0.6}})
    previewMap?.addSource('preview-airports',{type:'geojson',data:{type:'FeatureCollection',features:airportFeatures} as any})
    previewMap?.addLayer({id:'preview-airports-halo',type:'circle',source:'preview-airports',paint:{'circle-radius':6,'circle-color':'#ffffff','circle-stroke-color':'#ffffff','circle-stroke-width':2}})
    previewMap?.addLayer({id:'preview-airports',type:'circle',source:'preview-airports',paint:{'circle-radius':4,'circle-color':'#0b2d5c','circle-stroke-color':'#f5a623','circle-stroke-width':1.5}})
    previewMap?.addLayer({id:'preview-airport-labels',type:'symbol',source:'preview-airports',layout:{'text-field':['get','code'],'text-size':10,'text-offset':[0,1.25],'text-anchor':'top'},paint:{'text-color':'#0b2d5c','text-halo-color':'#ffffff','text-halo-width':1.5}})
  })
}

async function loadFlights(){await run(async()=>{const r=await getAdminFlights({q:flightQ.value,scope:flightScope.value,userId:flightUserId.value||null,dateFrom:flightDateFrom.value,dateTo:flightDateTo.value,sort:flightSort.value,page:flightPage.value,perPage:flightPer.value});flights.value=r.flights;flightPages.value=r.pages;flightTotal.value=r.total})}
async function openFlight(id:number){await run(async()=>{const r=await getAdminFlight(id);selectedFlight.value=r.flight;const f=r.flight;const normalizedTravelReason=normalizeTravelReason(f.travel_reason);flightDraft.value={...f,travel_reason:['p','b'].includes(normalizedTravelReason)?normalizedTravelReason:'',user_label:`${f.user_nick||''} · ${f.user_email||''}`,departure_label:`${f.departure_iata||f.departure_icao||'---'} · ${f.departure_airport||''}`,arrival_label:`${f.arrival_iata||f.arrival_icao||'---'} · ${f.arrival_airport||''}`,airline_label:f.airline_name?`${f.airline_name} · ${f.airline_iata||''}`:'',aircraft_label:f.aircraft_name||''}})}
async function saveFlight(){if(!selectedFlight.value)return;await run(async()=>{const r=await updateAdminFlight(Number(selectedFlight.value.id),flightDraft.value);flash(r.message||'Lot zapisany.');selectedFlight.value=null;clearLookupMenus();await loadFlights()})}
async function removeFlight(){if(!selectedFlight.value||!confirm(`Usunąć lot #${selectedFlight.value.id}? Ta operacja jest zapisywana w logu.`))return;await run(async()=>{const r=await deleteAdminFlight(Number(selectedFlight.value.id));flash(r.message||'Lot usunięty.');selectedFlight.value=null;await loadFlights()})}

async function loadCatalog(){await run(async()=>{const r=await getAdminCatalog(catalogType.value,{q:catalogQ.value,sort:catalogSort.value,page:catalogPage.value,perPage:catalogPer.value});catalogItems.value=r.items;catalogEditable.value=r.editable_columns;catalogPages.value=r.pages;catalogTotal.value=r.total})}
async function loadAircraftFieldOptions(){
  if(catalogType.value!=='aircraft'){catalogFieldOptions.value={manufacturer:[],family:[]};return}
  try{
    const [m,f]=await Promise.all([getAdminCatalogOptions('aircraft','manufacturer'),getAdminCatalogOptions('aircraft','family')])
    catalogFieldOptions.value={manufacturer:m.items.map(x=>String(x.label)),family:f.items.map(x=>String(x.label))}
  }catch{catalogFieldOptions.value={manufacturer:[],family:[]}}
}
async function openCatalog(id:number|null){
  clearLookupMenus()
  await loadAircraftFieldOptions()
  if(id===null){catalogDraft.value={id:null,is_active:1,country_label:'',manufacturer:'',family:'',variant:''};catalogEditable.value=catalogEditable.value.length?catalogEditable.value:[];return}
  await run(async()=>{
    const r=await getAdminCatalogItem(catalogType.value,id)
    catalogDraft.value={...r.item,country_label:r.item.country_name||''}
    catalogEditable.value=r.editable_columns
  })
}
async function saveCatalog(){if(!catalogDraft.value)return;await run(async()=>{const id=catalogDraft.value.id?Number(catalogDraft.value.id):null;const r=await saveAdminCatalogItem(catalogType.value,id,catalogDraft.value);flash(r.message||'Rekord zapisany.');catalogDraft.value=null;clearLookupMenus();await loadCatalog()})}

async function loadAliases(){await run(async()=>{aliases.value=(await getAdminAliases(aliasType.value,aliasQ.value,aliasSource.value)).aliases})}
async function addAlias(){await run(async()=>{const r=await createAdminAlias({entity_type:aliasDraft.value.entity_type,entity_id:Number(aliasDraft.value.entity_id),alias:aliasDraft.value.alias,source:'manual'});flash(r.message||'Alias dodany.');aliasDraft.value.alias='';aliasDraft.value.entity_id='';aliasTargetLabel.value='';await loadAliases()})}
function editAlias(a:any){selectedAlias.value=a;aliasEditDraft.value={entity_type:a.entity_type,entity_id:Number(a.entity_id),alias:a.alias,source:a.source||'manual',target_label:a.target_label}}
async function saveAliasEdit(){if(!selectedAlias.value)return;await run(async()=>{const r=await updateAdminAlias(Number(selectedAlias.value.id),aliasEditDraft.value);flash(r.message||'Alias zapisany.');selectedAlias.value=null;await loadAliases()})}
async function removeAlias(id:number){if(!confirm('Usunąć ten alias?'))return;await run(async()=>{const r=await deleteAdminAlias(id);flash(r.message||'Alias usunięty.');await loadAliases()})}
async function loadDuplicates(){duplicateDetails.value=[];duplicateGroup.value=null;duplicatePrimaryId.value=null;await run(async()=>{duplicates.value=(await getAdminDuplicates(duplicateType.value)).groups})}
async function openDuplicateGroup(g:any){duplicateGroup.value=g;await run(async()=>{const r=await getAdminDuplicateDetails(duplicateType.value,g.ids);duplicateDetails.value=r.items;duplicatePrimaryId.value=r.items.length?Number(r.items[0].id):null})}
async function mergeDuplicateGroup(){if(!duplicateGroup.value||!duplicatePrimaryId.value)return;const duplicateIds=duplicateDetails.value.map(x=>Number(x.id)).filter(id=>id!==duplicatePrimaryId.value);if(!duplicateIds.length)return;if(!confirm(`Scalić ${duplicateIds.length} rekord(y) do #${duplicatePrimaryId.value}? Powiązane loty zostaną przepięte, a stare nazwy zapisane jako aliasy.`))return;await run(async()=>{const r=await mergeAdminDuplicates({type:duplicateType.value,primary_id:duplicatePrimaryId.value,duplicate_ids:duplicateIds});flash(r.message||'Rekordy scalone.');duplicateDetails.value=[];duplicateGroup.value=null;await loadDuplicates();await loadQuality()})}
async function openHistory(entity:string,id:number){await run(async()=>{historyPanel.value=await getAdminHistory(entity,id)})}
async function loadUserActivity(id:number){await run(async()=>{userActivity.value=await getAdminUserActivity(id)})}
async function unlockUser(id:number){if(!confirm('Wyczyścić blokadę i licznik nieudanych logowań tego użytkownika?'))return;await run(async()=>{const r=await unlockAdminUser(id);flash(r.message||'Blokada usunięta.');await loadSecurity();if(selectedUser.value&&Number(selectedUser.value.user.id)===id)await loadUserActivity(id)})}
async function notificationAction(item:any,action:'read'|'dismiss'){await run(async()=>{await setAdminNotificationState(item.key,{is_read:true,is_dismissed:action==='dismiss'});notifications.value=await getAdminNotifications()})}
async function openAlert(item:any){await notificationAction(item,'read');await changeSection(item.section as AdminSection)}
async function globalSearchNow(){const q=globalQ.value.trim();if(q.length<2){globalGroups.value={};globalOpen.value=false;return}try{const r=await searchAdminGlobal(q);globalGroups.value=r.groups;globalOpen.value=true}catch{globalGroups.value={};globalOpen.value=false}}
function globalSearchInput(){if(globalTimer.value)window.clearTimeout(globalTimer.value);globalTimer.value=window.setTimeout(()=>void globalSearchNow(),180)}
async function openGlobalResult(item:any){globalOpen.value=false;globalQ.value='';globalGroups.value={};const sec=item.section as AdminSection;section.value=sec;if(sec==='users'){await openUser(Number(item.id));return}if(sec==='flights'){await openFlight(Number(item.id));return}if(['airports','airlines','aircraft'].includes(sec)){await loadCatalog();await openCatalog(Number(item.id));return}if(sec==='aliases'){aliasQ.value=item.title;await loadAliases();return}await changeSection(sec)}
async function loadAudit(){await run(async()=>{const r=await getAdminAudit({q:auditQ.value,entityType:auditEntity.value,sort:auditSort.value,page:auditPage.value,perPage:auditPer.value});auditItems.value=r.items;auditPages.value=r.pages;auditTotal.value=r.total})}

function lookup(kind:'users'|'airports'|'airlines'|'aircraft',q:string,target:string){
  lookupOpen.value=target;lookupActive.value=-1
  if(lookupTimer.value)window.clearTimeout(lookupTimer.value)
  lookupTimer.value=window.setTimeout(async()=>{
    if(!q.trim()){lookupItems.value=[];lookupActive.value=-1;return}
    try{lookupItems.value=(await adminLookup(kind,q)).items;lookupActive.value=lookupItems.value.length?0:-1}catch{lookupItems.value=[];lookupActive.value=-1}
  },180)
}
function chooseLookup(item:any,target:string){if(target==='flight-user'){flightDraft.value.user_id=item.id;flightDraft.value.user_label=item.label}if(target==='flight-dep'){flightDraft.value.departure_airport_id=item.id;flightDraft.value.departure_label=item.label}if(target==='flight-arr'){flightDraft.value.arrival_airport_id=item.id;flightDraft.value.arrival_label=item.label}if(target==='flight-airline'){flightDraft.value.airline_id=item.id;flightDraft.value.airline_label=item.label}if(target==='flight-aircraft'){flightDraft.value.aircraft_type_id=item.id;flightDraft.value.aircraft_label=item.label}if(target==='alias-target'){aliasDraft.value.entity_id=String(item.id);aliasTargetLabel.value=item.label}lookupOpen.value='';lookupItems.value=[];lookupActive.value=-1}
function lookupKeydown(event:KeyboardEvent,target:string){
  if(lookupOpen.value!==target||!lookupItems.value.length){if(event.key==='Escape')clearLookupMenus();return}
  if(event.key==='ArrowDown'){event.preventDefault();lookupActive.value=(lookupActive.value+1)%lookupItems.value.length}
  else if(event.key==='ArrowUp'){event.preventDefault();lookupActive.value=(lookupActive.value-1+lookupItems.value.length)%lookupItems.value.length}
  else if(event.key==='Enter'&&lookupActive.value>=0){event.preventDefault();chooseLookup(lookupItems.value[lookupActive.value],target)}
  else if(event.key==='Escape'){event.preventDefault();clearLookupMenus()}
}
function aliasLookupKind(){return aliasDraft.value.entity_type==='airport'?'airports':aliasDraft.value.entity_type==='airline'?'airlines':'aircraft'}
function aliasLookupInput(event: Event){const target=event.target as HTMLInputElement;lookup(aliasLookupKind(),target.value,'alias-target')}
function flightLookupInput(event: Event, kind:'users'|'airports'|'airlines'|'aircraft', target:string){const input=event.target as HTMLInputElement;lookup(kind,input.value,target)}
function countryLookupInput(event:Event){
  const q=(event.target as HTMLInputElement).value
  if(catalogDraft.value)catalogDraft.value.country_label=q
  countryLookupOpen.value=true
  if(lookupTimer.value)window.clearTimeout(lookupTimer.value)
  lookupTimer.value=window.setTimeout(async()=>{
    if(!q.trim()){countryLookupItems.value=[];countryLookupActive.value=-1;return}
    try{countryLookupItems.value=(await getAdminCountries(q)).items;countryLookupActive.value=countryLookupItems.value.length?0:-1}catch{countryLookupItems.value=[];countryLookupActive.value=-1}
  },180)
}
function countryLookupKeydown(event:KeyboardEvent){
  if(!countryLookupOpen.value||!countryLookupItems.value.length){if(event.key==='Escape')clearLookupMenus();return}
  if(event.key==='ArrowDown'){event.preventDefault();countryLookupActive.value=(countryLookupActive.value+1)%countryLookupItems.value.length}
  else if(event.key==='ArrowUp'){event.preventDefault();countryLookupActive.value=(countryLookupActive.value-1+countryLookupItems.value.length)%countryLookupItems.value.length}
  else if(event.key==='Enter'&&countryLookupActive.value>=0){event.preventDefault();chooseCountry(countryLookupItems.value[countryLookupActive.value])}
  else if(event.key==='Escape'){event.preventDefault();clearLookupMenus()}
}
function chooseCountry(item:any){
  if(!catalogDraft.value)return
  catalogDraft.value.country_id=Number(item.id)
  catalogDraft.value.country_label=item.label
  countryLookupOpen.value=false
  countryLookupItems.value=[]
  countryLookupActive.value=-1
}
function clearCountry(){
  if(!catalogDraft.value)return
  catalogDraft.value.country_id=null
  catalogDraft.value.country_name=null
  catalogDraft.value.country_label=''
  countryLookupOpen.value=false
  countryLookupItems.value=[]
  countryLookupActive.value=-1
}
async function copyResetUrl(){if(!resetUrl.value)return;await navigator.clipboard.writeText(resetUrl.value);flash('Link skopiowany.')}

async function changeSection(id:AdminSection){section.value=id;error.value='';message.value='';selectedUser.value=null;selectedFlight.value=null;catalogDraft.value=null;selectedAlias.value=null;historyPanel.value=null;closePreview();if(id==='dashboard')await loadDashboard();if(id==='users')await loadUsers();if(id==='flights')await loadFlights();if(['airports','airlines','aircraft'].includes(id)){catalogPage.value=1;await loadCatalog()}if(id==='quality')await loadQuality();if(id==='aliases')await loadAliases();if(id==='duplicates')await loadDuplicates();if(id==='alerts')await loadNotifications();if(id==='security')await loadSecurity();if(id==='service_stats')await loadServiceStats();if(id==='audit')await loadAudit()}
watch([userStatus,userSort,userPer],()=>{userPage.value=1;void loadUsers()});watch([flightScope,flightSort,flightPer],()=>{flightPage.value=1;void loadFlights()});watch([catalogSort,catalogPer],()=>{catalogPage.value=1;void loadCatalog()});watch([aliasType,aliasSource],()=>void loadAliases());watch(duplicateType,()=>void loadDuplicates());watch([auditEntity,auditSort,auditPer],()=>{auditPage.value=1;void loadAudit()})
onMounted(()=>void loadDashboard());onBeforeUnmount(()=>{previewMap?.remove();if(lookupTimer.value)window.clearTimeout(lookupTimer.value);if(globalTimer.value)window.clearTimeout(globalTimer.value)})
</script>

<template>
  <div class="runway-shell">
    <aside class="runway-nav">
      <div class="runway-brand"><span class="runway-logo-mark"><img :src="runwayLogo" alt="" aria-hidden="true"></span><div><strong>RUNWAY</strong><small>Mapa Lotów · administracja</small></div></div>
      <nav><button v-for="item in nav" :key="item.id" :class="{active:section===item.id}" @click="changeSection(item.id)"><strong>{{ item.label }} <span v-if="item.id==='alerts' && notifications.unread" class="nav-badge">{{notifications.unread}}</span></strong><small>{{ item.sub }}</small></button></nav>
      <div class="runway-nav-footer"><span>{{ props.user?.nick }}</span><a href="/">← Wróć do Mapy Lotów</a></div>
    </aside>

    <main class="runway-main">
      <header class="runway-top"><div><span class="eyebrow">ZAPLECZE SERWISU</span><h1>{{ nav.find(n=>n.id===section)?.label }}</h1></div><div class="runway-top-actions"><div class="global-search"><input v-model="globalQ" placeholder="Szukaj w RUNWAY…" autocomplete="off" @input="globalSearchInput" @focus="globalQ.trim().length>=2&&(globalOpen=true)" @keydown.escape="globalOpen=false"><div v-if="globalOpen" class="global-results"><template v-if="Object.keys(globalGroups).length"><section v-for="(rows,name) in globalGroups" :key="name"><strong>{{name}}</strong><button v-for="r in rows" :key="name+'-'+r.id" type="button" @click="openGlobalResult(r)"><b>{{r.title}}</b><small>{{r.subtitle}}</small></button></section></template><p v-else>Brak wyników.</p></div></div><button class="close" title="Wróć do mapy" @click="requestAppClose">×</button></div></header>
      <div v-if="error" class="notice error">{{ error }}</div><div v-if="message" class="notice ok">{{ message }}</div><div v-if="loading" class="loading">Przetwarzanie…</div>

      <section v-if="preview" class="preview-card">
        <div class="section-head"><div><h2>Podgląd jako użytkownik: {{ preview.user.nick }}</h2><p>Tryb tylko do odczytu. Nie wykonujesz operacji w imieniu użytkownika.</p></div><button class="secondary" @click="closePreview">Zamknij podgląd</button></div>
        <div ref="previewMapEl" class="preview-map"></div>
        <div class="mini-stats"><span><b>{{ fmt(preview.flights.length) }}</b> lotów</span><span><b>{{ fmt(preview.flights.reduce((s:number,f:any)=>s+Number(f.distance_km||0),0)) }}</b> km</span><span><b>{{ preview.user.privacy_mode }}</b> prywatność</span></div>
      </section>

      <template v-else-if="section==='dashboard' && dashboard">
        <div class="kpi-grid">
          <article><span>Użytkownicy</span><b>{{ fmt(dashboard.users.total) }}</b><small>{{ fmt(dashboard.users.new_30) }} nowych / 30 dni</small></article>
          <article><span>Loty</span><b>{{ fmt(dashboard.flights.total) }}</b><small>{{ fmt(dashboard.flights.planned) }} zaplanowanych</small></article>
          <article><span>Lotniska</span><b>{{ fmt(dashboard.catalogs.airports) }}</b><small>aktualna baza referencyjna</small></article>
          <article><span>Linie lotnicze</span><b>{{ fmt(dashboard.catalogs.airlines) }}</b><small>rekordów słownikowych</small></article>
          <article><span>Typy samolotów</span><b>{{ fmt(dashboard.catalogs.aircraft_types) }}</b><small>rekordów</small></article>
          <article><span>Dystans</span><b>{{ fmt(dashboard.flights.distance_km) }} km</b><small>{{ duration(dashboard.flights.duration_seconds) }}</small></article>
        </div>
        <div v-if="quality && serviceStats" class="dashboard-focus">
          <section class="card attention-card"><div class="section-head"><div><h2>Wymaga uwagi</h2><p>Najważniejsze sygnały z jakości danych i bezpieczeństwa.</p></div><button class="secondary" @click="changeSection('quality')">Otwórz jakość danych</button></div><div class="attention-grid"><button @click="changeSection('quality')"><span>Błędy danych</span><b>{{fmt(quality.totals.error)}}</b></button><button @click="changeSection('quality')"><span>Ostrzeżenia</span><b>{{fmt(quality.totals.warning)}}</b></button><button @click="changeSection('duplicates')"><span>Grupy duplikatów</span><b>{{fmt(Number(quality.duplicates.airports||0)+Number(quality.duplicates.airlines||0)+Number(quality.duplicates.aircraft||0))}}</b></button><button @click="changeSection('alerts')"><span>Nieprzeczytane alerty</span><b>{{fmt(notifications.unread)}}</b></button></div></section>
          <section class="card"><h2>Aktywność serwisu - 12 miesięcy</h2><div class="bar-list"><div v-for="m in serviceStats.monthly" :key="m.month"><span>{{m.month}}</span><div class="bar-track"><i :style="{width:`${Math.min(100,Number(m.flights||0)/Math.max(1,...serviceStats.monthly.map(x=>Number(x.flights||0)))*100)}%`}"></i></div><b>{{fmt(m.flights)}}</b></div></div></section>
        </div>
        <div class="two-cols"><section class="card"><h2>Ostatnie konta</h2><table><thead><tr><th>ID</th><th>Nick</th><th>E-mail</th><th>Utworzono</th></tr></thead><tbody><tr v-for="u in dashboard.recent_users" :key="u.id" @click="section='users';openUser(Number(u.id))"><td>#{{u.id}}</td><td>{{u.nick}}</td><td>{{u.email}}</td><td>{{date(u.created_at)}}</td></tr></tbody></table></section><section class="card"><h2>Ostatnio dodane loty</h2><table><thead><tr><th>ID</th><th>Użytkownik</th><th>Trasa</th><th>Data</th></tr></thead><tbody><tr v-for="f in dashboard.recent_flights" :key="f.id" @click="section='flights';openFlight(Number(f.id))"><td>#{{f.id}}</td><td>{{f.user_nick}}</td><td>{{f.departure_iata}} → {{f.arrival_iata}}</td><td>{{f.departure_date}}</td></tr></tbody></table></section></div>
      </template>

      <template v-else-if="section==='users'">
        <div class="toolbar"><input v-model="userQ" placeholder="Nick, e-mail lub ID" @keyup.enter="userPage=1;loadUsers()"><select v-model="userStatus"><option value="all">Wszystkie statusy</option><option value="active">Aktywne</option><option value="inactive">Nieaktywne</option><option value="verified">Zweryfikowane</option><option value="unverified">Niezweryfikowane</option><option value="admin">Administratorzy</option></select><select v-model="userSort"><option value="created_desc">Najnowsze</option><option value="created_asc">Najstarsze</option><option value="nick_asc">Nick A-Z</option><option value="flights_desc">Najwięcej lotów</option><option value="login_desc">Ostatnie logowanie</option></select><select v-model.number="userPer"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><button @click="userPage=1;loadUsers()">Szukaj</button><button class="reset-filter" @click="resetUserFilters">Reset</button></div>
        <div class="count">{{ fmt(userTotal) }} użytkowników</div><div class="table-wrap"><table><thead><tr><th><button class="sort-th" @click="toggleUserSort('id')">ID {{sortMark(userSort,'id')}}</button></th><th><button class="sort-th" @click="toggleUserSort('nick')">Nick {{sortMark(userSort,'nick')}}</button></th><th><button class="sort-th" @click="toggleUserSort('email')">E-mail {{sortMark(userSort,'email')}}</button></th><th><button class="sort-th" @click="toggleUserSort('status')">Status {{sortMark(userSort,'status')}}</button></th><th><button class="sort-th" @click="toggleUserSort('flights')">Loty {{sortMark(userSort,'flights')}}</button></th><th><button class="sort-th" @click="toggleUserSort('distance')">Dystans {{sortMark(userSort,'distance')}}</button></th><th><button class="sort-th" @click="toggleUserSort('created')">Rejestracja {{sortMark(userSort,'created')}}</button></th><th><button class="sort-th" @click="toggleUserSort('login')">Logowanie {{sortMark(userSort,'login')}}</button></th></tr></thead><tbody><tr v-for="u in users" :key="u.id" @click="openUser(u.id)"><td>#{{u.id}}</td><td><b>{{u.nick}}</b></td><td>{{u.email}}</td><td><span class="pill" :class="Boolean(Number(u.is_active))?'green':'red'">{{Boolean(Number(u.is_active))?'aktywny':'nieaktywny'}}</span><span v-if="Boolean(Number(u.is_admin))" class="pill blue">admin</span></td><td>{{fmt(u.flights_count)}}</td><td>{{fmt(u.distance_km)}} km</td><td>{{date(u.created_at)}}</td><td>{{date(u.last_login_at)}}</td></tr></tbody></table></div><div class="pager"><button :disabled="userPage<=1" @click="userPage--;loadUsers()">←</button><span>{{userPage}} / {{userPages}}</span><button :disabled="userPage>=userPages" @click="userPage++;loadUsers()">→</button></div>
      </template>

      <template v-else-if="section==='flights'">
        <div class="toolbar"><input v-model="flightQ" placeholder="Użytkownik, lotnisko, linia, numer lotu" @keyup.enter="flightPage=1;loadFlights()"><select v-model="flightScope"><option value="all">Wszystkie</option><option value="completed">Odbyte</option><option value="planned">Zaplanowane</option></select><input v-model="flightUserId" inputmode="numeric" placeholder="User ID"><div class="date-field"><input v-model="flightDateFrom" type="date" title="Wybierz z kalendarza lub wpisz datę z klawiatury"><button type="button" class="date-picker-button" title="Otwórz kalendarz" aria-label="Otwórz kalendarz" @click="openDatePicker"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18"></path></svg></button></div><div class="date-field"><input v-model="flightDateTo" type="date" title="Wybierz z kalendarza lub wpisz datę z klawiatury"><button type="button" class="date-picker-button" title="Otwórz kalendarz" aria-label="Otwórz kalendarz" @click="openDatePicker"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18"></path></svg></button></div><select v-model="flightSort"><option value="date_desc">Data malejąco</option><option value="date_asc">Data rosnąco</option><option value="user_asc">Użytkownik</option><option value="route_asc">Trasa</option></select><select v-model.number="flightPer"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><button @click="flightPage=1;loadFlights()">Filtruj</button><button class="reset-filter" @click="resetFlightFilters">Reset</button></div>
        <div class="count">{{fmt(flightTotal)}} lotów</div><div class="table-wrap"><table><thead><tr><th><button class="sort-th" @click="toggleFlightSort('id')">ID {{sortMark(flightSort,'id')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('user')">Użytkownik {{sortMark(flightSort,'user')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('date')">Data {{sortMark(flightSort,'date')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('route')">Trasa {{sortMark(flightSort,'route')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('flight_number')">Numer {{sortMark(flightSort,'flight_number')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('airline')">Linia {{sortMark(flightSort,'airline')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('aircraft')">Samolot {{sortMark(flightSort,'aircraft')}}</button></th><th><button class="sort-th" @click="toggleFlightSort('distance')">Dystans {{sortMark(flightSort,'distance')}}</button></th></tr></thead><tbody><tr v-for="f in flights" :key="f.id" @click="openFlight(f.id)"><td>#{{f.id}}</td><td>{{f.user_nick}}</td><td>{{f.departure_date}}</td><td><b>{{f.departure_iata||'---'}} → {{f.arrival_iata||'---'}}</b></td><td>{{val(f.flight_number)}}</td><td>{{val(f.airline_name)}}</td><td>{{val(f.aircraft_name)}}</td><td>{{fmt(f.distance_km)}} km</td></tr></tbody></table></div><div class="pager"><button :disabled="flightPage<=1" @click="flightPage--;loadFlights()">←</button><span>{{flightPage}} / {{flightPages}}</span><button :disabled="flightPage>=flightPages" @click="flightPage++;loadFlights()">→</button></div>
      </template>

      <template v-else-if="['airports','airlines','aircraft'].includes(section)">
        <div class="toolbar"><input v-model="catalogQ" :placeholder="section==='airports'?'IATA, ICAO, nazwa, miasto':section==='airlines'?'Nazwa, IATA, ICAO, callsign':'Nazwa, producent, model, wariant'" @keyup.enter="catalogPage=1;loadCatalog()"><select v-model="catalogSort"><option value="name_asc">Nazwa A-Z</option><option value="name_desc">Nazwa Z-A</option><option value="usage_desc">Najczęściej używane</option><option value="id_desc">Najnowsze ID</option></select><select v-model.number="catalogPer"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><button @click="catalogPage=1;loadCatalog()">Szukaj</button><button class="reset-filter" @click="resetCatalogFilters">Reset</button><button class="secondary" @click="openCatalog(null)">+ Dodaj rekord</button></div>
        <div class="count">{{fmt(catalogTotal)}} rekordów · struktura pól odczytywana z aktualnej bazy</div><div class="table-wrap"><table><thead><tr><th><button class="sort-th" @click="toggleCatalogSort('id')">ID {{sortMark(catalogSort,'id')}}</button></th><th><button class="sort-th" @click="toggleCatalogSort('name')">Nazwa {{sortMark(catalogSort,'name')}}</button></th><th v-if="section!=='aircraft'"><button class="sort-th" @click="toggleCatalogSort('iata')">IATA {{sortMark(catalogSort,'iata')}}</button></th><th v-if="section!=='aircraft'"><button class="sort-th" @click="toggleCatalogSort('icao')">ICAO {{sortMark(catalogSort,'icao')}}</button></th><th v-if="section==='airports'"><button class="sort-th" @click="toggleCatalogSort('city')">Miasto {{sortMark(catalogSort,'city')}}</button></th><th v-if="section==='aircraft'"><button class="sort-th" @click="toggleCatalogSort('manufacturer')">Producent {{sortMark(catalogSort,'manufacturer')}}</button></th><th v-if="section==='aircraft'"><button class="sort-th" @click="toggleCatalogSort('model')">Model / wariant {{sortMark(catalogSort,'model')}}</button></th><th><button class="sort-th" @click="toggleCatalogSort('usage')">Użycie {{sortMark(catalogSort,'usage')}}</button></th></tr></thead><tbody><tr v-for="r in catalogItems" :key="r.id" @click="openCatalog(Number(r.id))"><td>#{{r.id}}</td><td><b>{{r.name}}</b></td><td v-if="section!=='aircraft'">{{val(r.iata_code)}}</td><td v-if="section!=='aircraft'">{{val(r.icao_code)}}</td><td v-if="section==='airports'">{{val(r.city)}}</td><td v-if="section==='aircraft'">{{val(r.manufacturer)}}</td><td v-if="section==='aircraft'">{{val(r.model)}} {{val(r.variant)==='—'?'':r.variant}}</td><td>{{fmt(r.usage_count)}}</td></tr></tbody></table></div><div class="pager"><button :disabled="catalogPage<=1" @click="catalogPage--;loadCatalog()">←</button><span>{{catalogPage}} / {{catalogPages}}</span><button :disabled="catalogPage>=catalogPages" @click="catalogPage++;loadCatalog()">→</button></div>
      </template>

      <template v-else-if="section==='quality' && quality">
        <div class="section-head"><div><h2>Centrum jakości danych</h2><p>RUNWAY wskazuje problemy i podejrzane rekordy. Nic nie jest poprawiane automatycznie.</p></div><button class="secondary" @click="loadQuality">Odśwież analizę</button></div>
        <div class="kpi-grid quality-kpis"><article><span>Błędy</span><b>{{fmt(quality.totals.error)}}</b><small>wymagają sprawdzenia</small></article><article><span>Ostrzeżenia</span><b>{{fmt(quality.totals.warning)}}</b><small>mogą wpływać na wyniki</small></article><article><span>Informacje</span><b>{{fmt(quality.totals.info)}}</b><small>braki dopuszczalne, ale warte kontroli</small></article></div>
        <div class="quality-list"><details v-for="c in quality.categories" :key="c.key" :class="`severity-${c.severity}`"><summary><span class="quality-dot"></span><b>{{c.label}}</b><span>{{fmt(c.count)}}</span><button v-if="c.section==='flights'" type="button" @click.prevent.stop="changeSection('flights')">Przejdź do Lotów</button><button v-if="c.section==='airports'" type="button" @click.prevent.stop="changeSection('airports')">Przejdź do Lotnisk</button></summary><div v-if="c.samples?.length" class="quality-samples table-wrap"><table><thead><tr><th v-for="k in Object.keys(c.samples[0])" :key="k">{{k}}</th></tr></thead><tbody><tr v-for="(row,i) in c.samples" :key="i"><td v-for="k in Object.keys(c.samples[0])" :key="k">{{val(row[k])}}</td></tr></tbody></table></div><p v-else class="muted">Brak rekordów w tej kategorii.</p></details></div>
      </template>

      <template v-else-if="section==='aliases'">
        <div class="section-head"><div><h2>Aliasy 2.0</h2><p>Alternatywne nazwy wskazują jeden kanoniczny rekord. Źródło i użycie aliasu pozwalają później ocenić jego znaczenie w migracjach i importach.</p></div></div>
        <div class="alias-create"><select v-model="aliasDraft.entity_type"><option value="airport">Lotnisko</option><option value="airline">Linia</option><option value="aircraft_type">Typ samolotu</option></select><div class="lookup"><input :value="aliasTargetLabel" placeholder="Wyszukaj rekord docelowy" autocomplete="off" @input="aliasLookupInput" @keydown="lookupKeydown($event,'alias-target')"><div v-if="lookupOpen==='alias-target'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'alias-target')">{{x.label}}</button></div></div><input v-model="aliasDraft.alias" placeholder="Alias, np. B77W"><button :disabled="!aliasDraft.entity_id||!aliasDraft.alias" @click="addAlias">Dodaj alias</button></div>
        <div class="toolbar compact"><select v-model="aliasType"><option value="all">Wszystkie typy</option><option value="airport">Lotniska</option><option value="airline">Linie</option><option value="aircraft_type">Samoloty</option></select><select v-model="aliasSource"><option value="all">Wszystkie źródła</option><option value="manual">Ręczny</option><option value="migration">Migracja</option><option value="import">Import</option><option value="suggestion">Sugestia</option><option value="merge">Scalenie</option></select><input v-model="aliasQ" placeholder="Szukaj aliasu lub ID" @keyup.enter="loadAliases()"><button @click="loadAliases()">Szukaj</button><button class="reset-filter" @click="resetAliasFilters">Reset</button></div>
        <div class="table-wrap"><table><thead><tr><th><button class="sort-th" @click="toggleAliasSort('alias')">Alias {{sortMark(aliasSort,'alias')}}</button></th><th>Typ</th><th>Rekord docelowy</th><th>Źródło</th><th>Użycia</th><th>Ostatnie użycie</th><th>Dodał / zmienił</th><th>Akcje</th></tr></thead><tbody><tr v-for="a in aliasesSorted" :key="a.id" @dblclick="editAlias(a)"><td><b>{{a.alias}}</b></td><td>{{a.entity_type}}</td><td>#{{a.entity_id}} · {{a.target_label}}</td><td><span class="pill blue">{{a.source||'manual'}}</span></td><td>{{fmt(a.usage_count)}}</td><td>{{date(a.last_used_at)}}</td><td>{{val(a.updated_by_nick||a.created_by_nick)}}</td><td><button class="table-action" @click.stop="editAlias(a)">Edytuj</button><button class="danger-link" @click.stop="removeAlias(Number(a.id))">Usuń</button></td></tr></tbody></table></div>
        <p class="hint">Licznik użycia będzie zwiększany przez przyszły importer lub procedury, które rzeczywiście rozpoznają dane przez alias. Obecnie ręcznie dodane aliasy startują od 0.</p>
      </template>

      <template v-else-if="section==='duplicates'">
        <div class="section-head"><div><h2>Duplikaty i bezpieczne scalanie</h2><p>Najpierw porównaj rekordy. Dopiero potem wybierz rekord docelowy. RUNWAY przepina znane powiązania, zachowuje stare nazwy jako aliasy i zapisuje operację w logu.</p></div><div class="section-filter"><select v-model="duplicateType"><option value="airports">Lotniska</option><option value="airlines">Linie lotnicze</option><option value="aircraft">Typy samolotów</option></select><button class="reset-filter" @click="resetDuplicateFilters">Reset</button></div></div>
        <div v-if="!duplicateGroup" class="dup-grid"><article v-for="g in duplicates" :key="g.reason+g.key" @click="openDuplicateGroup(g)"><span class="pill orange">{{g.reason}}</span><h3>{{g.key}}</h3><p>{{g.count}} rekordy/rekordów</p><code>{{g.ids.join(', ')}}</code><button class="secondary">Porównaj →</button></article></div>
        <section v-else class="card merge-card"><div class="section-head"><div><h2>Porównanie: {{duplicateGroup.key}}</h2><p>Wybierz rekord, który ma pozostać. Pozostałe zostaną scalone do niego.</p></div><button class="secondary" @click="duplicateGroup=null;duplicateDetails=[]">← Wróć do grup</button></div><div class="table-wrap"><table><thead><tr><th>Docelowy</th><th>ID</th><th>Nazwa</th><th>IATA</th><th>ICAO</th><th>Miasto / producent</th><th>Model / wariant</th><th>Użycie w lotach</th><th>Aliasy</th></tr></thead><tbody><tr v-for="r in duplicateDetails" :key="r.id" :class="{'merge-primary': Number(r.id) === duplicatePrimaryId}"><td><input v-model="duplicatePrimaryId" type="radio" name="duplicate-primary" :value="Number(r.id)"></td><td>#{{r.id}}</td><td><b>{{val(r.name)}}</b></td><td>{{val(r.iata_code)}}</td><td>{{val(r.icao_code)}}</td><td>{{val(r.city||r.manufacturer)}}</td><td>{{val(r.model)}} {{r.variant||''}}</td><td>{{fmt(r.usage_count)}}</td><td>{{fmt(r.alias_count)}}</td></tr></tbody></table></div><div class="merge-warning"><b>Operacja nieodwracalna z poziomu UI.</b> Jeżeli baza zawiera dodatkowe nieznane powiązania z rekordami, backend zatrzyma scalanie zamiast ryzykować utratę danych.</div><div class="actions"><button class="danger" :disabled="!duplicatePrimaryId" @click="mergeDuplicateGroup">Scal do wybranego rekordu</button></div></section>
      </template>

      <template v-else-if="section==='alerts'">
        <div class="section-head"><div><h2>Centrum alertów</h2><p>Dynamiczne sygnały z jakości danych i bezpieczeństwa. Odczytanie lub ukrycie alertu dotyczy tylko Twojego konta administratora.</p></div><button class="secondary" @click="loadNotifications">Odśwież</button></div>
        <div class="alert-list"><article v-for="a in notifications.items" :key="a.key" :class="[`severity-${a.severity}`,{unread:!a.is_read}]"><div><span class="pill" :class="a.severity==='error'?'red':a.severity==='warning'?'orange':'blue'">{{a.severity}}</span><h3>{{a.title}}</h3><p>{{a.message}}</p></div><div class="alert-actions"><button class="secondary" @click="openAlert(a)">Otwórz</button><button v-if="!a.is_read" class="secondary" @click="notificationAction(a,'read')">Oznacz przeczytane</button><button class="reset-filter" @click="notificationAction(a,'dismiss')">Ukryj</button></div></article><p v-if="!notifications.items?.length" class="empty-state">Brak aktywnych alertów.</p></div>
      </template>

      <template v-else-if="section==='security' && security">
        <div class="section-head"><div><h2>Bezpieczeństwo kont</h2><p>Blokady logowania, nieudane próby i konta wymagające uwagi. RUNWAY nie pokazuje ani nie zna haseł użytkowników.</p></div><button class="secondary" @click="loadSecurity">Odśwież</button></div>
        <div class="kpi-grid"><article><span>Zablokowane konta</span><b>{{fmt(security.summary.locked_users)}}</b><small>aktywna blokada logowania</small></article><article><span>≥ 5 nieudanych prób</span><b>{{fmt(security.summary.elevated_failed_logins)}}</b><small>warto sprawdzić</small></article><article><span>Niezweryfikowane</span><b>{{fmt(security.summary.unverified)}}</b><small>wszystkie konta</small></article></div>
        <div class="two-cols"><section class="card"><h2>Ryzyko logowania</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Użytkownik</th><th>Próby</th><th>Ostatnia próba</th><th>Blokada do</th><th></th></tr></thead><tbody><tr v-for="u in security.login_risks" :key="u.id"><td>#{{u.id}}</td><td><button class="table-action" @click="openUser(Number(u.id))">{{u.nick}}</button></td><td>{{fmt(u.failed_login_attempts)}}</td><td>{{date(u.last_failed_login_at)}}</td><td>{{date(u.locked_until)}}</td><td><button class="secondary" @click="unlockUser(Number(u.id))">Wyczyść blokadę</button></td></tr></tbody></table></div></section><section class="card"><h2>Administratorzy</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Nick</th><th>Status</th><th>Ostatnie logowanie</th></tr></thead><tbody><tr v-for="u in security.admins" :key="u.id" @click="openUser(Number(u.id))"><td>#{{u.id}}</td><td><b>{{u.nick}}</b></td><td>{{Boolean(Number(u.is_active))?'aktywny':'nieaktywny'}}</td><td>{{date(u.last_login_at)}}</td></tr></tbody></table></div></section></div>
        <section class="card security-unverified"><h2>Niezweryfikowane od ponad 7 dni</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Nick</th><th>E-mail</th><th>Utworzono</th></tr></thead><tbody><tr v-for="u in security.unverified" :key="u.id" @click="openUser(Number(u.id))"><td>#{{u.id}}</td><td>{{u.nick}}</td><td>{{u.email}}</td><td>{{date(u.created_at)}}</td></tr></tbody></table></div></section>
      </template>

      <template v-else-if="section==='service_stats' && serviceStats">
        <div class="section-head"><div><h2>Statystyki całego serwisu</h2><p>Dane zbiorcze wszystkich kont i lotów. To statystyki administracyjne, niezależne od statystyk pojedynczego użytkownika.</p></div><button class="secondary" @click="loadServiceStats">Odśwież</button></div>
        <div class="kpi-grid"><article><span>Użytkownicy</span><b>{{fmt(serviceStats.totals.users)}}</b><small>{{fmt(serviceStats.totals.active_users_30)}} aktywnych / 30 dni</small></article><article><span>Loty</span><b>{{fmt(serviceStats.totals.flights)}}</b><small>{{fmt(serviceStats.totals.planned_flights)}} zaplanowanych</small></article><article><span>Dystans</span><b>{{fmt(serviceStats.totals.distance_km)}} km</b><small>{{duration(serviceStats.totals.duration_seconds)}}</small></article><article><span>Lotniska</span><b>{{fmt(serviceStats.totals.airports)}}</b></article><article><span>Linie</span><b>{{fmt(serviceStats.totals.airlines)}}</b></article><article><span>Typy samolotów</span><b>{{fmt(serviceStats.totals.aircraft_types)}}</b></article></div>
        <div class="two-cols stats-grid"><section class="card"><h2>Aktywność miesięczna</h2><div class="bar-list"><div v-for="m in serviceStats.monthly" :key="m.month"><span>{{m.month}}</span><div class="bar-track"><i :style="{width:`${Math.min(100,Number(m.flights||0)/Math.max(1,...serviceStats.monthly.map(x=>Number(x.flights||0)))*100)}%`}"></i></div><b>{{fmt(m.flights)}} lotów / {{fmt(m.new_users)}} kont</b></div></div></section><section class="card"><h2>Najczęstsze lotniska</h2><ol class="rank-list"><li v-for="x in serviceStats.top_airports" :key="x.id"><b>{{x.iata_code||'---'}} · {{x.name}}</b><span>{{fmt(x.operations)}} operacji</span></li></ol></section><section class="card"><h2>Najczęstsze linie</h2><ol class="rank-list"><li v-for="x in serviceStats.top_airlines" :key="x.id"><b>{{x.name}}</b><span>{{fmt(x.flights)}} lotów</span></li></ol></section><section class="card"><h2>Najczęstsze samoloty</h2><ol class="rank-list"><li v-for="x in serviceStats.top_aircraft" :key="x.id"><b>{{x.name}}</b><span>{{fmt(x.flights)}} lotów</span></li></ol></section><section class="card"><h2>Najczęstsze trasy kierunkowe</h2><ol class="rank-list"><li v-for="(x,i) in serviceStats.top_routes" :key="i"><b>{{x.departure_iata||'---'}} → {{x.arrival_iata||'---'}}</b><span>{{fmt(x.flights)}} lotów</span></li></ol></section></div>
      </template>

      <template v-else-if="section==='audit'">
        <div class="toolbar"><input v-model="auditQ" placeholder="Akcja, opis, administrator, ID" @keyup.enter="auditPage=1;loadAudit()"><select v-model="auditEntity"><option value="">Wszystkie obiekty</option><option value="user">Użytkownik</option><option value="flight">Lot</option><option value="airport">Lotnisko</option><option value="airline">Linia</option><option value="aircraft_type">Samolot</option><option value="alias">Alias</option></select><select v-model="auditSort"><option value="date_desc">Najnowsze</option><option value="date_asc">Najstarsze</option><option value="admin_asc">Administrator A-Z</option><option value="action_asc">Akcja A-Z</option><option value="entity_asc">Obiekt A-Z</option></select><select v-model.number="auditPer"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><button @click="auditPage=1;loadAudit()">Szukaj</button><button class="reset-filter" @click="resetAuditFilters">Reset</button></div><div class="count">{{fmt(auditTotal)}} operacji</div><div class="audit-list"><details v-for="a in auditItems" :key="a.id"><summary><time>{{date(a.created_at)}}</time><b>{{a.admin_nick||'konto usunięte'}}</b><span>{{a.summary}}</span><code>{{a.entity_type}} #{{a.entity_id||'—'}}</code></summary><div class="audit-json"><div><strong>Przed</strong><pre>{{a.before_json||'—'}}</pre></div><div><strong>Po</strong><pre>{{a.after_json||'—'}}</pre></div></div></details></div><div class="pager"><button :disabled="auditPage<=1" @click="auditPage--;loadAudit()">←</button><span>{{auditPage}} / {{auditPages}}</span><button :disabled="auditPage>=auditPages" @click="auditPage++;loadAudit()">→</button></div>
      </template>
    </main>

    <div v-if="selectedUser" class="drawer-backdrop"><aside class="drawer"><button class="drawer-x" @click="closeUserEditor">×</button><h2>{{selectedUser.user.nick}}</h2><p class="muted">#{{selectedUser.user.id}} · {{selectedUser.user.email}}</p><div class="mini-stats"><span><b>{{fmt(selectedUser.user.flights_count)}}</b> lotów</span><span><b>{{fmt(selectedUser.stats.unique_airports)}}</b> lotnisk</span><span><b>{{fmt(selectedUser.stats.unique_countries)}}</b> państw</span><span><b>{{fmt(selectedUser.stats.unique_airlines)}}</b> linii</span></div><div class="form-grid"><label>Nick<input v-model="userDraft.nick" maxlength="80" autocomplete="off"></label><label>E-mail<input v-model="userDraft.email" type="email" maxlength="190" autocomplete="off"></label>
      <div class="choice-field"><span>Status konta</span><div class="segmented"><button type="button" :class="{selected:userDraft.is_active===true}" @click="userDraft.is_active=true">Aktywne</button><button type="button" :class="{selected:userDraft.is_active===false}" @click="userDraft.is_active=false">Nieaktywne</button></div></div>
      <div class="choice-field"><span>Uprawnienia</span><div class="segmented"><button type="button" :class="{selected:userDraft.is_admin===false}" @click="userDraft.is_admin=false">Użytkownik</button><button type="button" :class="{selected:userDraft.is_admin===true}" @click="userDraft.is_admin=true">Administrator</button></div></div>
      <div class="choice-field"><span>Weryfikacja e-mail</span><div class="segmented"><button type="button" :class="{selected:userDraft.email_verified===true}" @click="userDraft.email_verified=true">Zweryfikowany</button><button type="button" :class="{selected:userDraft.email_verified===false}" @click="userDraft.email_verified=false">Niezweryfikowany</button></div></div>
    </div><dl class="details"><dt>Prywatność</dt><dd>{{selectedUser.user.privacy_mode}}</dd><dt>Public slug</dt><dd>{{val(selectedUser.user.public_slug)}}</dd><dt>Publiczny profil</dt><dd><a v-if="selectedUser.user.public_slug" :href="`/profil/${selectedUser.user.public_slug}`" target="_blank">/profil/{{selectedUser.user.public_slug}}</a><span v-else>—</span></dd><dt>Link udostępnienia</dt><dd><a v-if="selectedUser.user.share_token" :href="`/udostepniona/${selectedUser.user.share_token}`" target="_blank">otwórz link</a><span v-else>—</span></dd><dt>Ostatni lot</dt><dd>{{val(selectedUser.stats.last_flight_date)}}</dd><dt>Ostatnie logowanie</dt><dd>{{date(selectedUser.user.last_login_at)}}</dd><dt v-if="userActivity?.user?.failed_login_attempts!==undefined">Nieudane logowania</dt><dd v-if="userActivity?.user?.failed_login_attempts!==undefined">{{fmt(userActivity.user.failed_login_attempts)}}</dd><dt v-if="userActivity?.user?.locked_until">Blokada do</dt><dd v-if="userActivity?.user?.locked_until">{{date(userActivity.user.locked_until)}}</dd><dt v-if="userActivity?.user?.session_version!==undefined">Wersja sesji</dt><dd v-if="userActivity?.user?.session_version!==undefined">{{userActivity.user.session_version}}</dd></dl><div class="actions"><button @click="saveUser">Zapisz dane</button><button class="secondary" @click="closeUserEditor">Anuluj</button><button class="secondary" @click="showPreview(Number(selectedUser.user.id))">Podgląd jako użytkownik</button><button class="secondary" @click="flightUserId=String(selectedUser.user.id);selectedUser=null;section='flights';flightPage=1;loadFlights()">Pokaż wszystkie loty</button><button class="secondary" @click="openHistory('user',Number(selectedUser.user.id))">Historia zmian</button><button class="secondary" @click="userAction('sessions')">Wyloguj ze wszystkich sesji</button><button v-if="Number(userActivity?.user?.failed_login_attempts||0)>0 || userActivity?.user?.locked_until" class="secondary" @click="unlockUser(Number(selectedUser.user.id))">Wyczyść blokadę logowania</button><button v-if="!selectedUser.user.email_verified_at" class="secondary" @click="userAction('activation')">Wyślij link aktywacyjny</button><button class="secondary" @click="userAction('reset')">Utwórz awaryjny link resetu hasła</button></div><p class="admin-help">Awaryjny link służy wtedy, gdy użytkownik nie może samodzielnie przejść procedury „Nie pamiętasz hasła?”. RUNWAY tylko tworzy bezpieczny link ważny przez 60 minut - nie wysyła go automatycznie. Skopiuj go i przekaż użytkownikowi bezpiecznym kanałem.</p><div v-if="resetUrl" class="reset-url"><strong>Awaryjny link resetu - ważny 60 min</strong><input :value="resetUrl" readonly><div class="reset-url-actions"><button class="secondary" @click="copyResetUrl">Kopiuj link</button></div><small>Po otwarciu linku użytkownik ustawia nowe hasło. Wygenerowanie kolejnego linku zastępuje poprzedni.</small></div><h3>Ostatnia aktywność administracyjna</h3><ul v-if="userActivity?.audit?.length" class="recent activity-list"><li v-for="a in userActivity.audit.slice(0,5)" :key="a.id"><button @click="openHistory('user',Number(selectedUser.user.id))">{{date(a.created_at)}} · {{a.summary}}</button></li></ul><p v-else class="muted">Brak operacji administracyjnych na tym koncie.</p><h3>Ostatnie loty</h3><ul class="recent"><li v-for="f in selectedUser.recent_flights" :key="f.id"><button @click="selectedUser=null;section='flights';openFlight(Number(f.id))">#{{f.id}} · {{f.departure_iata}} → {{f.arrival_iata}} · {{f.departure_date}}</button></li></ul></aside></div>

    <div v-if="selectedFlight" class="drawer-backdrop"><aside class="drawer wide"><button class="drawer-x" @click="closeFlightEditor">×</button><h2>Lot #{{selectedFlight.id}}</h2><p class="muted">{{selectedFlight.departure_iata}} → {{selectedFlight.arrival_iata}} · {{selectedFlight.user_nick}}</p><div class="form-grid three">
      <label><span class="field-caption">Właściciel <small>ID: {{flightDraft.user_id}}</small></span><div class="lookup"><input v-model="flightDraft.user_label" autocomplete="off" @input="flightLookupInput($event,'users','flight-user')" @keydown="lookupKeydown($event,'flight-user')"><div v-if="lookupOpen==='flight-user'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'flight-user')">{{x.label}}</button></div></div></label><label>Data wylotu<div class="date-field"><input v-model="flightDraft.departure_date" type="date" title="Wybierz z kalendarza lub wpisz datę z klawiatury"><button type="button" class="date-picker-button" title="Otwórz kalendarz" aria-label="Otwórz kalendarz" @click="openDatePicker"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18"></path></svg></button></div></label><label>Godzina wylotu<input v-model="flightDraft.departure_time" type="time"></label>
      <label><span class="field-caption">Lotnisko wylotu <small>ID: {{flightDraft.departure_airport_id}}</small></span><div class="lookup"><input v-model="flightDraft.departure_label" autocomplete="off" @input="flightLookupInput($event,'airports','flight-dep')" @keydown="lookupKeydown($event,'flight-dep')"><div v-if="lookupOpen==='flight-dep'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'flight-dep')">{{x.label}}</button></div></div></label><label>Data przylotu<div class="date-field"><input v-model="flightDraft.arrival_date" type="date" title="Wybierz z kalendarza lub wpisz datę z klawiatury"><button type="button" class="date-picker-button" title="Otwórz kalendarz" aria-label="Otwórz kalendarz" @click="openDatePicker"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18"></path></svg></button></div></label><label>Godzina przylotu<input v-model="flightDraft.arrival_time" type="time"></label>
      <label><span class="field-caption">Lotnisko przylotu <small>ID: {{flightDraft.arrival_airport_id}}</small></span><div class="lookup"><input v-model="flightDraft.arrival_label" autocomplete="off" @input="flightLookupInput($event,'airports','flight-arr')" @keydown="lookupKeydown($event,'flight-arr')"><div v-if="lookupOpen==='flight-arr'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'flight-arr')">{{x.label}}</button></div></div></label><label><span class="field-caption">Linia lotnicza <small>ID: {{flightDraft.airline_id||'—'}} <button v-if="flightDraft.airline_id" type="button" class="inline-clear" @click="clearFlightLookup('airline')">wyczyść</button></small></span><div class="lookup"><input v-model="flightDraft.airline_label" placeholder="Zacznij wpisywać nazwę lub kod" autocomplete="off" @input="flightLookupInput($event,'airlines','flight-airline')" @keydown="lookupKeydown($event,'flight-airline')"><div v-if="lookupOpen==='flight-airline'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'flight-airline')">{{x.label}}</button></div></div></label><label><span class="field-caption">Typ samolotu <small>ID: {{flightDraft.aircraft_type_id||'—'}} <button v-if="flightDraft.aircraft_type_id" type="button" class="inline-clear" @click="clearFlightLookup('aircraft')">wyczyść</button></small></span><div class="lookup"><input v-model="flightDraft.aircraft_label" placeholder="Zacznij wpisywać typ" autocomplete="off" @input="flightLookupInput($event,'aircraft','flight-aircraft')" @keydown="lookupKeydown($event,'flight-aircraft')"><div v-if="lookupOpen==='flight-aircraft'" class="lookup-menu"><button v-for="(x,i) in lookupItems" :key="x.id" type="button" :class="{active:i===lookupActive}" @mouseenter="lookupActive=i" @click="chooseLookup(x,'flight-aircraft')">{{x.label}}</button></div></div></label>
      <label>Numer lotu<input v-model="flightDraft.flight_number" maxlength="20" autocomplete="off"></label>
      <label>Klasa<select v-model="flightDraft.travel_class"><option v-if="!hasOption(travelClassOptions,flightDraft.travel_class)" :value="flightDraft.travel_class">Wartość historyczna: {{flightDraft.travel_class}}</option><option v-for="o in travelClassOptions" :key="o.value" :value="o.value">{{o.label}}</option></select></label>
      <label>Typ miejsca<select v-model="flightDraft.seat_type"><option v-if="!hasOption(seatTypeOptions,flightDraft.seat_type)" :value="flightDraft.seat_type">Wartość historyczna: {{flightDraft.seat_type}}</option><option v-for="o in seatTypeOptions" :key="o.value" :value="o.value">{{o.label}}</option></select></label>
      <label>Numer miejsca<input v-model="flightDraft.seat_number" maxlength="10" autocomplete="off"></label><label>Cel podróży<select v-model="flightDraft.travel_reason"><option v-for="o in travelReasonOptions" :key="o.value" :value="o.value">{{o.label}}</option></select></label><label>Rejestracja<input v-model="flightDraft.aircraft_registration" maxlength="24" autocomplete="off"></label><label class="span3">Uwagi<textarea v-model="flightDraft.notes" rows="4" maxlength="2000"></textarea></label>
    </div><p class="hint">Dystans jest przeliczany po zmianie lotnisk. Czas jest przeliczany tylko po zmianie dat/godzin. Historyczne czasy z migracji pozostają nienaruszone, jeśli ich nie edytujesz.</p><div class="actions"><button @click="saveFlight">Zapisz lot</button><button class="secondary" @click="closeFlightEditor">Anuluj</button><button class="secondary" @click="openHistory('flight',Number(selectedFlight.id))">Historia zmian</button><button class="danger" @click="removeFlight">Usuń lot</button><button class="secondary" @click="selectedFlight=null;section='users';openUser(Number(flightDraft.user_id))">Przejdź do użytkownika</button></div></aside></div>

    <div v-if="catalogDraft" class="drawer-backdrop"><aside class="drawer"><button class="drawer-x" @click="closeCatalogEditor">×</button><h2>{{catalogDraft.id?`Edycja #${catalogDraft.id}`:'Nowy rekord'}}</h2><p class="muted">Pokazujemy tylko pola edytowalne, które faktycznie istnieją w aktualnej strukturze bazy.</p><div class="form-grid">
      <template v-for="field in orderedCatalogEditable" :key="field">
        <div v-if="field==='is_active'" class="choice-field"><span>{{catalogLabel(field)}}</span><div class="segmented"><button type="button" :class="{selected:Boolean(Number(catalogDraft.is_active))}" @click="catalogDraft.is_active=1">Aktywny</button><button type="button" :class="{selected:!Boolean(Number(catalogDraft.is_active))}" @click="catalogDraft.is_active=0">Nieaktywny</button></div><small v-if="catalogHint(field)" class="field-help">{{catalogHint(field)}}</small></div>
        <label v-else-if="field==='country_id'"><span class="field-caption">{{catalogLabel(field)}} <small>ID: {{catalogDraft.country_id||'—'}} <button v-if="catalogDraft.country_id" type="button" class="inline-clear" @click="clearCountry">wyczyść</button></small></span><div class="lookup"><input :value="catalogDraft.country_label" placeholder="Wpisz nazwę lub kod państwa" autocomplete="off" @input="countryLookupInput" @keydown="countryLookupKeydown"><div v-if="countryLookupOpen" class="lookup-menu"><button v-for="(x,i) in countryLookupItems" :key="x.id" type="button" :class="{active:i===countryLookupActive}" @mouseenter="countryLookupActive=i" @click="chooseCountry(x)">{{x.label}}</button></div></div></label>
        <label v-else-if="field==='country_name' && !catalogEditable.includes('country_id')" >{{catalogLabel(field)}}<input v-model="catalogDraft[field]" maxlength="120"></label>
        <label v-else-if="field==='timezone_name'">{{catalogLabel(field)}}<select v-model="catalogDraft[field]"><option value="">Brak / nieustalona</option><option v-if="catalogDraft[field] && !timezoneOptions.includes(String(catalogDraft[field]))" :value="catalogDraft[field]">{{catalogDraft[field]}} (wartość historyczna)</option><option v-for="tz in timezoneOptions" :key="tz" :value="tz">{{tz}}</option></select><small class="field-help">Wybierz poprawną strefę IANA, np. Europe/Warsaw lub Asia/Tokyo.</small></label>
        <label v-else-if="section==='aircraft' && field==='manufacturer'">{{catalogLabel(field)}}<select v-model="catalogDraft[field]"><option value="">Brak danych</option><option v-if="catalogDraft[field] && !catalogFieldOptions.manufacturer.includes(String(catalogDraft[field]))" :value="catalogDraft[field]">{{catalogDraft[field]}} (wartość historyczna)</option><option v-for="x in catalogFieldOptions.manufacturer" :key="x" :value="x">{{x}}</option></select><small class="field-help">{{catalogHint(field)}}</small></label>
        <label v-else-if="section==='aircraft' && field==='family'">{{catalogLabel(field)}}<select v-model="catalogDraft[field]"><option value="">Brak danych</option><option v-if="catalogDraft[field] && !catalogFieldOptions.family.includes(String(catalogDraft[field]))" :value="catalogDraft[field]">{{catalogDraft[field]}} (wartość historyczna)</option><option v-for="x in catalogFieldOptions.family" :key="x" :value="x">{{x}}</option></select><small class="field-help">{{catalogHint(field)}}</small></label>
        <label v-else-if="field!=='country_name'">{{catalogLabel(field)}}<input v-model="catalogDraft[field]" :type="['latitude','longitude'].includes(field)?'number':'text'" :step="['latitude','longitude'].includes(field)?'any':undefined" :maxlength="field==='iata_code'?3:field==='icao_code'?4:field==='callsign'?30:field==='name'?180:120" :style="['iata_code','icao_code'].includes(field)?'text-transform:uppercase':''" autocomplete="off"><small v-if="catalogHint(field)" class="field-help">{{catalogHint(field)}}</small></label>
      </template>
    </div><div class="actions"><button @click="saveCatalog">Zapisz rekord</button><button class="secondary" @click="closeCatalogEditor">Anuluj</button><button v-if="catalogDraft.id" class="secondary" @click="openHistory(section==='airports'?'airport':section==='airlines'?'airline':'aircraft_type',Number(catalogDraft.id))">Historia zmian</button></div></aside></div>
    <div v-if="selectedAlias" class="drawer-backdrop"><aside class="drawer compact-drawer"><button class="drawer-x" @click="selectedAlias=null">×</button><h2>Edycja aliasu #{{selectedAlias.id}}</h2><p class="muted">Rekord docelowy: #{{selectedAlias.entity_id}} · {{selectedAlias.target_label}}</p><div class="form-grid"><label>Alias<input v-model="aliasEditDraft.alias" maxlength="190"></label><label>Źródło<select v-model="aliasEditDraft.source"><option value="manual">Ręczny</option><option value="migration">Migracja</option><option value="import">Import</option><option value="suggestion">Sugestia</option><option value="merge">Scalenie</option></select></label></div><dl class="details"><dt>Liczba użyć</dt><dd>{{fmt(selectedAlias.usage_count)}}</dd><dt>Ostatnie użycie</dt><dd>{{date(selectedAlias.last_used_at)}}</dd><dt>Utworzył</dt><dd>{{val(selectedAlias.created_by_nick)}}</dd><dt>Ostatnia zmiana</dt><dd>{{val(selectedAlias.updated_by_nick)}}</dd></dl><div class="actions"><button @click="saveAliasEdit">Zapisz alias</button><button class="secondary" @click="selectedAlias=null">Anuluj</button><button class="secondary" @click="openHistory('alias',Number(selectedAlias.id))">Historia zmian</button></div></aside></div>

    <div v-if="historyPanel" class="history-backdrop"><aside class="history-panel"><button class="drawer-x" @click="historyPanel=null">×</button><h2>Historia zmian</h2><p class="muted">{{historyPanel.entity}} #{{historyPanel.entity_id}} · {{historyPanel.items.length}} zapisanych operacji</p><div v-if="historyPanel.items.length" class="history-list"><article v-for="h in historyPanel.items" :key="h.id"><header><time>{{date(h.created_at)}}</time><b>{{h.admin_nick||'konto usunięte'}}</b><span>{{h.summary}}</span></header><div v-if="h.changes?.length" class="history-changes"><div v-for="c in h.changes" :key="c.field"><strong>{{c.field}}</strong><span>{{val(c.before)}}</span><i>→</i><span>{{val(c.after)}}</span></div></div><p v-else class="muted">Operacja nie zawiera porównywalnych zmian pól.</p></article></div><p v-else class="empty-state">Brak zapisanej historii tego rekordu.</p></aside></div>

    <div v-if="closePrompt" class="confirm-backdrop">
      <section class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="close-confirm-title">
        <h3 id="close-confirm-title">Opuścić RUNWAY?</h3>
        <p>Czy na pewno chcesz wyjść z całego panelu administracyjnego i wrócić do Mapy Lotów?</p>
        <div class="confirm-actions"><button type="button" @click="confirmAppClose">Tak</button><button type="button" class="secondary" @click="cancelAppClose">Nie</button></div>
      </section>
    </div>
  </div>
</template>

<style scoped>
*{box-sizing:border-box}.runway-shell{position:fixed;inset:0;z-index:500;background:#f4f7fa;color:#243244;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:16px}.runway-nav{position:absolute;inset:0 auto 0 0;width:258px;background:#0b2d5c;color:#fff;display:flex;flex-direction:column;padding:22px 16px}.runway-brand{display:flex;gap:12px;align-items:center;padding:4px 8px 22px;border-bottom:1px solid rgba(255,255,255,.15)}.runway-logo-mark{width:35px;height:35px;display:grid;place-items:center;flex:0 0 35px}.runway-logo-mark img{display:block;width:35px;height:35px;object-fit:contain}.runway-brand strong{display:block;font-size:19px;letter-spacing:.08em}.runway-brand small{display:block;margin-top:4px;color:#bbcadb;font-size:13px}.runway-nav nav{display:grid;gap:5px;padding:18px 0;overflow:auto}.runway-nav nav button{border:0;background:transparent;color:#dce6f1;text-align:left;border-radius:9px;padding:10px 12px;cursor:pointer}.runway-nav nav button:hover,.runway-nav nav button.active{background:rgba(255,255,255,.12);color:#fff}.runway-nav nav strong{display:block;font-size:15px}.runway-nav nav small{display:block;margin-top:2px;font-size:12px;color:#aebfd2}.runway-nav-footer{margin-top:auto;padding:16px 8px 2px;border-top:1px solid rgba(255,255,255,.15);font-size:13px}.runway-nav-footer span,.runway-nav-footer a{display:block}.runway-nav-footer a{margin-top:9px;color:#fff;text-decoration:none;font-weight:700}.runway-main{position:absolute;inset:0 0 0 258px;padding:26px 30px 50px;overflow:auto}.runway-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em;color:#78889a}.runway-top h1{margin:4px 0 0;font-size:30px;color:#0b2d5c}.close,.drawer-x{border:0;background:#e8edf2;color:#536171;border-radius:9px;width:42px;height:42px;font-size:27px;cursor:pointer}.notice{padding:12px 15px;border-radius:9px;margin:0 0 14px;font-size:15px}.notice.error{background:#fff1f1;color:#9d3232;border:1px solid #efc6c6}.notice.ok{background:#effaf3;color:#2c6b45;border:1px solid #c8e7d2}.loading{position:fixed;top:18px;right:80px;z-index:800;background:#fff;border:1px solid #dae2e9;border-radius:20px;padding:8px 14px;font-size:13px;box-shadow:0 4px 18px #0002}.kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.kpi-grid article,.card,.preview-card{background:#fff;border:1px solid #dce4eb;border-radius:13px;box-shadow:0 3px 12px rgba(11,45,92,.05)}.kpi-grid article{padding:18px}.kpi-grid span{display:block;color:#708094;font-size:13px}.kpi-grid b{display:block;margin:6px 0 3px;color:#0b2d5c;font-size:26px}.kpi-grid small{font-size:13px;color:#738194}.two-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}.card{padding:17px}.card h2,.section-head h2,.preview-card h2{margin:0 0 12px;color:#0b2d5c;font-size:20px}.toolbar,.alias-create{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}.toolbar.compact{margin-top:16px}.toolbar input,.toolbar select,.alias-create input,.alias-create select,.form-grid input,.form-grid select,.form-grid textarea,.reset-url input,.section-head select{min-height:39px;border:1px solid #cfd9e2;border-radius:8px;background:#fff;padding:8px 10px;font:inherit;color:#243244}.toolbar input,.toolbar select,.section-head select{font-size:14px}.toolbar input{min-width:220px}input[type="date"]{
  appearance:auto;
  -webkit-appearance:auto;
  background-color:#fff;
}
input[type="date"]::-webkit-calendar-picker-indicator{
  opacity:0;
  width:0;
  height:0;
  margin:0;
  padding:0;
}
.date-field{
  position:relative;
  display:flex;
  align-items:stretch;
  width:100%;
  min-width:0;
}
.date-field input[type="date"]{
  width:100%;
  min-width:0;
  padding-right:39px!important;
}
.date-picker-button{
  position:absolute;
  right:5px;
  top:50%;
  transform:translateY(-50%);
  width:30px;
  height:30px;
  display:grid;
  place-items:center;
  border:0;
  border-radius:6px;
  background:transparent;
  color:#526172;
  cursor:pointer;
  padding:0;
}
.date-picker-button:hover{
  background:#edf2f6;
  color:#0b2d5c;
}
.date-picker-button svg{
  width:18px;
  height:18px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
  stroke-linejoin:round;
  pointer-events:none;
}
.toolbar .date-field{
  width:150px;
  min-width:150px;
  flex:0 0 150px;
}
.toolbar .date-field input[type="date"]{
  width:100%;
  min-width:0;
  background:#fff;
}
.toolbar .date-field .date-picker-button{
  min-height:0;
  width:30px;
  height:30px;
  padding:0;
  border:0;
  border-radius:6px;
  background:transparent!important;
  color:#526172!important;
  box-shadow:none;
}
.toolbar .date-field .date-picker-button:hover{
  background:#edf2f6!important;
  color:#0b2d5c!important;
}
.toolbar button,.actions button,.secondary,.alias-create button,.pager button{min-height:39px;border:0;border-radius:8px;padding:8px 13px;background:#0b2d5c;color:#fff;font-size:14px;font-weight:750;cursor:pointer}.secondary,.actions .secondary{background:#edf2f6;color:#0b2d5c;border:1px solid #ccd7e1}.reset-filter{background:#fff!important;color:#5b6878!important;border:1px solid #cbd5df!important}.reset-filter:hover{background:#f3f6f8!important}.danger,.actions .danger{background:#a93434}.danger-link{border:0;background:transparent;color:#a93434;font-size:13px;font-weight:750;cursor:pointer}.count{font-size:13px;color:#6e7c8c;margin:8px 0}.table-wrap{overflow:auto;background:#fff;border:1px solid #dce4eb;border-radius:11px}table{width:100%;border-collapse:collapse;font-size:13px}th{position:sticky;top:0;background:#f0f4f7;color:#526172;font-size:11px;text-transform:uppercase;letter-spacing:.04em;text-align:left!important;padding:10px;border-bottom:1px solid #dce4eb}td{padding:10px;border-bottom:1px solid #edf1f4;white-space:nowrap;text-align:left!important}tbody tr{transition:background .12s ease}tbody tr:hover{background:#eaf1f8}table button{text-align:left}.sort-th{border:0;background:transparent;color:inherit;padding:0;font:inherit;font-weight:800;text-transform:inherit;letter-spacing:inherit;cursor:pointer;white-space:nowrap}.sort-th:hover{color:#0b2d5c;text-decoration:underline;text-underline-offset:3px}.table-wrap .danger-link{font-size:12px}.pill{display:inline-flex;margin-right:5px;border-radius:4px;padding:3px 7px;font-size:10px;font-weight:800;border:1px solid transparent}.pill.green{background:#edf7f0;color:#285f3c;border-color:#cfe5d6}.pill.red{background:#faeeee;color:#8b3030;border-color:#eccccc}.pill.blue{background:#edf3f9;color:#315b82;border-color:#d1deeb}.pill.orange{background:#fff4e5;color:#865a1b;border-color:#edd9b9}.pager{display:flex;justify-content:center;align-items:center;gap:12px;margin:14px 0;font-size:14px}.pager button:disabled{opacity:.35;cursor:default}.section-head{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:14px}.section-filter{display:flex;align-items:center;gap:8px}.section-head p,.muted,.hint{margin:4px 0;color:#708094;font-size:13px;line-height:1.5}.drawer-backdrop{position:fixed;inset:0;z-index:900;background:rgba(17,31,47,.32);display:flex;justify-content:flex-end;padding:12px 12px 12px 0}.drawer{width:min(560px,95vw);height:calc(100vh - 24px);overflow:auto;overflow-x:hidden;background:#fff;padding:28px;box-shadow:-12px 0 40px #0003;border-radius:12px}.drawer.wide{width:min(820px,96vw)}.drawer-x{float:right}.drawer h2{margin:0;color:#0b2d5c;font-size:24px}.form-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;margin:18px 0;width:100%;min-width:0}.form-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid>*{min-width:0;max-width:100%}.form-grid label{display:grid;gap:5px;min-width:0;max-width:100%;font-size:13px;font-weight:700;color:#526172}.form-grid input,.form-grid select,.form-grid textarea{width:100%;max-width:100%;min-width:0}.field-caption{display:flex;align-items:baseline;justify-content:space-between;gap:8px;min-height:17px}.field-caption small{font-size:10px;font-weight:600;color:#8995a3;white-space:nowrap}.field-help{font-size:11px!important;font-weight:500!important;color:#7b8897!important;line-height:1.35}.form-grid .check{display:flex;align-items:center;gap:8px;background:#f7f9fb;padding:10px;border-radius:6px}.form-grid .check input{min-height:auto}.choice-field{display:grid;gap:6px;font-size:13px;font-weight:700;color:#526172}.segmented{display:flex;gap:0;border:1px solid #cfd9e2;border-radius:6px;overflow:hidden;background:#fff;width:max-content;max-width:100%}.segmented button{min-height:39px;border:0;border-right:1px solid #cfd9e2;border-radius:0;background:#fff;color:#526172;padding:7px 12px;font:inherit;font-weight:700;cursor:pointer}.segmented button:last-child{border-right:0}.segmented button.selected{background:#0b2d5c;color:#fff}.inline-clear{border:0;background:transparent;color:#9a3a3a;padding:0 3px;font-size:11px;font-weight:700;cursor:pointer;text-decoration:underline}.span3{grid-column:1/-1}.actions{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.mini-stats{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0}.mini-stats span{background:#f2f6f9;border-radius:8px;padding:8px 10px;font-size:12px}.mini-stats b{font-size:15px;color:#0b2d5c}.details{display:grid;grid-template-columns:140px 1fr;gap:6px 12px;font-size:13px}.details dt{color:#718093}.details dd{margin:0;font-weight:700}.recent{list-style:none;padding:0}.recent button{width:100%;border:0;border-bottom:1px solid #edf1f4;background:#fff;text-align:left;padding:9px 0;font-size:13px;cursor:pointer}.admin-help{margin:10px 0 12px;padding:10px 12px;background:#f6f8fa;border-left:3px solid #9eb2c7;color:#657486;font-size:12px;line-height:1.5}.reset-url{background:#fff8e9;border:1px solid #ecd8ab;border-radius:9px;padding:12px;display:grid;gap:8px}.reset-url-actions{display:flex;gap:8px}.reset-url small{color:#7b6a43;font-size:11px;line-height:1.4}.preview-card{padding:18px}.preview-map{height:430px;border-radius:10px;overflow:hidden;border:1px solid #d8e1e9}.alias-create{background:#fff;border:1px solid #dce4eb;border-radius:11px;padding:14px}.lookup{position:relative;width:100%;min-width:0;max-width:100%}.lookup input{width:100%;max-width:100%;min-width:0}.lookup-menu{position:absolute;top:43px;left:0;right:0;z-index:20;background:#fff;border:1px solid #cfd9e2;border-radius:8px;box-shadow:0 8px 22px #0002;max-height:250px;overflow:auto}.lookup-menu button{display:block;width:100%;text-align:left;border:0;background:#fff;color:#243244;padding:9px;font-size:13px;cursor:pointer}.lookup-menu button:hover,.lookup-menu button.active{background:#eaf1f8;color:#0b2d5c}.recent button:hover{background:#f3f7fa}.audit-list details:hover{border-color:#bfcdda;background:#fbfdff}.dup-grid article:hover{border-color:#bfcdda;background:#fbfdff}.dup-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.dup-grid article{background:#fff;border:1px solid #dce4eb;border-radius:10px;padding:14px}.dup-grid h3{font-size:14px;color:#0b2d5c;word-break:break-word}.dup-grid p,.dup-grid code{font-size:12px}.audit-list{display:grid;gap:8px}.audit-list details{background:#fff;border:1px solid #dce4eb;border-radius:9px}.audit-list summary{display:grid;grid-template-columns:170px 140px 1fr 130px;gap:10px;padding:11px;align-items:center;cursor:pointer;font-size:13px}.audit-list code{font-size:11px}.audit-json{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 12px 12px}.audit-json pre{overflow:auto;max-height:240px;background:#f5f7f9;padding:9px;border-radius:7px;font-size:11px;white-space:pre-wrap}
.confirm-backdrop{position:fixed;inset:0;z-index:1200;background:rgba(17,31,47,.48);display:grid;place-items:center;padding:20px}.confirm-box{width:min(420px,94vw);background:#fff;border:1px solid #dce4eb;border-radius:12px;box-shadow:0 20px 60px #0004;padding:22px}.confirm-box h3{margin:0 0 8px;color:#0b2d5c;font-size:20px}.confirm-box p{margin:0;color:#647487;font-size:14px;line-height:1.5}.confirm-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:20px}.confirm-actions button{min-height:39px;border:0;border-radius:7px;padding:8px 15px;background:#0b2d5c;color:#fff;font-size:14px;font-weight:750;cursor:pointer}.confirm-actions .secondary{background:#edf2f6;color:#0b2d5c;border:1px solid #ccd7e1}

.nav-badge{display:inline-grid!important;place-items:center;min-width:19px;height:19px;margin-left:6px;padding:0 5px;border-radius:9px;background:#f5a623;color:#0b2d5c!important;font-size:10px!important;font-weight:900}
.runway-top-actions{display:flex;align-items:center;gap:10px}.global-search{position:relative;width:min(420px,40vw)}.global-search>input{width:100%;height:42px;border:1px solid #cbd6e0;border-radius:9px;background:#fff;padding:9px 12px;font-size:14px;color:#243244;box-shadow:0 2px 10px #0b2d5c0c}.global-results{position:absolute;top:48px;right:0;z-index:850;width:min(520px,70vw);max-height:62vh;overflow:auto;background:#fff;border:1px solid #ccd7e1;border-radius:10px;box-shadow:0 15px 36px #0003;padding:8px}.global-results section{padding:7px 0;border-bottom:1px solid #edf1f4}.global-results section:last-child{border-bottom:0}.global-results section>strong{display:block;padding:3px 9px;color:#748294;font-size:10px;text-transform:uppercase;letter-spacing:.08em}.global-results button{display:block;width:100%;border:0;background:#fff;text-align:left;padding:8px 9px;border-radius:6px;cursor:pointer}.global-results button:hover{background:#eaf1f8}.global-results button b{display:block;color:#0b2d5c;font-size:13px}.global-results button small{display:block;margin-top:2px;color:#748294;font-size:11px}.global-results>p{margin:8px;color:#748294;font-size:12px}
.dashboard-focus{display:grid;grid-template-columns:1.1fr .9fr;gap:14px;margin-top:14px}.attention-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.attention-grid button{border:1px solid #dce4eb;border-radius:8px;background:#f8fafc;padding:10px;text-align:left;cursor:pointer}.attention-grid button:hover{background:#edf3f8}.attention-grid span{display:block;color:#6e7e90;font-size:11px}.attention-grid b{display:block;margin-top:4px;color:#0b2d5c;font-size:22px}
.bar-list{display:grid;gap:7px}.bar-list>div{display:grid;grid-template-columns:62px minmax(80px,1fr) auto;gap:8px;align-items:center;font-size:11px}.bar-track{height:8px;background:#e9eef3;border-radius:4px;overflow:hidden}.bar-track i{display:block;height:100%;background:#0b2d5c;border-radius:4px}.bar-list b{font-size:11px;color:#526172;white-space:nowrap}
.quality-kpis{margin-bottom:14px}.quality-list{display:grid;gap:9px}.quality-list details{background:#fff;border:1px solid #dce4eb;border-left-width:4px;border-radius:9px;overflow:hidden}.quality-list details.severity-error{border-left-color:#b53a3a}.quality-list details.severity-warning{border-left-color:#d58a20}.quality-list details.severity-info{border-left-color:#4376a7}.quality-list summary{display:grid;grid-template-columns:12px 1fr auto auto;gap:10px;align-items:center;padding:13px;cursor:pointer}.quality-list summary>button{border:1px solid #cbd6e0;background:#f4f7fa;color:#0b2d5c;border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer}.quality-dot{width:9px;height:9px;border-radius:50%;background:#8795a5}.severity-error .quality-dot{background:#b53a3a}.severity-warning .quality-dot{background:#d58a20}.severity-info .quality-dot{background:#4376a7}.quality-samples{margin:0 12px 12px}
.dup-grid article{cursor:pointer}.dup-grid article .secondary{margin-top:10px}.merge-card{margin-top:8px}.merge-primary{background:#edf5ff!important}.merge-warning{margin-top:12px;padding:11px 13px;border:1px solid #ebd39f;background:#fff8e8;border-radius:8px;color:#775a22;font-size:12px}.merge-warning b{display:block;margin-bottom:3px}.table-action{border:0;background:transparent;color:#315f8d;font-weight:700;font-size:12px;cursor:pointer;padding:2px 5px}.table-action:hover{text-decoration:underline}
.alert-list{display:grid;gap:10px}.alert-list article{display:flex;justify-content:space-between;gap:20px;align-items:center;background:#fff;border:1px solid #dce4eb;border-left:4px solid #8da0b2;border-radius:9px;padding:14px}.alert-list article.severity-error{border-left-color:#b53a3a}.alert-list article.severity-warning{border-left-color:#d58a20}.alert-list article.severity-info{border-left-color:#4376a7}.alert-list article.unread{box-shadow:0 3px 14px #0b2d5c14}.alert-list h3{display:inline;margin:0 0 0 6px;color:#0b2d5c;font-size:15px}.alert-list p{margin:7px 0 0;color:#667688;font-size:12px}.alert-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.alert-actions button{min-height:34px;padding:6px 9px;font-size:11px;border-radius:6px}.empty-state{padding:28px;background:#fff;border:1px dashed #cbd6e0;border-radius:9px;text-align:center;color:#718093}
.security-unverified{margin-top:14px}.stats-grid{align-items:start}.rank-list{list-style:none;padding:0;margin:0;display:grid;gap:7px}.rank-list li{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf1f4;padding:8px 0;font-size:12px}.rank-list li:last-child{border-bottom:0}.rank-list b{color:#0b2d5c}.rank-list span{color:#718093;white-space:nowrap}
.compact-drawer{width:min(500px,95vw)}.history-backdrop{position:fixed;inset:0;z-index:980;background:rgba(17,31,47,.38);display:flex;justify-content:flex-end;padding:12px}.history-panel{width:min(720px,96vw);height:calc(100vh - 24px);overflow:auto;background:#fff;border-radius:12px;padding:26px;box-shadow:-12px 0 40px #0003}.history-panel h2{margin:0;color:#0b2d5c}.history-list{display:grid;gap:10px;margin-top:16px}.history-list article{border:1px solid #dce4eb;border-radius:9px;padding:12px}.history-list article>header{display:grid;grid-template-columns:155px 120px 1fr;gap:9px;font-size:12px;align-items:center}.history-list time{color:#748294}.history-list header b{color:#0b2d5c}.history-changes{display:grid;gap:5px;margin-top:10px}.history-changes>div{display:grid;grid-template-columns:130px 1fr 20px 1fr;gap:7px;align-items:start;padding:6px 0;border-top:1px solid #edf1f4;font-size:11px}.history-changes strong{color:#526172}.history-changes i{text-align:center;color:#8a98a6}.history-changes span{word-break:break-word}.activity-list{max-height:170px;overflow:auto}
@media(max-width:1050px){.kpi-grid,.dup-grid{grid-template-columns:repeat(2,1fr)}.dashboard-focus{grid-template-columns:1fr}.attention-grid{grid-template-columns:repeat(2,1fr)}.global-search{width:300px}.two-cols{grid-template-columns:1fr}.form-grid.three{grid-template-columns:1fr 1fr}.audit-list summary{grid-template-columns:1fr 1fr}.runway-nav{width:220px}.runway-main{left:220px}}
</style>
