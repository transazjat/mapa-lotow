<script setup lang="ts">
import {
  computed,
  ref,
  watch,
} from 'vue'

import {
  activateAccount,
  changeAccountPassword,
  confirmEmailChange,
  downloadAccountExport,
  loginAccount,
  logoutAccount,
  regenerateShareLink,
  registerAccount,
  requestEmailChange,
  requestPasswordReset,
  resendActivation,
  resetPassword,
  updateAccountPrivacy,
  updateAccountProfile,
} from '../services/api'

import type {
  AccountUser,
  PrivacyMode,
} from '../types/account'


type AccountMode =
  | 'login'
  | 'register'
  | 'forgot'
  | 'reset'
  | 'activate'
  | 'account'
  | 'profile'
  | 'privacy'
  | 'password'
  | 'email'
  | 'email-confirm'
  | 'export'


const props = defineProps<{
  user: AccountUser | null
  mode: AccountMode
  token?: string | null
}>()


const emit = defineEmits<{
  close: []
  mode: [mode: AccountMode]
  authenticated: [user: AccountUser]
  userUpdated: [user: AccountUser]
  loggedOut: []
}>()


const loading = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)

const email = ref('')
const nick = ref('')
const password = ref('')
const passwordRepeat = ref('')
const currentPassword = ref('')
const newEmail = ref('')
const remember = ref(true)
const avatarFailed = ref(false)
const fieldError = ref<string | null>(null)
const loginFieldsInvalid = ref(false)
const registrationComplete = ref(false)
const activationNeedsNewLink = ref(false)
const emailChangeComplete = ref(false)

const nickStyle = ref<
  'travel' | 'aviation' | 'neutral'
>('travel')


const currentMode =
  computed(
    () => props.mode,
  )


const centered =
  computed(
    () =>
      [
        'login',
        'register',
        'reset',
        'activate',
        'email-confirm',
      ].includes(
        currentMode.value,
      ) ||
      (
        currentMode.value === 'forgot' &&
        !props.user
      ),
  )


const initial =
  computed(
    () => {
      const value =
        props.user?.nick?.trim() ?? ''

      return value
        ? value.charAt(0).toUpperCase()
        : '?'
    },
  )


const passwordLength =
  computed(
    () =>
      Array.from(
        password.value,
      ).length,
  )


const passwordDigits =
  computed(
    () =>
      (
        password.value.match(
          /\d/g,
        ) ?? []
      ).length,
  )


const passwordHasEnoughDigits =
  computed(
    () =>
      passwordDigits.value >= 2,
  )


const passwordHasSpecial =
  computed(
    () =>
      /[^\p{L}\p{N}\s]/u.test(
        password.value,
      ),
  )


const passwordHasMinLength =
  computed(
    () =>
      passwordLength.value >= 10,
  )


const passwordRequirementsMet =
  computed(
    () =>
      passwordHasMinLength.value &&
      passwordHasEnoughDigits.value &&
      passwordHasSpecial.value,
  )


watch(
  () => props.user,
  (user) => {
    nick.value =
      user?.nick ?? ''

    email.value =
      user?.email ?? ''

    avatarFailed.value =
      false
  },
  {
    immediate: true,
  },
)


watch(
  () => props.mode,
  () => {
    error.value = null
    message.value = null
    fieldError.value = null
    loginFieldsInvalid.value = false
    registrationComplete.value = false
    activationNeedsNewLink.value = false
    emailChangeComplete.value = false
    password.value = ''
    passwordRepeat.value = ''
    currentPassword.value = ''
    newEmail.value = ''
  },
)


async function perform(
  action: () => Promise<void>,
): Promise<void> {
  loading.value = true
  error.value = null
  message.value = null
  fieldError.value = null

  try {
    await action()
  } catch (err) {
    const apiError =
      err as Error & {
        field?: string
        existing_account?: boolean
      }

    error.value =
      err instanceof Error
        ? err.message
        : 'Wystąpił błąd.'

    fieldError.value =
      apiError?.field ?? null

    if (
      currentMode.value === 'login'
    ) {
      loginFieldsInvalid.value = true
    }

    if (
      currentMode.value === 'activate'
    ) {
      activationNeedsNewLink.value = true
    }
  } finally {
    loading.value = false
  }
}


async function submitLogin(): Promise<void> {
  loginFieldsInvalid.value = false

  await perform(
    async () => {
      const response =
        await loginAccount(
          email.value,
          password.value,
          remember.value,
        )

      if (!response.user) {
        throw new Error(
          response.message ??
          'Nie udało się zalogować.',
        )
      }

      emit(
        'authenticated',
        response.user,
      )
    },
  )
}


async function submitRegister(): Promise<void> {
  if (!passwordRequirementsMet.value) {
    error.value =
      'Hasło nie spełnia wszystkich wymaganych warunków.'
    fieldError.value =
      'password'
    return
  }

  await perform(
    async () => {
      const response =
        await registerAccount(
          email.value,
          nick.value,
          password.value,
          passwordRepeat.value,
        )

      message.value =
        response.message ??
        'Konto zostało utworzone. Sprawdź e-mail i aktywuj konto przed pierwszym logowaniem.'

      registrationComplete.value =
        true

      password.value = ''
      passwordRepeat.value = ''
    },
  )
}


async function submitForgot(): Promise<void> {
  await perform(
    async () => {
      const response =
        await requestPasswordReset(
          email.value,
        )

      message.value =
        response.message ??
        'Sprawdź pocztę.'
    },
  )
}


async function submitReset(): Promise<void> {
  if (!props.token) {
    error.value =
      'Brak tokenu resetu hasła.'
    return
  }

  if (!passwordRequirementsMet.value) {
    error.value =
      'Hasło nie spełnia wszystkich wymaganych warunków.'
    fieldError.value =
      'password'
    return
  }

  await perform(
    async () => {
      const response =
        await resetPassword(
          props.token ?? '',
          password.value,
          passwordRepeat.value,
        )

      message.value =
        response.message ??
        'Hasło zostało zmienione.'

      password.value = ''
      passwordRepeat.value = ''
    },
  )
}


async function submitActivation(): Promise<void> {
  if (!props.token) {
    error.value =
      'Brak tokenu aktywacyjnego.'
    return
  }

  await perform(
    async () => {
      const response =
        await activateAccount(
          props.token ?? '',
        )

      if (response.user) {
        emit(
          'authenticated',
          response.user,
        )

        message.value =
          'Zostałeś poprawnie zalogowany do systemu.'

        window.history.replaceState(
          {},
          document.title,
          window.location.pathname,
        )

        await new Promise<void>(
          (resolve) => {
            window.setTimeout(
              resolve,
              2000,
            )
          },
        )

        emit('close')
      } else {
        message.value =
          response.message ??
          'Konto zostało aktywowane.'
      }
    },
  )
}


async function resendActivationLink(): Promise<void> {
  await perform(
    async () => {
      const response =
        await resendActivation(
          email.value,
        )

      activationNeedsNewLink.value =
        false

      message.value =
        response.message ??
        'Wysłaliśmy nowy link aktywacyjny.'
    },
  )
}


function generateNick(): void {
  const dictionaries = {
    travel: {
      adjectives: [
        'Bursztynowy',
        'Daleki',
        'Dziki',
        'Górski',
        'Koralowy',
        'Kresowy',
        'Leśny',
        'Morski',
        'Nadmorski',
        'Nieznany',
        'Północny',
        'Południowy',
        'Srebrny',
        'Słoneczny',
        'Tajemniczy',
        'Wędrowny',
        'Wschodni',
        'Zachodni',
        'Zielony',
        'Złoty',
        'Cichy',
        'Wolny',
        'Dalekosiężny',
        'Bezkresny',
        'Podróżny',
        'Szafirowy',
        'Kamienny',
        'Pustynny',
        'Polarny',
        'Tropikalny',
      ],
      nouns: [
        'Horyzont',
        'Kompas',
        'Wędrowiec',
        'Podróżnik',
        'Globtroter',
        'Odkrywca',
        'Nomada',
        'Szlak',
        'Trakt',
        'Gościniec',
        'Przełęcz',
        'Szczyt',
        'Archipelag',
        'Kontynent',
        'Karawana',
        'Latarnik',
        'Obieżyświat',
        'Włóczykij',
        'Przewodnik',
        'Kartograf',
        'Wędrownik',
        'Azyl',
        'Przystań',
        'Biegun',
        'Kierunek',
        'Drogowskaz',
        'Pionier',
        'Ekspedycja',
        'Wyprawa',
        'Azymut',
      ],
    },

    aviation: {
      adjectives: [
        'Podniebny',
        'Lotniczy',
        'Skrzydlaty',
        'Naddźwiękowy',
        'Błękitny',
        'Srebrny',
        'Wysoki',
        'Przelotowy',
        'Transkontynentalny',
        'Międzykontynentalny',
        'Dalekodystansowy',
        'Chmurowy',
        'Jetowy',
        'Aerodynamiczny',
        'Nocny',
        'Poranny',
        'Wieczorny',
        'Północny',
        'Atlantycki',
        'Pacyficzny',
        'Polarny',
        'Wolny',
        'Śmiały',
        'Spokojny',
        'Bezkresny',
        'Biały',
        'Granatowy',
        'Kobaltowy',
        'Wędrowny',
        'Powietrzny',
      ],
      nouns: [
        'Lotnik',
        'Pilot',
        'Skrzydło',
        'Horyzont',
        'Radar',
        'Kokpit',
        'Ster',
        'Pułap',
        'Azymut',
        'Kurs',
        'Przelot',
        'Rejs',
        'Terminal',
        'Hangar',
        'Pas',
        'Szybowiec',
        'Albatros',
        'Sokół',
        'Orzeł',
        'Wektor',
        'Nimbus',
        'Jetstream',
        'Awionik',
        'Kapitan',
        'Nawigator',
        'Aeronauta',
        'Glider',
        'Skyline',
        'Voyager',
        'Airwalker',
      ],
    },

    neutral: {
      adjectives: [
        'Błękitny',
        'Bursztynowy',
        'Cichy',
        'Czujny',
        'Daleki',
        'Dobry',
        'Granatowy',
        'Jasny',
        'Kobaltowy',
        'Łagodny',
        'Niebieski',
        'Niezależny',
        'Pogodny',
        'Srebrny',
        'Spokojny',
        'Szafirowy',
        'Śmiały',
        'Wolny',
        'Zielony',
        'Złoty',
        'Życzliwy',
        'Radosny',
        'Uważny',
        'Przyjazny',
        'Wytrwały',
        'Ciekawy',
        'Niespokojny',
        'Pewny',
        'Odważny',
        'Zwinny',
      ],
      nouns: [
        'Atlas',
        'Bursztyn',
        'Echo',
        'Feniks',
        'Horyzont',
        'Iskra',
        'Kompas',
        'Meteor',
        'Nimbus',
        'Orion',
        'Pionier',
        'Promień',
        'Rytm',
        'Sokół',
        'Sygnał',
        'Szafir',
        'Wektor',
        'Zenit',
        'Aster',
        'Helios',
        'Lumen',
        'Nova',
        'Pixel',
        'Puls',
        'Nurt',
        'Prąd',
        'Impuls',
        'Kadr',
        'Punkt',
        'Ślad',
      ],
    },
  } as const

  const selected =
    dictionaries[
      nickStyle.value
    ]

  const adjective =
    selected.adjectives[
      Math.floor(
        Math.random() *
        selected.adjectives.length,
      )
    ]

  const noun =
    selected.nouns[
      Math.floor(
        Math.random() *
        selected.nouns.length,
      )
    ]

  nick.value =
    `${adjective} ${noun}`

  fieldError.value =
    null
}


async function saveProfile(): Promise<void> {
  await perform(
    async () => {
      const response =
        await updateAccountProfile(
          nick.value,
        )

      emit(
        'userUpdated',
        response.user,
      )

      message.value =
        'Profil został zapisany.'
    },
  )
}


async function setPrivacy(
  mode: PrivacyMode,
): Promise<void> {
  await perform(
    async () => {
      const response =
        await updateAccountPrivacy(
          mode,
        )

      emit(
        'userUpdated',
        response.user,
      )
    },
  )
}


async function generateNewShareLink(): Promise<void> {
  await perform(
    async () => {
      const response =
        await regenerateShareLink()

      emit(
        'userUpdated',
        response.user,
      )

      message.value =
        'Wygenerowano nowy link. Poprzedni link przestał działać.'
    },
  )
}


async function copyText(
  value: string | null,
): Promise<void> {
  if (!value) {
    return
  }

  await navigator.clipboard.writeText(
    value,
  )

  message.value =
    'Link został skopiowany.'
}


async function submitPasswordChange(): Promise<void> {
  if (!passwordRequirementsMet.value) {
    error.value =
      'Nowe hasło nie spełnia wszystkich wymaganych warunków.'
    fieldError.value =
      'password'
    return
  }

  await perform(
    async () => {
      const response =
        await changeAccountPassword(
          currentPassword.value,
          password.value,
          passwordRepeat.value,
        )

      message.value =
        response.message ??
        'Hasło zostało zmienione.'

      emit('loggedOut')
    },
  )
}


async function submitEmailChange(): Promise<void> {
  await perform(
    async () => {
      const response =
        await requestEmailChange(
          currentPassword.value,
          newEmail.value,
        )

      message.value =
        response.message ??
        'Sprawdź nową skrzynkę e-mail.'

      currentPassword.value = ''
    },
  )
}


async function confirmNewEmail(): Promise<void> {
  if (!props.token) {
    error.value =
      'Brak tokenu zmiany adresu e-mail.'
    return
  }

  await perform(
    async () => {
      const response =
        await confirmEmailChange(
          props.token ?? '',
        )

      message.value =
        response.message ??
        'Nowy adres e-mail został potwierdzony. Ze względów bezpieczeństwa zaloguj się ponownie.'

      emailChangeComplete.value =
        true

      emit('loggedOut')

      window.history.replaceState(
        {},
        document.title,
        window.location.pathname,
      )
    },
  )
}


async function doLogout(): Promise<void> {
  await perform(
    async () => {
      await logoutAccount()
      emit('loggedOut')
    },
  )
}


async function exportData(
  format: 'csv' | 'xlsx' | 'json',
): Promise<void> {
  await perform(
    async () => {
      await downloadAccountExport(
        format,
      )

      message.value =
        format === 'xlsx'
          ? 'Pobrano plik Excel XLSX.'
          : format === 'csv'
            ? 'Pobrano eksport CSV.'
            : 'Pobrano pełną kopię JSON.'
    },
  )
}
</script>


<template>
  <aside
    class="account-panel"
    :class="{
      'account-panel--centered':
        centered,
    }"
  >
    <button
      type="button"
      class="account-panel__close"
      aria-label="Zamknij"
      title="Zamknij"
      @click="emit('close')"
    >
      ×
    </button>

    <template v-if="currentMode === 'login'">
      <div class="account-panel__eyebrow">
        Konto
      </div>

      <h2>Zaloguj się</h2>

      <form
        class="account-form"
        @submit.prevent="submitLogin"
      >
        <label>
          <span>E-mail</span>
          <input
            v-model.trim="email"
            type="email"
            autocomplete="email"
            :class="{
              'account-input--error':
                loginFieldsInvalid,
            }"
            required
          >
        </label>

        <label>
          <span>Hasło</span>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            :class="{
              'account-input--error':
                loginFieldsInvalid,
            }"
            required
          >
        </label>

        <label class="account-check">
          <input
            v-model="remember"
            type="checkbox"
          >
          <span>Zapamiętaj mnie</span>
        </label>

        <button
          class="account-primary"
          type="submit"
          :disabled="loading"
        >
          Zaloguj się
        </button>
      </form>

      <div class="account-links">
        <button
          type="button"
          @click="emit('mode', 'forgot')"
        >
          Nie pamiętasz hasła?
        </button>

        <button
          type="button"
          @click="emit('mode', 'register')"
        >
          Załóż konto
        </button>
      </div>
    </template>

    <template v-else-if="currentMode === 'register'">
      <template v-if="!registrationComplete">
        <div class="account-panel__eyebrow">
          Nowe konto
        </div>

        <h2>Załóż konto</h2>

        <p class="account-intro account-intro--register">
          Po rejestracji wyślemy e-mail aktywacyjny. Do czasu kliknięcia linku konto pozostaje nieaktywne.
        </p>

        <form
          class="account-form"
          @submit.prevent="submitRegister"
        >
          <label>
            <span>E-mail</span>
            <input
              v-model.trim="email"
              type="email"
              autocomplete="email"
              :class="{
                'account-input--error':
                  fieldError === 'email',
              }"
              required
            >
          </label>

          <label>
            <span>Nick / nazwa pod jaką będziesz występował</span>

            <div class="nick-input-row">
              <input
                v-model.trim="nick"
                type="text"
                autocomplete="nickname"
                maxlength="60"
                :class="{
                  'account-input--error':
                    fieldError === 'nick',
                }"
                required
              >

              <button
                type="button"
                class="nick-generator"
                @click="generateNick"
              >
                Wygeneruj
              </button>

              <select
                v-model="nickStyle"
                class="nick-style-select"
                aria-label="Styl generowanej nazwy"
              >
                <option value="travel">
                  Podróżniczy
                </option>

                <option value="aviation">
                  Lotniczy
                </option>

                <option value="neutral">
                  Neutralny
                </option>
              </select>
            </div>
          </label>

          <label>
            <span>Hasło</span>
            <input
              v-model="password"
              type="password"
              autocomplete="new-password"
              :class="{
                'account-input--error':
                  fieldError === 'password',
              }"
              required
            >
          </label>

          <div class="password-rules">
            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasMinLength,
              }"
            >
              <span class="password-rule__dot" />
              10 znaków
              <strong>{{ passwordLength }}/10</strong>
            </span>

            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasEnoughDigits,
              }"
            >
              <span class="password-rule__dot" />
              2 cyfry
              <strong>{{ Math.min(passwordDigits, 2) }}/2</strong>
            </span>

            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasSpecial,
              }"
            >
              <span class="password-rule__dot" />
              1 znak specjalny
            </span>
          </div>

          <label>
            <span>Powtórz hasło</span>
            <input
              v-model="passwordRepeat"
              type="password"
              autocomplete="new-password"
              :class="{
                'account-input--error':
                  fieldError === 'password_repeat',
              }"
              required
            >
          </label>

          <button
            class="account-primary account-primary--register"
            type="submit"
            :disabled="loading"
          >
            Załóż konto
          </button>
        </form>

        <div class="account-links">
          <button
            type="button"
            class="account-link--register-login"
            @click="emit('mode', 'login')"
          >
            Mam już konto
          </button>
        </div>
      </template>

      <template v-else>
        <div class="account-panel__eyebrow">
          Rejestracja
        </div>

        <h2>Konto zostało utworzone</h2>

        <div class="registration-success">
          Konto zostało utworzone. Sprawdź e-mail i aktywuj konto przed pierwszym logowaniem.
        </div>

        <div class="account-links account-links--center">
          <button
            type="button"
            class="account-link--register-login"
            @click="emit('mode', 'login')"
          >
            Mam już konto
          </button>
        </div>
      </template>
    </template>

    <template v-else-if="currentMode === 'forgot'">
      <div class="account-panel__eyebrow">
        Hasło
      </div>

      <h2>Nie pamiętasz hasła?</h2>

      <p class="account-intro">
        Podaj e-mail. Jeśli konto istnieje, wyślemy jednorazowy link ważny przez 60 minut.
        <strong
          v-if="!user"
          class="legacy-account-note"
        >
          Ta sama funkcja aktywuje konta przeniesione ze starej Mapy Lotów.
        </strong>
      </p>

      <form
        class="account-form"
        @submit.prevent="submitForgot"
      >
        <label>
          <span>E-mail</span>
          <input
            v-model.trim="email"
            type="email"
            autocomplete="email"
            required
          >
        </label>

        <button
          class="account-primary"
          type="submit"
          :disabled="loading"
        >
          Wyślij link
        </button>
      </form>

      <div
        class="account-links"
      >
        <button
          v-if="!user"
          type="button"
          @click="emit('mode', 'login')"
        >
          Wróć do logowania
        </button>

        <button
          v-else
          type="button"
          @click="emit('mode', 'password')"
        >
          ← Wróć do zmiany hasła
        </button>
      </div>
    </template>

    <template v-else-if="currentMode === 'activate'">
      <div class="account-panel__eyebrow">
        Aktywacja
      </div>

      <h2>Aktywuj konto</h2>

      <p class="account-intro">
        Kliknij przycisk, aby potwierdzić adres e-mail i aktywować konto.
      </p>

      <button
        class="account-primary account-primary--standalone"
        type="button"
        :disabled="loading"
        @click="submitActivation"
      >
        Aktywuj konto
      </button>

      <form
        v-if="activationNeedsNewLink"
        class="activation-resend"
        @submit.prevent="resendActivationLink"
      >
        <p>
          Jeśli link wygasł albo jest nieprawidłowy, możesz poprosić o nowy.
        </p>

        <label>
          <span>E-mail konta</span>
          <input
            v-model.trim="email"
            type="email"
            autocomplete="email"
            required
          >
        </label>

        <button
          type="submit"
          :disabled="loading"
        >
          Wyślij nowy link aktywacyjny
        </button>
      </form>
    </template>

    <template v-else-if="currentMode === 'reset'">
      <div class="account-panel__eyebrow">
        Hasło
      </div>

      <h2>Ustaw nowe hasło</h2>

      <form
        class="account-form"
        @submit.prevent="submitReset"
      >
        <label>
          <span>Nowe hasło</span>
          <input
            v-model="password"
            type="password"
            autocomplete="new-password"
            :class="{
              'account-input--error':
                fieldError === 'password',
            }"
            required
          >
        </label>

        <div class="password-rules">
          <span
            class="password-rule"
            :class="{
              'password-rule--ok':
                passwordHasMinLength,
            }"
          >
            <span class="password-rule__dot" />
            10 znaków
            <strong>{{ passwordLength }}/10</strong>
          </span>

          <span
            class="password-rule"
            :class="{
              'password-rule--ok':
                passwordHasEnoughDigits,
            }"
          >
            <span class="password-rule__dot" />
            2 cyfry
            <strong>{{ Math.min(passwordDigits, 2) }}/2</strong>
          </span>

          <span
            class="password-rule"
            :class="{
              'password-rule--ok':
                passwordHasSpecial,
            }"
          >
            <span class="password-rule__dot" />
            1 znak specjalny
          </span>
        </div>

        <label>
          <span>Powtórz hasło</span>
          <input
            v-model="passwordRepeat"
            type="password"
            autocomplete="new-password"
            :class="{
              'account-input--error':
                fieldError === 'password_repeat',
            }"
            required
          >
        </label>

        <button
          class="account-primary"
          type="submit"
          :disabled="loading"
        >
          Ustaw hasło
        </button>
      </form>

      <div class="account-links">
        <button
          type="button"
          @click="emit('mode', 'login')"
        >
          Przejdź do logowania
        </button>
      </div>
    </template>

    <template v-else-if="currentMode === 'email-confirm'">
      <div class="account-panel__eyebrow">
        E-mail
      </div>

      <h2>Potwierdź nowy adres e-mail</h2>

      <p class="account-intro">
        Kliknij przycisk, aby zakończyć zmianę adresu e-mail konta.
      </p>

      <button
        v-if="!emailChangeComplete"
        class="account-primary account-primary--standalone"
        type="button"
        :disabled="loading"
        @click="confirmNewEmail"
      >
        Potwierdź nowy e-mail
      </button>

      <button
        v-else
        class="account-primary account-primary--standalone"
        type="button"
        @click="emit('mode', 'login')"
      >
        Zaloguj
      </button>
    </template>

    <template v-else-if="user">
      <div
        v-if="currentMode === 'account'"
        class="account-settings-title"
      >
        Ustawienia Konta
      </div>

      <div class="account-profile-head">
        <div class="account-avatar">
          <img
            v-if="!avatarFailed"
            :src="user.avatar_url"
            alt=""
            @error="avatarFailed = true"
          >

          <span v-else>
            {{ initial }}
          </span>
        </div>

        <div>
          <div class="account-panel__eyebrow">
            Konto
          </div>

          <h2>{{ user.nick }}</h2>

          <div class="account-profile-email">
            {{ user.email }}
          </div>
        </div>
      </div>

      <template v-if="currentMode === 'profile'">
        <form
          class="account-form"
          @submit.prevent="saveProfile"
        >
          <label>
            <span>Nick</span>
            <input
              v-model.trim="nick"
              type="text"
              maxlength="60"
              required
            >
          </label>

          <label>
            <span>E-mail</span>

            <div class="profile-email-row">
              <input
                :value="user.email"
                type="email"
                disabled
              >

              <button
                type="button"
                class="account-secondary"
                @click="emit('mode', 'email')"
              >
                Zmień e-mail
              </button>
            </div>
          </label>

          <button
            class="account-primary"
            type="submit"
            :disabled="loading"
          >
            Zapisz profil
          </button>
        </form>
      </template>

      <template v-else-if="currentMode === 'privacy'">
        <div class="privacy-options">
          <button
            type="button"
            :class="{ active: user.privacy_mode === 'private' }"
            @click="setPrivacy('private')"
          >
            <strong>🔒 Prywatna</strong>
            <span>Mapę widzisz tylko Ty po zalogowaniu.</span>
          </button>

          <button
            type="button"
            :class="{ active: user.privacy_mode === 'link' }"
            @click="setPrivacy('link')"
          >
            <strong>🔗 Dostępna przez link</strong>
            <span>Każdy posiadający specjalny link może zobaczyć mapę.</span>
          </button>

          <button
            type="button"
            :class="{ active: user.privacy_mode === 'public' }"
            @click="setPrivacy('public')"
          >
            <strong>🌐 Publiczna</strong>
            <span>Twoja mapa lotów oraz statystyki są publicznie widoczne.</span>
          </button>
        </div>

        <div
          v-if="user.privacy_mode === 'link' && user.share_url"
          class="share-box"
        >
          <span>Link do mapy</span>

          <p class="share-box__description">
            Ten adres możesz przesłać wybranym osobom. Każdy, kto posiada ten link, może zobaczyć Twoją mapę i statystyki, ale profil nie jest publicznie wyszukiwalny.
          </p>

          <code>{{ user.share_url }}</code>

          <div class="share-box__actions">
            <button
              type="button"
              @click="copyText(user.share_url)"
            >
              Kopiuj
            </button>

            <button
              type="button"
              @click="generateNewShareLink"
            >
              Wygeneruj nowy link
            </button>
          </div>
        </div>

        <div
          v-if="user.privacy_mode === 'public' && user.public_url"
          class="share-box"
        >
          <span>Publiczny profil</span>

          <p class="share-box__description">
            To stały adres Twojego publicznego profilu. Możesz go udostępnić na stronie, blogu lub w mediach społecznościowych. Osoba odwiedzająca ten adres zobaczy Twoją mapę lotów i publiczne statystyki.
          </p>

          <code>{{ user.public_url }}</code>

          <div class="share-box__actions">
            <button
              type="button"
              @click="copyText(user.public_url)"
            >
              Kopiuj
            </button>
          </div>
        </div>
      </template>

      <template v-else-if="currentMode === 'email'">
        <p class="account-intro account-intro--account">
          Nowy adres zacznie działać dopiero po kliknięciu linku potwierdzającego wysłanego na nową skrzynkę. Dotychczasowy adres pozostanie aktywny do tego momentu.
        </p>

        <form
          class="account-form"
          @submit.prevent="submitEmailChange"
        >
          <label>
            <span>Nowy adres e-mail</span>
            <input
              v-model.trim="newEmail"
              type="email"
              autocomplete="email"
              :class="{
                'account-input--error':
                  fieldError === 'email',
              }"
              required
            >
          </label>

          <label>
            <span>Aktualne hasło</span>
            <input
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
            >
          </label>

          <button
            class="account-primary"
            type="submit"
            :disabled="loading"
          >
            Wyślij potwierdzenie
          </button>
        </form>
      </template>

      <template v-else-if="currentMode === 'password'">
        <form
          class="account-form"
          @submit.prevent="submitPasswordChange"
        >
          <label>
            <span>Aktualne hasło</span>
            <input
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
            >
          </label>

          <label>
            <span>Nowe hasło</span>
            <input
              v-model="password"
              type="password"
              autocomplete="new-password"
              :class="{
                'account-input--error':
                  fieldError === 'password',
              }"
              required
            >
          </label>

          <div class="password-rules">
            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasMinLength,
              }"
            >
              <span class="password-rule__dot" />
              10 znaków
              <strong>{{ passwordLength }}/10</strong>
            </span>

            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasEnoughDigits,
              }"
            >
              <span class="password-rule__dot" />
              2 cyfry
              <strong>{{ Math.min(passwordDigits, 2) }}/2</strong>
            </span>

            <span
              class="password-rule"
              :class="{
                'password-rule--ok':
                  passwordHasSpecial,
              }"
            >
              <span class="password-rule__dot" />
              1 znak specjalny
            </span>
          </div>

          <label>
            <span>Powtórz nowe hasło</span>
            <input
              v-model="passwordRepeat"
              type="password"
              autocomplete="new-password"
              :class="{
                'account-input--error':
                  fieldError === 'password_repeat',
              }"
              required
            >
          </label>

          <button
            class="account-primary"
            type="submit"
            :disabled="loading"
          >
            Zmień hasło
          </button>
        </form>

        <div class="account-links account-links--password-help">
          <button
            type="button"
            @click="emit('mode', 'forgot')"
          >
            Nie pamiętam aktualnego hasła
          </button>
        </div>
      </template>

      <template v-else-if="currentMode === 'export'">
        <div class="export-panel">
          <h3>Eksport danych</h3>

          <p>
            Pobierz własne dane z Mapy Lotów. Eksport nie usuwa ani nie zmienia żadnych danych w koncie.
          </p>

          <button
            type="button"
            class="export-option"
            :disabled="loading"
            @click="exportData('xlsx')"
          >
            <strong>Excel dla Windows (.xlsx)</strong>
            <span>
              Gotowy skoroszyt z arkuszami Loty, Podsumowanie i Profil. Najwygodniejszy format do pracy w Microsoft Excel.
            </span>
          </button>

          <button
            type="button"
            class="export-option"
            :disabled="loading"
            @click="exportData('csv')"
          >
            <strong>CSV</strong>
            <span>
              Prosta tabela wszystkich lotów, zgodna z Excelem i innymi arkuszami kalkulacyjnymi.
            </span>
          </button>

          <button
            type="button"
            class="export-option"
            :disabled="loading"
            @click="exportData('json')"
          >
            <strong>Pełna kopia JSON</strong>
            <span>
              Techniczna kopia profilu i wszystkich lotów, przydatna do późniejszego importu lub archiwizacji.
            </span>
          </button>
        </div>
      </template>

      <template v-else>
        <div class="account-menu">
          <button
            type="button"
            @click="emit('mode', 'profile')"
          >
            <strong>Profil</strong>
            <span>Nick i dane konta</span>
          </button>

          <button
            type="button"
            @click="emit('mode', 'privacy')"
          >
            <strong>Prywatność mapy</strong>
            <span>Prywatna, przez link lub publiczna</span>
          </button>

          <button
            type="button"
            @click="emit('mode', 'password')"
          >
            <strong>Zmiana hasła</strong>
            <span>Zmień hasło i unieważnij pozostałe sesje</span>
          </button>

          <button
            type="button"
            @click="emit('mode', 'export')"
          >
            <strong>Eksport danych</strong>
            <span>Pobierz CSV lub pełną kopię JSON</span>
          </button>

          <button
            type="button"
            class="account-menu__logout"
            @click="doLogout"
          >
            <strong>Wyloguj</strong>
          </button>
        </div>
      </template>

      <div
        v-if="currentMode !== 'account'"
        class="account-links account-links--back"
      >
        <button
          type="button"
          @click="emit('mode', 'account')"
        >
          ← Wróć do konta
        </button>
      </div>
    </template>

    <div
      v-if="error"
      class="account-message account-message--error"
    >
      {{ error }}
    </div>

    <div
      v-if="message"
      class="account-message account-message--success"
    >
      {{ message }}
    </div>
  </aside>
</template>


<style scoped>
.account-panel {
  position: absolute;
  top: 18px;
  right: 18px;
  z-index: 36;
  width: min(430px, calc(100vw - 36px));
  max-height: calc(100vh - 38px);
  overflow-y: auto;
  box-sizing: border-box;
  padding: 22px;
  border: 1px solid #dde3e9;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.985);
  box-shadow: 0 16px 42px rgba(15, 23, 42, 0.16);
  color: #243244;
  backdrop-filter: blur(10px);
}

.account-panel--centered {
  position: fixed;
  top: 50%;
  right: auto;
  left: 50%;
  width: min(470px, calc(100vw - 36px));
  max-height: calc(100vh - 36px);
  transform: translate(-50%, -50%);
}

.account-panel__close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 8px;
  background: #f1f3f5;
  color: #56616e;
  cursor: pointer;
  font-size: 22px;
}

.account-panel__eyebrow {
  margin-bottom: 5px;
  color: #9099a5;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.account-settings-title {
  margin: 0 40px 16px 0;
  color: #0b2d5c;
  font-size: 22px;
  font-weight: 800;
  line-height: 1.15;
}

h2 {
  margin: 0;
  padding-right: 40px;
  color: #0b2d5c;
  font-size: 21px;
  line-height: 1.15;
}

h3 {
  margin: 0;
  color: #0b2d5c;
  font-size: 18px;
}

.account-intro {
  margin: 10px 0 16px;
  color: #697686;
  font-size: 12px;
  line-height: 1.5;
}

.account-intro--register {
  font-size: 12px;
}

.legacy-account-note {
  display: block;
  margin-top: 7px;
  color: #0b2d5c;
  font-weight: 800;
}

.account-form {
  display: grid;
  gap: 12px;
  margin-top: 18px;
}

.account-form label:not(.account-check) {
  display: grid;
  gap: 5px;
}

.account-form label > span {
  color: #657180;
  font-size: 10.5px;
  font-weight: 700;
}

.account-form input[type='text'],
.account-form input[type='email'],
.account-form input[type='password'] {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 11px;
  border: 1px solid #d7dde4;
  border-radius: 8px;
  background: #fff;
  color: #0b2d5c;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  outline: none;
}

.account-form input:focus {
  border-color: #9bb0ca;
  box-shadow: 0 0 0 3px rgba(11, 45, 92, 0.06);
}

.account-form input:disabled {
  background: #f5f6f7;
  color: #7a8490;
}

.account-input--error {
  border-color: #d96666 !important;
  background: #fff8f8 !important;
  box-shadow: 0 0 0 3px rgba(185, 55, 55, 0.07) !important;
}

.account-check {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #667382;
  font-size: 10px;
}

.account-primary {
  min-height: 41px;
  border: 1px solid #0b2d5c;
  border-radius: 8px;
  background: #0b2d5c;
  color: #fff;
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
}

.account-primary:disabled {
  cursor: wait;
  opacity: 0.55;
}

.account-primary--register {
  font-size: 13px;
}

.account-primary--standalone {
  width: 100%;
  margin-top: 16px;
}

.account-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 10px;
  margin-top: 14px;
}

.account-links--center {
  justify-content: center;
}

.account-links button {
  padding: 0;
  border: 0;
  background: transparent;
  color: #506985;
  cursor: pointer;
  font-size: 10.5px;
  font-weight: 700;
}

.account-link--register-login {
  font-size: 11.5px !important;
}

.account-links button:hover {
  color: #0b2d5c;
  text-decoration: underline;
  text-underline-offset: 3px;
}

.account-links--back,
.account-links--password-help {
  justify-content: flex-start;
  margin-top: 18px;
}

.account-message {
  margin-top: 15px;
  padding: 10px 11px;
  border-radius: 8px;
  font-size: 11px;
  line-height: 1.45;
}

.account-message--error {
  border: 1px solid #f1c7c7;
  background: #fff5f5;
  color: #9b2c2c;
}

.account-message--success {
  border: 1px solid #cfe3d4;
  background: #f3fbf5;
  color: #2f6c3f;
}

.registration-success {
  margin-top: 18px;
  padding: 18px;
  border: 1px solid #cfe3d4;
  border-radius: 10px;
  background: #f3fbf5;
  color: #2f6c3f;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.55;
  text-align: center;
}

.nick-input-row {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    78px
    88px;
  gap: 6px;
  align-items: stretch;
}

.profile-email-row {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    auto;
  gap: 8px;
  align-items: stretch;
}

.nick-generator,
.nick-style-select,
.account-secondary {
  min-width: 0;
  border: 1px solid #cfd9e3;
  border-radius: 8px;
  background: #f7f9fb;
  color: #0b2d5c;
  cursor: pointer;
  font-size: 9.5px;
  font-weight: 750;
}

.nick-style-select {
  padding: 0 5px;
}

.nick-generator,
.account-secondary {
  padding: 0 8px;
}

.nick-generator:hover,
.account-secondary:hover {
  background: #edf3f8;
  border-color: #b8c9d9;
}

.password-rules {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 12px;
  margin-top: -4px;
  padding: 7px 9px;
  overflow-x: auto;
  border: 1px solid #e2e7ec;
  border-radius: 8px;
  background: #f8fafb;
  white-space: nowrap;
}

.password-rule {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 4px;
  color: #8b95a1;
  font-size: 9.5px;
  font-weight: 650;
  transition: color 0.15s ease;
}

.password-rule strong {
  color: inherit;
  font-size: 9px;
}

.password-rule__dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
  border-radius: 50%;
  background: #c7ced6;
  transition: background 0.15s ease;
}

.password-rule--ok {
  color: #2f7b48;
}

.password-rule--ok .password-rule__dot {
  background: #3ea862;
}

.account-profile-head {
  display: flex;
  align-items: center;
  gap: 13px;
  padding-right: 35px;
}

.account-avatar {
  display: flex;
  width: 54px;
  height: 54px;
  flex: 0 0 54px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 50%;
  background: linear-gradient(145deg, #173f70, #0b2d5c);
  color: #fff;
  font-size: 22px;
  font-weight: 800;
  box-shadow: 0 4px 12px rgba(11, 45, 92, 0.18);
}

.account-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.account-profile-email {
  margin-top: 4px;
  color: #7c8794;
  font-size: 11px;
}

.account-menu,
.privacy-options {
  display: grid;
  gap: 8px;
  margin-top: 20px;
}

.account-menu button,
.privacy-options button,
.export-option {
  display: grid;
  gap: 5px;
  width: 100%;
  padding: 13px 14px;
  border: 1px solid #e0e5ea;
  border-radius: 9px;
  background: #fff;
  color: #263547;
  cursor: pointer;
  text-align: left;
}

.account-menu button:hover,
.privacy-options button:hover,
.privacy-options button.active,
.export-option:hover {
  border-color: #aebfd1;
  background: #f7f9fb;
}

.account-menu strong,
.privacy-options strong,
.export-option strong {
  color: #0b2d5c;
  font-size: 12px;
}

.account-menu span,
.privacy-options span,
.export-option span {
  color: #667586;
  font-size: 11px;
  line-height: 1.4;
}

.account-menu__logout strong {
  color: #8b3c3c;
}

.share-box {
  display: grid;
  gap: 8px;
  margin-top: 14px;
  padding: 12px;
  border: 1px solid #dce5ec;
  border-radius: 9px;
  background: #f7fafc;
}

.share-box > span {
  color: #5f6f80;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}

.share-box__description {
  margin: 0;
  color: #667586;
  font-size: 11px;
  line-height: 1.5;
}

.share-box code {
  display: block;
  padding: 10px 11px;
  overflow-wrap: anywhere;
  border: 1px solid #d9e3ec;
  border-radius: 7px;
  background: #ffffff;
  color: #164a7b;
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 10.5px;
  font-weight: 650;
  line-height: 1.5;
}

.share-box__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}

.share-box__actions button {
  padding: 8px 10px;
  border: 1px solid #d6dce3;
  border-radius: 7px;
  background: #fff;
  color: #0b2d5c;
  cursor: pointer;
  font-size: 10.5px;
  font-weight: 750;
}

.activation-resend {
  display: grid;
  gap: 9px;
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid #e3e8ed;
}

.activation-resend p {
  margin: 0;
  color: #687686;
  font-size: 11px;
  line-height: 1.5;
}

.activation-resend label {
  display: grid;
  gap: 5px;
}

.activation-resend label span {
  color: #657180;
  font-size: 10px;
  font-weight: 700;
}

.activation-resend input {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 11px;
  border: 1px solid #d7dde4;
  border-radius: 8px;
  background: #fff;
  color: #0b2d5c;
  font-size: 12px;
  font-weight: 700;
}

.activation-resend button {
  min-height: 38px;
  border: 1px solid #cfd9e3;
  border-radius: 8px;
  background: #f7f9fb;
  color: #0b2d5c;
  cursor: pointer;
  font-size: 10.5px;
  font-weight: 750;
}

.export-panel {
  display: grid;
  gap: 10px;
  margin-top: 20px;
}

.export-panel p {
  margin: 0 0 4px;
  color: #687686;
  font-size: 11px;
  line-height: 1.5;
}

.export-option:disabled {
  cursor: wait;
  opacity: 0.55;
}

@media (max-width: 560px) {
  .account-panel {
    top: 10px;
    right: 10px;
    width: calc(100vw - 20px);
    max-height: calc(100vh - 20px);
  }

  .account-panel--centered {
    top: 50%;
    right: auto;
    left: 50%;
    width: calc(100vw - 20px);
    transform: translate(-50%, -50%);
  }

  .nick-input-row {
    grid-template-columns: 1fr;
  }

  .nick-style-select,
  .nick-generator {
    min-height: 36px;
  }
}
</style>
