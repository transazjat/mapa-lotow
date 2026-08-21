<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
  watch,
} from 'vue'

import {
  deleteAdminFlight,
  getAdminDashboard,
  getAdminFlight,
  getAdminFlights,
  getAdminUser,
  getAdminUsers,
  updateAdminUser,
} from '../services/adminApi'

import type {
  AccountUser,
} from '../types/account'

import type {
  AdminDashboardResponse,
  AdminFlightListItem,
  AdminFlightsResponse,
  AdminUserListItem,
  AdminUserResponse,
  AdminUsersResponse,
} from '../types/admin'


type AdminSection =
  | 'dashboard'
  | 'users'
  | 'flights'


const props = defineProps<{
  user: AccountUser | null
}>()


const activeSection =
  ref<AdminSection>(
    'dashboard',
  )

const loading =
  ref(false)

const error =
  ref<string | null>(
    null,
  )

const message =
  ref<string | null>(
    null,
  )

const dashboard =
  ref<AdminDashboardResponse | null>(
    null,
  )

const users =
  ref<AdminUsersResponse | null>(
    null,
  )

const flights =
  ref<AdminFlightsResponse | null>(
    null,
  )

const selectedUser =
  ref<AdminUserResponse | null>(
    null,
  )

const selectedFlight =
  ref<Record<string, unknown> | null>(
    null,
  )

const deletingFlight =
  ref<AdminFlightListItem | null>(
    null,
  )

const userSearch =
  ref('')

const userStatus =
  ref('all')

const userPage =
  ref(1)

const flightSearch =
  ref('')

const flightScope =
  ref('all')

const flightUserId =
  ref('')

const flightDateFrom =
  ref('')

const flightDateTo =
  ref('')

const flightPage =
  ref(1)


const authorized =
  computed(
    () =>
      Boolean(
        props.user?.is_admin,
      ),
  )


const formatNumber =
  new Intl.NumberFormat(
    'pl-PL',
  )


function formatDate(
  value: unknown,
): string {
  if (
    value === null
    || value === undefined
    || value === ''
  ) {
    return '—'
  }

  const date =
    new Date(
      String(value),
    )

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return String(value)
  }

  return new Intl.DateTimeFormat(
    'pl-PL',
    {
      dateStyle: 'medium',
    },
  ).format(
    date,
  )
}


function formatDateTime(
  value: unknown,
): string {
  if (
    value === null
    || value === undefined
    || value === ''
  ) {
    return '—'
  }

  const date =
    new Date(
      String(value),
    )

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return String(value)
  }

  return new Intl.DateTimeFormat(
    'pl-PL',
    {
      dateStyle: 'short',
      timeStyle: 'short',
    },
  ).format(
    date,
  )
}


function formatDuration(
  seconds: unknown,
): string {
  const total =
    Number(
      seconds ?? 0,
    )

  const hours =
    Math.floor(
      total / 3600,
    )

  const minutes =
    Math.floor(
      (
        total % 3600
      ) / 60,
    )

  return `${hours}:${String(minutes).padStart(2, '0')}`
}


async function run(
  action: () => Promise<void>,
): Promise<void> {
  loading.value = true
  error.value = null
  message.value = null

  try {
    await action()
  } catch (err) {
    error.value =
      err instanceof Error
        ? err.message
        : 'Wystąpił błąd.'
  } finally {
    loading.value = false
  }
}


async function loadDashboard(): Promise<void> {
  await run(
    async () => {
      dashboard.value =
        await getAdminDashboard()
    },
  )
}


async function loadUsers(): Promise<void> {
  await run(
    async () => {
      users.value =
        await getAdminUsers({
          q: userSearch.value,
          status:
            userStatus.value,
          page:
            userPage.value,
          perPage: 25,
        })
    },
  )
}


async function loadFlights(): Promise<void> {
  const parsedUserId =
    Number(
      flightUserId.value,
    )

  await run(
    async () => {
      flights.value =
        await getAdminFlights({
          q:
            flightSearch.value,
          scope:
            flightScope.value,
          userId:
            Number.isFinite(
              parsedUserId,
            )
            && parsedUserId > 0
              ? parsedUserId
              : null,
          dateFrom:
            flightDateFrom.value,
          dateTo:
            flightDateTo.value,
          page:
            flightPage.value,
          perPage: 25,
        })
    },
  )
}


async function setSection(
  section: AdminSection,
): Promise<void> {
  activeSection.value =
    section

  selectedUser.value =
    null

  selectedFlight.value =
    null

  if (section === 'dashboard') {
    await loadDashboard()
  } else if (section === 'users') {
    await loadUsers()
  } else {
    await loadFlights()
  }
}


async function openUser(
  user: AdminUserListItem | number,
): Promise<void> {
  const id =
    typeof user === 'number'
      ? user
      : user.id

  await run(
    async () => {
      selectedUser.value =
        await getAdminUser(
          id,
        )
    },
  )
}


async function saveUserFlags(
  values: {
    is_active?: boolean
    is_admin?: boolean
  },
): Promise<void> {
  if (!selectedUser.value) {
    return
  }

  const id =
    Number(
      selectedUser.value.user.id,
    )

  await run(
    async () => {
      const result =
        await updateAdminUser(
          id,
          values,
        )

      message.value =
        result.message
        ?? 'Zapisano.'

      selectedUser.value =
        await getAdminUser(
          id,
        )

      await loadUsers()
      await loadDashboard()
    },
  )
}


async function openFlight(
  flight: AdminFlightListItem,
): Promise<void> {
  await run(
    async () => {
      const result =
        await getAdminFlight(
          flight.id,
        )

      selectedFlight.value =
        result.flight
    },
  )
}


async function confirmDeleteFlight(): Promise<void> {
  if (!deletingFlight.value) {
    return
  }

  const id =
    deletingFlight.value.id

  await run(
    async () => {
      const result =
        await deleteAdminFlight(
          id,
        )

      message.value =
        result.message
        ?? 'Lot został usunięty.'

      deletingFlight.value =
        null

      selectedFlight.value =
        null

      await loadFlights()
      await loadDashboard()
    },
  )
}


function userSearchSubmit(): void {
  userPage.value = 1
  void loadUsers()
}


function flightSearchSubmit(): void {
  flightPage.value = 1
  void loadFlights()
}


function previousUserPage(): void {
  if (userPage.value <= 1) {
    return
  }

  userPage.value--
  void loadUsers()
}


function nextUserPage(): void {
  if (
    !users.value
    || userPage.value >=
      users.value.pages
  ) {
    return
  }

  userPage.value++
  void loadUsers()
}


function previousFlightPage(): void {
  if (flightPage.value <= 1) {
    return
  }

  flightPage.value--
  void loadFlights()
}


function nextFlightPage(): void {
  if (
    !flights.value
    || flightPage.value >=
      flights.value.pages
  ) {
    return
  }

  flightPage.value++
  void loadFlights()
}


watch(
  () => props.user?.is_admin,
  (isAdmin) => {
    if (isAdmin) {
      void loadDashboard()
    }
  },
)


onMounted(
  () => {
    if (authorized.value) {
      void loadDashboard()
    }
  },
)
</script>


<template>
  <div class="admin-shell">
    <template v-if="!user">
      <section class="admin-access-panel">
        <div class="admin-access-panel__icon">
          🔐
        </div>

        <h1>Panel administracyjny</h1>

        <p>
          Musisz zalogować się na konto administratora.
        </p>

        <a href="/">
          Wróć do Mapy Lotów
        </a>
      </section>
    </template>

    <template v-else-if="!authorized">
      <section class="admin-access-panel">
        <div class="admin-access-panel__icon">
          ⛔
        </div>

        <h1>Brak dostępu</h1>

        <p>
          To konto nie ma uprawnień administratora.
        </p>

        <a href="/">
          Wróć do Mapy Lotów
        </a>
      </section>
    </template>

    <template v-else>
      <aside class="admin-sidebar">
        <a
          href="/"
          class="admin-brand"
        >
          <span class="admin-brand__mark">
            ✈
          </span>

          <span>
            <strong>MAPA LOTÓW</strong>
            <small>Administracja</small>
          </span>
        </a>

        <nav class="admin-nav">
          <button
            type="button"
            :class="{
              active:
                activeSection === 'dashboard',
            }"
            @click="setSection('dashboard')"
          >
            <span>⌂</span>
            Dashboard
          </button>

          <button
            type="button"
            :class="{
              active:
                activeSection === 'users',
            }"
            @click="setSection('users')"
          >
            <span>♙</span>
            Użytkownicy
          </button>

          <button
            type="button"
            :class="{
              active:
                activeSection === 'flights',
            }"
            @click="setSection('flights')"
          >
            <span>✈</span>
            Loty
          </button>
        </nav>

        <div class="admin-sidebar__future">
          <span>Dalsze etapy</span>
          <div>Lotniska</div>
          <div>Linie lotnicze</div>
          <div>Samoloty</div>
          <div>Log zmian</div>
        </div>

        <div class="admin-admin-card">
          <strong>{{ user.nick }}</strong>
          <span>{{ user.email }}</span>
          <a href="/">
            ← Wróć do mapy
          </a>
        </div>
      </aside>

      <main class="admin-main">
        <header class="admin-topbar">
          <div>
            <div class="admin-topbar__eyebrow">
              Mapa Lotów
            </div>

            <h1>
              <template v-if="activeSection === 'dashboard'">
                Dashboard
              </template>

              <template v-else-if="activeSection === 'users'">
                Użytkownicy
              </template>

              <template v-else>
                Loty
              </template>
            </h1>
          </div>

          <div class="admin-topbar__meta">
            Etap 1
          </div>
        </header>

        <div
          v-if="error"
          class="admin-message admin-message--error"
        >
          {{ error }}
        </div>

        <div
          v-if="message"
          class="admin-message admin-message--success"
        >
          {{ message }}
        </div>

        <div
          v-if="loading"
          class="admin-loading"
        >
          Ładowanie…
        </div>

        <template v-if="activeSection === 'dashboard' && dashboard">
          <section class="admin-kpis">
            <article>
              <span>Użytkownicy</span>
              <strong>
                {{ formatNumber.format(dashboard.users.total) }}
              </strong>
              <small>
                +{{ dashboard.users.new_30 }} / 30 dni
              </small>
            </article>

            <article>
              <span>Aktywne konta</span>
              <strong>
                {{ formatNumber.format(dashboard.users.active) }}
              </strong>
              <small>
                {{ dashboard.users.inactive }} nieaktywne
              </small>
            </article>

            <article>
              <span>Loty</span>
              <strong>
                {{ formatNumber.format(dashboard.flights.total) }}
              </strong>
              <small>
                {{ dashboard.flights.planned }} zaplanowanych
              </small>
            </article>

            <article>
              <span>Dystans</span>
              <strong>
                {{ formatNumber.format(Math.round(dashboard.flights.distance_km)) }}
              </strong>
              <small>km</small>
            </article>

            <article>
              <span>Czas lotów</span>
              <strong>
                {{ formatDuration(dashboard.flights.duration_seconds) }}
              </strong>
              <small>godz:min</small>
            </article>

            <article>
              <span>Administratorzy</span>
              <strong>
                {{ dashboard.users.admins }}
              </strong>
              <small>
                {{ dashboard.users.unverified }} kont niezweryfikowanych
              </small>
            </article>
          </section>

          <section class="admin-dashboard-grid">
            <article class="admin-card">
              <div class="admin-card__head">
                <div>
                  <span>Ostatnie konta</span>
                  <strong>Nowi użytkownicy</strong>
                </div>

                <button
                  type="button"
                  @click="setSection('users')"
                >
                  Zobacz wszystkie
                </button>
              </div>

              <div class="admin-simple-list">
                <button
                  v-for="item in dashboard.recent_users"
                  :key="item.id"
                  type="button"
                  @click="
                    setSection('users')
                      .then(() => openUser(item.id))
                  "
                >
                  <span>
                    <strong>{{ item.nick }}</strong>
                    <small>{{ item.email }}</small>
                  </span>

                  <span class="admin-simple-list__right">
                    {{ formatDate(item.created_at) }}
                  </span>
                </button>

                <div
                  v-if="dashboard.recent_users.length === 0"
                  class="admin-empty"
                >
                  Brak użytkowników.
                </div>
              </div>
            </article>

            <article class="admin-card">
              <div class="admin-card__head">
                <div>
                  <span>Ostatnie loty</span>
                  <strong>Wpisy w bazie</strong>
                </div>

                <button
                  type="button"
                  @click="setSection('flights')"
                >
                  Zobacz wszystkie
                </button>
              </div>

              <div class="admin-simple-list">
                <button
                  v-for="item in dashboard.recent_flights"
                  :key="item.id"
                  type="button"
                  @click="
                    setSection('flights')
                  "
                >
                  <span>
                    <strong>
                      {{ item.departure_iata ?? '—' }}
                      →
                      {{ item.arrival_iata ?? '—' }}
                    </strong>

                    <small>
                      {{ item.user_nick }}
                      ·
                      {{ item.airline_name ?? 'bez linii' }}
                    </small>
                  </span>

                  <span class="admin-simple-list__right">
                    {{ formatDate(item.departure_date) }}
                  </span>
                </button>

                <div
                  v-if="dashboard.recent_flights.length === 0"
                  class="admin-empty"
                >
                  Brak lotów.
                </div>
              </div>
            </article>
          </section>
        </template>

        <template v-else-if="activeSection === 'users'">
          <form
            class="admin-filters"
            @submit.prevent="userSearchSubmit"
          >
            <label class="admin-filter admin-filter--grow">
              <span>Szukaj</span>
              <input
                v-model.trim="userSearch"
                type="search"
                placeholder="Nick, e-mail lub ID"
              >
            </label>

            <label class="admin-filter">
              <span>Status</span>
              <select v-model="userStatus">
                <option value="all">Wszyscy</option>
                <option value="active">Aktywni</option>
                <option value="inactive">Nieaktywni</option>
                <option value="verified">Zweryfikowani</option>
                <option value="unverified">Niezweryfikowani</option>
                <option value="admin">Administratorzy</option>
              </select>
            </label>

            <button
              type="submit"
              class="admin-primary"
            >
              Filtruj
            </button>
          </form>

          <section class="admin-table-card">
            <div class="admin-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Użytkownik</th>
                    <th>Status</th>
                    <th>Loty</th>
                    <th>Dystans</th>
                    <th>Rejestracja</th>
                    <th>Ostatnie logowanie</th>
                    <th></th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="item in users?.users ?? []"
                    :key="item.id"
                  >
                    <td class="admin-mono">
                      {{ item.id }}
                    </td>

                    <td>
                      <strong>{{ item.nick }}</strong>
                      <small>{{ item.email }}</small>
                    </td>

                    <td>
                      <div class="admin-status-stack">
                        <span
                          class="admin-badge"
                          :class="{
                            'admin-badge--green':
                              Boolean(item.is_active),
                            'admin-badge--red':
                              !Boolean(item.is_active),
                          }"
                        >
                          {{ Boolean(item.is_active) ? 'Aktywne' : 'Nieaktywne' }}
                        </span>

                        <span
                          v-if="Boolean(item.is_admin)"
                          class="admin-badge admin-badge--navy"
                        >
                          Admin
                        </span>
                      </div>
                    </td>

                    <td>
                      {{ formatNumber.format(Number(item.flights_count)) }}
                    </td>

                    <td>
                      {{ formatNumber.format(Math.round(Number(item.distance_km))) }} km
                    </td>

                    <td>
                      {{ formatDate(item.created_at) }}
                    </td>

                    <td>
                      {{ formatDateTime(item.last_login_at) }}
                    </td>

                    <td class="admin-table__actions">
                      <button
                        type="button"
                        @click="openUser(item)"
                      >
                        Szczegóły
                      </button>
                    </td>
                  </tr>

                  <tr
                    v-if="(users?.users.length ?? 0) === 0"
                  >
                    <td
                      colspan="8"
                      class="admin-empty"
                    >
                      Brak użytkowników spełniających kryteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <footer class="admin-pagination">
              <span>
                {{ users?.total ?? 0 }} użytkowników
              </span>

              <div>
                <button
                  type="button"
                  :disabled="userPage <= 1"
                  @click="previousUserPage"
                >
                  ←
                </button>

                <strong>
                  {{ userPage }} / {{ users?.pages ?? 1 }}
                </strong>

                <button
                  type="button"
                  :disabled="userPage >= (users?.pages ?? 1)"
                  @click="nextUserPage"
                >
                  →
                </button>
              </div>
            </footer>
          </section>
        </template>

        <template v-else-if="activeSection === 'flights'">
          <form
            class="admin-filters admin-filters--flights"
            @submit.prevent="flightSearchSubmit"
          >
            <label class="admin-filter admin-filter--grow">
              <span>Szukaj</span>
              <input
                v-model.trim="flightSearch"
                type="search"
                placeholder="Użytkownik, lotnisko, linia, numer lotu"
              >
            </label>

            <label class="admin-filter">
              <span>Zakres</span>
              <select v-model="flightScope">
                <option value="all">Wszystkie</option>
                <option value="completed">Odbyte</option>
                <option value="planned">Zaplanowane</option>
              </select>
            </label>

            <label class="admin-filter admin-filter--small">
              <span>User ID</span>
              <input
                v-model.trim="flightUserId"
                inputmode="numeric"
                placeholder="np. 75"
              >
            </label>

            <label class="admin-filter">
              <span>Od</span>
              <input
                v-model="flightDateFrom"
                type="date"
              >
            </label>

            <label class="admin-filter">
              <span>Do</span>
              <input
                v-model="flightDateTo"
                type="date"
              >
            </label>

            <button
              type="submit"
              class="admin-primary"
            >
              Filtruj
            </button>
          </form>

          <section class="admin-table-card">
            <div class="admin-table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Trasa</th>
                    <th>Użytkownik</th>
                    <th>Linia / lot</th>
                    <th>Samolot</th>
                    <th>Dystans</th>
                    <th></th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="item in flights?.flights ?? []"
                    :key="item.id"
                  >
                    <td class="admin-mono">
                      {{ item.id }}
                    </td>

                    <td>
                      {{ formatDate(item.departure_date) }}
                      <small>{{ item.departure_time ?? '—' }}</small>
                    </td>

                    <td>
                      <strong>
                        {{ item.departure_iata ?? '—' }}
                        →
                        {{ item.arrival_iata ?? '—' }}
                      </strong>
                      <small>
                        {{ item.departure_city ?? item.departure_airport }}
                        →
                        {{ item.arrival_city ?? item.arrival_airport }}
                      </small>
                    </td>

                    <td>
                      <strong>{{ item.user_nick }}</strong>
                      <small>ID {{ item.user_id }}</small>
                    </td>

                    <td>
                      {{ item.airline_name ?? '—' }}
                      <small>{{ item.flight_number ?? '—' }}</small>
                    </td>

                    <td>
                      {{ item.aircraft_name ?? '—' }}
                    </td>

                    <td>
                      {{ formatNumber.format(Math.round(Number(item.distance_km ?? 0))) }} km
                    </td>

                    <td class="admin-table__actions">
                      <button
                        type="button"
                        @click="openFlight(item)"
                      >
                        Szczegóły
                      </button>

                      <button
                        type="button"
                        class="admin-danger-link"
                        @click="deletingFlight = item"
                      >
                        Usuń
                      </button>
                    </td>
                  </tr>

                  <tr
                    v-if="(flights?.flights.length ?? 0) === 0"
                  >
                    <td
                      colspan="8"
                      class="admin-empty"
                    >
                      Brak lotów spełniających kryteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <footer class="admin-pagination">
              <span>
                {{ flights?.total ?? 0 }} lotów
              </span>

              <div>
                <button
                  type="button"
                  :disabled="flightPage <= 1"
                  @click="previousFlightPage"
                >
                  ←
                </button>

                <strong>
                  {{ flightPage }} / {{ flights?.pages ?? 1 }}
                </strong>

                <button
                  type="button"
                  :disabled="flightPage >= (flights?.pages ?? 1)"
                  @click="nextFlightPage"
                >
                  →
                </button>
              </div>
            </footer>
          </section>
        </template>
      </main>

      <div
        v-if="selectedUser"
        class="admin-drawer-overlay"
        @click.self="selectedUser = null"
      >
        <aside class="admin-drawer">
          <button
            type="button"
            class="admin-drawer__close"
            @click="selectedUser = null"
          >
            ×
          </button>

          <div class="admin-drawer__eyebrow">
            Użytkownik #{{ selectedUser.user.id }}
          </div>

          <h2>
            {{ selectedUser.user.nick }}
          </h2>

          <div class="admin-drawer__email">
            {{ selectedUser.user.email }}
          </div>

          <section class="admin-detail-grid">
            <div>
              <span>Loty</span>
              <strong>
                {{ selectedUser.user.flights_count }}
              </strong>
            </div>

            <div>
              <span>Zaplanowane</span>
              <strong>
                {{ selectedUser.user.planned_count ?? 0 }}
              </strong>
            </div>

            <div>
              <span>Dystans</span>
              <strong>
                {{ formatNumber.format(Math.round(Number(selectedUser.user.distance_km))) }} km
              </strong>
            </div>

            <div>
              <span>Czas</span>
              <strong>
                {{ formatDuration(selectedUser.user.duration_seconds) }}
              </strong>
            </div>
          </section>

          <dl class="admin-definition-list">
            <div>
              <dt>Rejestracja</dt>
              <dd>{{ formatDateTime(selectedUser.user.created_at) }}</dd>
            </div>

            <div>
              <dt>Ostatnie logowanie</dt>
              <dd>{{ formatDateTime(selectedUser.user.last_login_at) }}</dd>
            </div>

            <div>
              <dt>E-mail</dt>
              <dd>
                {{ selectedUser.user.email_verified_at ? 'zweryfikowany' : 'niezweryfikowany' }}
              </dd>
            </div>

            <div>
              <dt>Prywatność</dt>
              <dd>{{ selectedUser.user.privacy_mode }}</dd>
            </div>
          </dl>

          <section class="admin-switches">
            <label>
              <span>
                <strong>Konto aktywne</strong>
                <small>Nieaktywne konto traci dostęp do serwisu.</small>
              </span>

              <input
                type="checkbox"
                :checked="Boolean(selectedUser.user.is_active)"
                :disabled="selectedUser.is_current_admin"
                @change="
                  saveUserFlags({
                    is_active:
                      ($event.target as HTMLInputElement).checked,
                  })
                "
              >
            </label>

            <label>
              <span>
                <strong>Administrator</strong>
                <small>Pełny dostęp do tego panelu.</small>
              </span>

              <input
                type="checkbox"
                :checked="Boolean(selectedUser.user.is_admin)"
                :disabled="selectedUser.is_current_admin"
                @change="
                  saveUserFlags({
                    is_admin:
                      ($event.target as HTMLInputElement).checked,
                  })
                "
              >
            </label>
          </section>

          <section class="admin-recent-box">
            <h3>Ostatnie loty</h3>

            <button
              v-for="item in selectedUser.recent_flights"
              :key="item.id"
              type="button"
              @click="
                selectedUser = null;
                activeSection = 'flights';
                flightUserId = String(selectedUser?.user.id ?? '');
              "
            >
              <span>
                {{ item.departure_iata ?? '—' }}
                →
                {{ item.arrival_iata ?? '—' }}
              </span>

              <small>
                {{ formatDate(item.departure_date) }}
              </small>
            </button>

            <div
              v-if="selectedUser.recent_flights.length === 0"
              class="admin-empty"
            >
              Użytkownik nie ma lotów.
            </div>
          </section>
        </aside>
      </div>

      <div
        v-if="selectedFlight"
        class="admin-drawer-overlay"
        @click.self="selectedFlight = null"
      >
        <aside class="admin-drawer">
          <button
            type="button"
            class="admin-drawer__close"
            @click="selectedFlight = null"
          >
            ×
          </button>

          <div class="admin-drawer__eyebrow">
            Lot #{{ selectedFlight.id }}
          </div>

          <h2>
            {{ selectedFlight.departure_iata ?? '—' }}
            →
            {{ selectedFlight.arrival_iata ?? '—' }}
          </h2>

          <div class="admin-drawer__email">
            {{ selectedFlight.user_nick }}
            · ID {{ selectedFlight.user_id }}
          </div>

          <dl class="admin-definition-list admin-definition-list--flight">
            <div>
              <dt>Data</dt>
              <dd>
                {{ formatDate(selectedFlight.departure_date) }}
                {{ selectedFlight.departure_time ?? '' }}
              </dd>
            </div>

            <div>
              <dt>Trasa</dt>
              <dd>
                {{ selectedFlight.departure_airport }}
                →
                {{ selectedFlight.arrival_airport }}
              </dd>
            </div>

            <div>
              <dt>Linia</dt>
              <dd>
                {{ selectedFlight.airline_name ?? '—' }}
                {{ selectedFlight.flight_number ?? '' }}
              </dd>
            </div>

            <div>
              <dt>Samolot</dt>
              <dd>{{ selectedFlight.aircraft_name ?? '—' }}</dd>
            </div>

            <div>
              <dt>Dystans</dt>
              <dd>
                {{ formatNumber.format(Math.round(Number(selectedFlight.distance_km ?? 0))) }} km
              </dd>
            </div>

            <div>
              <dt>Czas</dt>
              <dd>{{ formatDuration(selectedFlight.duration_seconds) }}</dd>
            </div>

            <div>
              <dt>Uwagi</dt>
              <dd>{{ selectedFlight.notes ?? '—' }}</dd>
            </div>
          </dl>
        </aside>
      </div>

      <div
        v-if="deletingFlight"
        class="admin-confirm-overlay"
        @click.self="deletingFlight = null"
      >
        <section class="admin-confirm">
          <div class="admin-confirm__icon">
            !
          </div>

          <h2>Usunąć lot?</h2>

          <p>
            Lot
            <strong>
              {{ deletingFlight.departure_iata ?? '—' }}
              →
              {{ deletingFlight.arrival_iata ?? '—' }}
            </strong>
            użytkownika
            <strong>{{ deletingFlight.user_nick }}</strong>
            zostanie trwale usunięty.
          </p>

          <div>
            <button
              type="button"
              @click="deletingFlight = null"
            >
              Anuluj
            </button>

            <button
              type="button"
              class="admin-danger"
              @click="confirmDeleteFlight"
            >
              Usuń lot
            </button>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>


<style scoped>
.admin-shell {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  grid-template-columns: 238px minmax(0, 1fr);
  overflow: hidden;
  background: #eef2f5;
  color: #263547;
  font-family:
    Inter,
    ui-sans-serif,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
}

.admin-sidebar {
  display: flex;
  min-height: 0;
  flex-direction: column;
  padding: 18px 14px;
  border-right: 1px solid #d9e0e6;
  background: #ffffff;
}

.admin-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 2px 4px 24px;
  color: #0b2d5c;
  text-decoration: none;
}

.admin-brand__mark {
  display: flex;
  width: 36px;
  height: 36px;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  background: #f3ba31;
  color: #0b2d5c;
  font-size: 19px;
  font-weight: 900;
}

.admin-brand > span:last-child {
  display: grid;
  gap: 2px;
}

.admin-brand strong {
  font-size: 13px;
  letter-spacing: 0.04em;
}

.admin-brand small {
  color: #84909d;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}

.admin-nav {
  display: grid;
  gap: 5px;
}

.admin-nav button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 42px;
  padding: 0 12px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #566475;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  text-align: left;
}

.admin-nav button span {
  width: 20px;
  color: #68809a;
  font-size: 16px;
  text-align: center;
}

.admin-nav button:hover {
  background: #f3f6f8;
}

.admin-nav button.active {
  background: #eaf0f6;
  color: #0b2d5c;
}

.admin-nav button.active span {
  color: #0b2d5c;
}

.admin-sidebar__future {
  display: grid;
  gap: 8px;
  margin-top: 26px;
  padding: 14px 12px;
  border-top: 1px solid #edf0f3;
  color: #a0a8b1;
  font-size: 10px;
}

.admin-sidebar__future > span {
  margin-bottom: 2px;
  color: #8d98a4;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.admin-admin-card {
  display: grid;
  gap: 3px;
  margin-top: auto;
  padding: 13px 12px;
  border: 1px solid #e1e6eb;
  border-radius: 10px;
  background: #f8fafb;
}

.admin-admin-card strong {
  color: #0b2d5c;
  font-size: 11px;
}

.admin-admin-card span {
  overflow: hidden;
  color: #84909c;
  font-size: 9px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.admin-admin-card a {
  margin-top: 7px;
  color: #597693;
  font-size: 9.5px;
  font-weight: 700;
  text-decoration: none;
}

.admin-main {
  min-width: 0;
  overflow-y: auto;
  padding: 26px 30px 42px;
}

.admin-topbar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
}

.admin-topbar__eyebrow {
  margin-bottom: 3px;
  color: #9aa4ae;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.admin-topbar h1 {
  margin: 0;
  color: #0b2d5c;
  font-size: 27px;
  line-height: 1;
}

.admin-topbar__meta {
  padding: 6px 9px;
  border: 1px solid #d5e0ea;
  border-radius: 999px;
  background: #f8fafc;
  color: #66798d;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
}

.admin-kpis {
  display: grid;
  grid-template-columns:
    repeat(6, minmax(0, 1fr));
  gap: 10px;
}

.admin-kpis article {
  display: grid;
  gap: 4px;
  min-width: 0;
  padding: 15px;
  border: 1px solid #dde4ea;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
}

.admin-kpis span {
  color: #7a8795;
  font-size: 9px;
  font-weight: 700;
}

.admin-kpis strong {
  overflow: hidden;
  color: #0b2d5c;
  font-size: 21px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.admin-kpis small {
  color: #98a1ab;
  font-size: 8.5px;
}

.admin-dashboard-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-top: 14px;
}

.admin-card,
.admin-table-card {
  overflow: hidden;
  border: 1px solid #dce3e9;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
}

.admin-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 16px;
  border-bottom: 1px solid #edf0f3;
}

.admin-card__head > div {
  display: grid;
  gap: 2px;
}

.admin-card__head span {
  color: #9aa3ad;
  font-size: 8px;
  font-weight: 800;
  text-transform: uppercase;
}

.admin-card__head strong {
  color: #0b2d5c;
  font-size: 13px;
}

.admin-card__head button {
  padding: 6px 8px;
  border: 1px solid #dde4eb;
  border-radius: 7px;
  background: #f8fafb;
  color: #58728c;
  cursor: pointer;
  font-size: 8.5px;
  font-weight: 700;
}

.admin-simple-list {
  display: grid;
}

.admin-simple-list button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-width: 0;
  padding: 11px 16px;
  border: 0;
  border-bottom: 1px solid #f0f2f4;
  background: #fff;
  cursor: pointer;
  text-align: left;
}

.admin-simple-list button:last-child {
  border-bottom: 0;
}

.admin-simple-list button:hover {
  background: #f9fbfc;
}

.admin-simple-list button > span:first-child {
  display: grid;
  min-width: 0;
  gap: 2px;
}

.admin-simple-list strong {
  color: #24384d;
  font-size: 10.5px;
}

.admin-simple-list small {
  overflow: hidden;
  color: #8994a0;
  font-size: 8.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.admin-simple-list__right {
  flex: 0 0 auto;
  color: #8793a0;
  font-size: 9px;
}

.admin-filters {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  margin-bottom: 12px;
  padding: 12px;
  border: 1px solid #dce4ea;
  border-radius: 11px;
  background: #fff;
}

.admin-filter {
  display: grid;
  gap: 4px;
  min-width: 150px;
}

.admin-filter--grow {
  flex: 1 1 300px;
}

.admin-filter--small {
  min-width: 92px;
  max-width: 100px;
}

.admin-filter span {
  color: #7e8995;
  font-size: 8px;
  font-weight: 800;
  text-transform: uppercase;
}

.admin-filter input,
.admin-filter select {
  width: 100%;
  min-height: 36px;
  box-sizing: border-box;
  padding: 0 9px;
  border: 1px solid #d7dfe7;
  border-radius: 7px;
  background: #fff;
  color: #26384b;
  font: inherit;
  font-size: 10px;
  outline: none;
}

.admin-filter input:focus,
.admin-filter select:focus {
  border-color: #9fb3c6;
  box-shadow: 0 0 0 3px rgba(11, 45, 92, 0.05);
}

.admin-primary {
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid #0b2d5c;
  border-radius: 7px;
  background: #0b2d5c;
  color: #fff;
  cursor: pointer;
  font-size: 9.5px;
  font-weight: 800;
}

.admin-table-wrap {
  overflow-x: auto;
}

.admin-table-card table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table-card th,
.admin-table-card td {
  padding: 10px 12px;
  border-bottom: 1px solid #eef1f3;
  color: #4f5e6e;
  font-size: 9.5px;
  text-align: left;
  vertical-align: middle;
}

.admin-table-card th {
  background: #f8fafb;
  color: #7b8794;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
}

.admin-table-card td strong,
.admin-table-card td small {
  display: block;
}

.admin-table-card td strong {
  color: #24384d;
  font-size: 10px;
}

.admin-table-card td small {
  margin-top: 2px;
  color: #8b96a1;
  font-size: 8.5px;
}

.admin-mono {
  color: #788491 !important;
  font-family:
    ui-monospace,
    SFMono-Regular,
    Consolas,
    monospace;
}

.admin-table__actions {
  white-space: nowrap;
}

.admin-table__actions button {
  padding: 5px 7px;
  border: 1px solid #d9e1e8;
  border-radius: 6px;
  background: #fff;
  color: #526d88;
  cursor: pointer;
  font-size: 8.5px;
  font-weight: 700;
}

.admin-table__actions button + button {
  margin-left: 4px;
}

.admin-table__actions .admin-danger-link {
  border-color: #eddddd;
  color: #a14a4a;
}

.admin-status-stack {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.admin-badge {
  display: inline-flex;
  padding: 4px 6px;
  border-radius: 999px;
  background: #edf1f4;
  color: #667482;
  font-size: 7.5px;
  font-weight: 800;
}

.admin-badge--green {
  background: #e9f5ed;
  color: #39704a;
}

.admin-badge--red {
  background: #f9ecec;
  color: #934b4b;
}

.admin-badge--navy {
  background: #e9eff6;
  color: #2f557d;
}

.admin-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 11px 13px;
  background: #fafbfc;
  color: #83909c;
  font-size: 9px;
}

.admin-pagination > div {
  display: flex;
  align-items: center;
  gap: 7px;
}

.admin-pagination button {
  width: 30px;
  height: 28px;
  border: 1px solid #dce3e9;
  border-radius: 6px;
  background: #fff;
  color: #516b84;
  cursor: pointer;
}

.admin-pagination button:disabled {
  cursor: default;
  opacity: 0.35;
}

.admin-pagination strong {
  color: #4f6174;
  font-size: 9px;
}

.admin-drawer-overlay,
.admin-confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(15, 23, 42, 0.25);
  backdrop-filter: blur(2px);
}

.admin-drawer {
  position: absolute;
  top: 0;
  right: 0;
  width: min(440px, calc(100vw - 30px));
  height: 100%;
  box-sizing: border-box;
  overflow-y: auto;
  padding: 25px;
  border-left: 1px solid #dce3e9;
  background: #fff;
  box-shadow: -16px 0 42px rgba(15, 23, 42, 0.15);
}

.admin-drawer__close {
  position: absolute;
  top: 14px;
  right: 14px;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: #f0f3f5;
  color: #566473;
  cursor: pointer;
  font-size: 21px;
}

.admin-drawer__eyebrow {
  margin-bottom: 4px;
  color: #929daa;
  font-size: 8px;
  font-weight: 800;
  text-transform: uppercase;
}

.admin-drawer h2 {
  margin: 0;
  padding-right: 40px;
  color: #0b2d5c;
  font-size: 22px;
}

.admin-drawer__email {
  margin-top: 4px;
  color: #7e8996;
  font-size: 10px;
}

.admin-detail-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-top: 20px;
}

.admin-detail-grid div {
  display: grid;
  gap: 3px;
  padding: 11px;
  border: 1px solid #e1e6eb;
  border-radius: 8px;
  background: #f8fafb;
}

.admin-detail-grid span {
  color: #8994a0;
  font-size: 8px;
}

.admin-detail-grid strong {
  color: #0b2d5c;
  font-size: 13px;
}

.admin-definition-list {
  display: grid;
  gap: 0;
  margin: 18px 0 0;
}

.admin-definition-list div {
  display: grid;
  grid-template-columns: 125px 1fr;
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid #eef1f3;
}

.admin-definition-list dt {
  color: #8994a0;
  font-size: 9px;
}

.admin-definition-list dd {
  margin: 0;
  color: #415365;
  font-size: 9.5px;
  font-weight: 650;
}

.admin-switches {
  display: grid;
  gap: 8px;
  margin-top: 20px;
}

.admin-switches label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px;
  border: 1px solid #dfe5ea;
  border-radius: 9px;
  background: #fafbfc;
}

.admin-switches label > span {
  display: grid;
  gap: 3px;
}

.admin-switches strong {
  color: #31465b;
  font-size: 10px;
}

.admin-switches small {
  color: #8a95a1;
  font-size: 8.5px;
}

.admin-switches input {
  width: 17px;
  height: 17px;
}

.admin-recent-box {
  display: grid;
  gap: 0;
  margin-top: 20px;
}

.admin-recent-box h3 {
  margin: 0 0 8px;
  color: #0b2d5c;
  font-size: 12px;
}

.admin-recent-box button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 0;
  border: 0;
  border-bottom: 1px solid #eef1f3;
  background: transparent;
  color: #40566b;
  cursor: pointer;
  font-size: 9.5px;
  text-align: left;
}

.admin-recent-box small {
  color: #8c97a2;
}

.admin-confirm-overlay {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.admin-confirm {
  width: min(390px, calc(100vw - 40px));
  box-sizing: border-box;
  padding: 24px;
  border: 1px solid #dfe4e9;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
  text-align: center;
}

.admin-confirm__icon {
  display: flex;
  width: 42px;
  height: 42px;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  border-radius: 50%;
  background: #fff0f0;
  color: #a54646;
  font-size: 18px;
  font-weight: 900;
}

.admin-confirm h2 {
  margin: 0;
  color: #0b2d5c;
  font-size: 19px;
}

.admin-confirm p {
  margin: 10px 0 19px;
  color: #697786;
  font-size: 10.5px;
  line-height: 1.55;
}

.admin-confirm > div:last-child {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.admin-confirm button {
  min-height: 39px;
  border: 1px solid #d8dfe6;
  border-radius: 8px;
  background: #f8fafb;
  color: #536476;
  cursor: pointer;
  font-size: 10px;
  font-weight: 800;
}

.admin-confirm .admin-danger {
  border-color: #9d4747;
  background: #a64d4d;
  color: #fff;
}

.admin-message {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 9.5px;
}

.admin-message--error {
  border: 1px solid #efd0d0;
  background: #fff5f5;
  color: #953f3f;
}

.admin-message--success {
  border: 1px solid #cee4d3;
  background: #f2faf4;
  color: #3a7048;
}

.admin-loading {
  position: fixed;
  top: 18px;
  left: 50%;
  z-index: 1200;
  transform: translateX(-50%);
  padding: 7px 12px;
  border: 1px solid #dce2e7;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.96);
  color: #627184;
  box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
  font-size: 9px;
  font-weight: 700;
}

.admin-empty {
  padding: 20px !important;
  color: #929da8 !important;
  font-size: 9.5px !important;
  text-align: center !important;
}

.admin-access-panel {
  position: fixed;
  top: 50%;
  left: 50%;
  width: min(390px, calc(100vw - 36px));
  box-sizing: border-box;
  padding: 28px;
  transform: translate(-50%, -50%);
  border: 1px solid #dce3e9;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.13);
  text-align: center;
}

.admin-access-panel__icon {
  margin-bottom: 10px;
  font-size: 28px;
}

.admin-access-panel h1 {
  margin: 0;
  color: #0b2d5c;
  font-size: 22px;
}

.admin-access-panel p {
  margin: 10px 0 18px;
  color: #6d7a88;
  font-size: 11px;
}

.admin-access-panel a {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border-radius: 8px;
  background: #0b2d5c;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  text-decoration: none;
}

@media (max-width: 1180px) {
  .admin-kpis {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }

  .admin-filters--flights {
    flex-wrap: wrap;
  }
}

@media (max-width: 820px) {
  .admin-shell {
    grid-template-columns: 72px minmax(0, 1fr);
  }

  .admin-sidebar {
    padding: 12px 8px;
  }

  .admin-brand > span:last-child,
  .admin-nav button:not(.active) {
    font-size: 0;
  }

  .admin-brand {
    justify-content: center;
    margin-left: 0;
    margin-right: 0;
  }

  .admin-nav button {
    justify-content: center;
    padding: 0;
  }

  .admin-nav button.active {
    font-size: 0;
  }

  .admin-nav button span {
    font-size: 17px;
  }

  .admin-sidebar__future,
  .admin-admin-card {
    display: none;
  }

  .admin-main {
    padding: 20px 16px 34px;
  }

  .admin-dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 620px) {
  .admin-kpis {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .admin-filters {
    align-items: stretch;
    flex-direction: column;
  }

  .admin-filter,
  .admin-filter--small {
    min-width: 0;
    max-width: none;
  }
}
</style>
