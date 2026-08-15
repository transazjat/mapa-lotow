<script setup lang="ts">
import 'flag-icons/css/flag-icons.min.css'

import {
  ref,
} from 'vue'

import type {
  FlightDetails,
} from '../types/flight'


type ExtendedFlightDetails =
  FlightDetails & {
    departure_country_code?: string | null
    arrival_country_code?: string | null
  }


defineProps<{
  flight: ExtendedFlightDetails | null
  loading: boolean
  error: string | null
}>()


const emit = defineEmits<{
  back: []
  close: []
  edit: []
  duplicate: []
  delete: []
}>()


const deleteConfirmOpen =
  ref(false)


function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'brak danych'
  }

  const date =
    new Date(
      `${value}T12:00:00`,
    )

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value
  }

  return new Intl.DateTimeFormat(
    undefined,
    {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    },
  ).format(date)
}


function formatTime(
  value: string | null,
): string {
  return value
    ? value.slice(
        0,
        5,
      )
    : '--:--'
}


function formatNumber(
  value: number | null,
): string {
  if (
    value === null
  ) {
    return 'brak danych'
  }

  return new Intl.NumberFormat(
    undefined,
  ).format(value)
}


function formatDuration(
  seconds: number | null,
): string {
  if (
    seconds === null
  ) {
    return 'brak danych'
  }

  const totalMinutes =
    Math.floor(
      seconds / 60,
    )

  const hours =
    Math.floor(
      totalMinutes / 60,
    )

  const minutes =
    totalMinutes % 60

  if (
    hours === 0
  ) {
    return `${minutes} min`
  }

  return `${hours} h ${minutes} min`
}


function travelClassLabel(
  value: string | null,
): string {
  switch (value) {
    case 'economy':
      return 'Ekonomiczna'

    case 'premium_economy':
      return 'Ekonomiczna Premium'

    case 'business':
      return 'Biznes'

    case 'first':
      return 'Pierwsza'

    default:
      return 'brak danych'
  }
}


function seatTypeLabel(
  value: string | null,
): string {
  switch (value) {
    case 'window':
      return 'Przy oknie'

    case 'middle':
      return 'Środkowe'

    case 'aisle':
      return 'Od przejścia'

    default:
      return 'brak danych'
  }
}


function travelReasonLabel(
  value: string | null,
): string {
  switch (value) {
    case 'private':
      return 'Prywatny'

    case 'business':
      return 'Biznesowy'

    default:
      return 'brak danych'
  }
}


function flagClass(
  code:
    string | null |
    undefined,
): string | null {
  if (!code) {
    return null
  }

  const normalized =
    code
      .trim()
      .toLowerCase()

  if (
    normalized.length !== 2
  ) {
    return null
  }

  return `fi fi-${normalized}`
}
</script>


<template>
  <aside class="flight-details-panel">

    <div class="panel-actions">

      <button
        type="button"
        class="panel-action panel-action--back"
        @click="
          emit(
            'back',
          )
        "
      >
        ← Wróć
      </button>


      <button
        type="button"
        class="panel-action panel-action--close"
        aria-label="Zamknij"
        title="Zamknij"
        @click="
          emit(
            'close',
          )
        "
      >
        ×
      </button>

    </div>


    <div
      v-if="loading"
      class="panel-state"
    >
      Ładowanie szczegółów lotu...
    </div>


    <div
      v-else-if="error"
      class="panel-state panel-state--error"
    >
      {{ error }}
    </div>


    <div
      v-else-if="flight"
      class="panel-content"
    >

      <!--
        Główna sekcja lotu.
        Nie powtarzamy już trasy
        nad tym elementem.
      -->

      <section class="management-actions">

        <button
          type="button"
          class="management-button"
          @click="emit('edit')"
        >
          Edytuj
        </button>

        <button
          type="button"
          class="management-button"
          @click="emit('duplicate')"
        >
          Duplikuj
        </button>

        <button
          type="button"
          class="management-button management-button--delete"
          @click="
            deleteConfirmOpen =
              true
          "
        >
          Usuń
        </button>

      </section>


      <section
        v-if="deleteConfirmOpen"
        class="delete-confirm"
      >
        <div class="delete-confirm__text">
          <strong>
            Usunąć ten lot?
          </strong>

          <span>
            {{
              flight.departure_iata ??
              '---'
            }}
            →
            {{
              flight.arrival_iata ??
              '---'
            }},
            {{
              formatDate(
                flight.departure_date,
              )
            }}.
            Tej operacji nie można cofnąć.
          </span>
        </div>

        <div class="delete-confirm__actions">
          <button
            type="button"
            class="delete-confirm__cancel"
            @click="
              deleteConfirmOpen =
                false
            "
          >
            Anuluj
          </button>

          <button
            type="button"
            class="delete-confirm__submit"
            @click="
              deleteConfirmOpen =
                false;
              emit('delete')
            "
          >
            Usuń lot
          </button>
        </div>
      </section>


      <section class="hero-card">

        <div class="hero-point">

          <div class="hero-code-row">

            <span
              v-if="
                flagClass(
                  flight.departure_country_code,
                )
              "
              :class="
                flagClass(
                  flight.departure_country_code,
                )!
              "
              class="hero-flag"
            ></span>


            <div class="hero-iata">
              {{
                flight.departure_iata ??
                '---'
              }}
            </div>

          </div>


          <div class="hero-time">
            {{
              formatTime(
                flight.departure_time,
              )
            }}
          </div>


          <div class="hero-city">
            {{ flight.departure_city }}
          </div>


          <div class="hero-airport">
            {{
              flight.departure_airport_name
            }}
          </div>


          <div class="hero-date">
            {{
              formatDate(
                flight.departure_date,
              )
            }}
          </div>

        </div>


        <div class="hero-center">

          <div class="hero-duration">
            {{
              formatDuration(
                flight.duration_seconds,
              )
            }}
          </div>


          <div class="hero-line">

            <span class="hero-plane">
              ✈
            </span>

          </div>


          <div class="hero-distance">
            {{
              formatNumber(
                flight.distance_km,
              )
            }}
            km
          </div>

        </div>


        <div class="hero-point hero-point--right">

          <div class="hero-code-row hero-code-row--right">

            <div class="hero-iata">
              {{
                flight.arrival_iata ??
                '---'
              }}
            </div>


            <span
              v-if="
                flagClass(
                  flight.arrival_country_code,
                )
              "
              :class="
                flagClass(
                  flight.arrival_country_code,
                )!
              "
              class="hero-flag"
            ></span>

          </div>


          <div class="hero-time">
            {{
              formatTime(
                flight.arrival_time,
              )
            }}
          </div>


          <div class="hero-city">
            {{ flight.arrival_city }}
          </div>


          <div class="hero-airport">
            {{
              flight.arrival_airport_name
            }}
          </div>


          <div class="hero-date">
            {{
              formatDate(
                flight.arrival_date,
              )
            }}
          </div>

        </div>

      </section>


      <section class="details-grid">

        <!-- Data startu / Data lądowania -->

        <div class="detail-card">

          <span>
            Data startu
          </span>

          <strong>
            {{
              formatDate(
                flight.departure_date,
              )
            }}
          </strong>

        </div>


        <div class="detail-card">

          <span>
            Data lądowania
          </span>

          <strong>
            {{
              formatDate(
                flight.arrival_date,
              )
            }}
          </strong>

        </div>


        <!-- Linia / Numer lotu -->

        <div class="detail-card">

          <span>
            Linia lotnicza
          </span>

          <strong>
            {{
              flight.airline_name ??
              'brak danych'
            }}
          </strong>

        </div>


        <div class="detail-card">

          <span>
            Numer lotu
          </span>

          <strong>
            {{
              flight.flight_number ??
              'brak danych'
            }}
          </strong>

        </div>


        <!-- Samolot / Cel -->

        <div class="detail-card">

          <span>
            Samolot
          </span>

          <strong>
            {{
              flight.aircraft_name ??
              'brak danych'
            }}
          </strong>

        </div>


        <div class="detail-card">

          <span>
            Cel podróży
          </span>

          <strong>
            {{
              travelReasonLabel(
                flight.travel_reason,
              )
            }}
          </strong>

        </div>


        <!-- Klasa / Miejsce -->

        <div class="detail-card">

          <span>
            Klasa
          </span>

          <strong>
            {{
              travelClassLabel(
                flight.travel_class,
              )
            }}
          </strong>

        </div>


        <div class="detail-card">

          <span>
            Miejsce
          </span>

          <strong>
            {{
              flight.seat_number
                ? `${flight.seat_number} - ${seatTypeLabel(
                    flight.seat_type,
                  )}`
                : seatTypeLabel(
                    flight.seat_type,
                  )
            }}
          </strong>

        </div>


        <!-- Czas / dystans -->

        <div class="detail-card">

          <span>
            Czas lotu
          </span>

          <strong>
            {{
              formatDuration(
                flight.duration_seconds,
              )
            }}
          </strong>

        </div>


        <div class="detail-card">

          <span>
            Dystans
          </span>

          <strong>
            {{
              flight.distance_km !==
                null
                ? `${formatNumber(
                    flight.distance_km,
                  )} km`
                : 'brak danych'
            }}
          </strong>

        </div>

      </section>


      <section
        v-if="flight.notes"
        class="notes-card"
      >

        <div class="notes-title">
          Notatki
        </div>

        <div class="notes-content">
          {{ flight.notes }}
        </div>

      </section>

    </div>


    <div
      v-else
      class="panel-state"
    >
      Brak danych lotu.
    </div>

  </aside>
</template>


<style scoped>
.flight-details-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 30;

  width: 430px;

  max-height:
    calc(100vh - 36px);

  overflow-y: auto;

  padding: 16px;

  background:
    rgba(
      255,
      255,
      255,
      0.97
    );

  backdrop-filter:
    blur(12px);

  border:
    1px solid
    rgba(
      0,
      0,
      0,
      0.08
    );

  border-radius: 16px;

  box-shadow:
    0 12px 35px
    rgba(
      0,
      0,
      0,
      0.16
    );
}


.panel-actions {
  display: flex;

  align-items: center;

  justify-content: space-between;

  margin-bottom: 12px;
}


.panel-action {
  border: 0;

  border-radius: 8px;

  background: #f3f3f3;

  color: #444;

  cursor: pointer;
}


.panel-action:hover {
  background: #e9e9e9;
}


.panel-action--back {
  padding:
    7px 11px;

  font-size: 12px;
  font-weight: 600;
}


.panel-action--close {
  width: 34px;
  height: 34px;

  font-size: 21px;

  line-height: 1;
}


.management-actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
  margin-bottom: 9px;
}


.management-button {
  min-height: 31px;
  padding: 0 10px;
  border: 1px solid #d9dde3;
  border-radius: 7px;
  background: #fff;
  color: #0b2d5c;
  cursor: pointer;
  font-size: 10px;
  font-weight: 700;
}


.management-button:hover {
  background: #f5f7f9;
}


.management-button--delete {
  border-color:
    rgba(
      160,
      33,
      33,
      0.22
    );

  color: #a02121;
}


.delete-confirm {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 10px;
  padding: 11px;
  border:
    1px solid
    rgba(
      160,
      33,
      33,
      0.18
    );
  border-radius: 9px;
  background:
    rgba(
      160,
      33,
      33,
      0.045
    );
}


.delete-confirm__text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}


.delete-confirm__text strong {
  color: #8f1f1f;
  font-size: 11px;
}


.delete-confirm__text span {
  color: #666;
  font-size: 10px;
  line-height: 1.4;
}


.delete-confirm__actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}


.delete-confirm__cancel,
.delete-confirm__submit {
  min-height: 31px;
  padding: 0 10px;
  border-radius: 7px;
  cursor: pointer;
  font-size: 10px;
  font-weight: 700;
}


.delete-confirm__cancel {
  border: 1px solid #d9dde3;
  background: #fff;
  color: #555;
}


.delete-confirm__submit {
  border: 1px solid #a02121;
  background: #a02121;
  color: #fff;
}


/*
|--------------------------------------------------------------------------
| Główna karta lotu
|--------------------------------------------------------------------------
*/

.hero-card {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    118px
    minmax(0, 1fr);

  align-items: center;

  gap: 10px;

  padding:
    14px 13px;

  background: #f7f7f7;

  border-radius: 12px;
}


.hero-point {
  min-width: 0;
}


.hero-point--right {
  text-align: right;
}


.hero-code-row {
  display: flex;

  align-items: center;

  gap: 7px;
}


.hero-code-row--right {
  justify-content: flex-end;
}


.hero-flag {
  width: 20px;
  height: 14px;

  border-radius: 2px;

  box-shadow:
    0 0 0 1px
    rgba(
      0,
      0,
      0,
      0.08
    );
}


.hero-iata {
  color: #0b2d5c;

  font-size: 23px;
  font-weight: 800;

  line-height: 1;
}


.hero-time {
  margin-top: 6px;

  color: #555;

  font-size: 23px;
  font-weight: 650;

  line-height: 1;
}


.hero-city {
  margin-top: 8px;

  overflow: hidden;

  color: #444;

  font-size: 11px;
  font-weight: 600;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.hero-airport {
  margin-top: 2px;

  overflow: hidden;

  color: #777;

  font-size: 10px;

  line-height: 1.2;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.hero-date {
  margin-top: 7px;

  color: #777;

  font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| Środek trasy
|--------------------------------------------------------------------------
*/

.hero-center {
  min-width: 0;

  text-align: center;
}


.hero-duration {
  color: #666;

  font-size: 10px;
}


.hero-line {
  position: relative;

  height: 2px;

  margin:
    9px 0;

  background: #0b2d5c;

  border-radius: 999px;
}


.hero-line::before,
.hero-line::after {
  position: absolute;

  top: 50%;

  width: 7px;
  height: 7px;

  border-radius: 50%;

  background: #0b2d5c;

  content: '';

  transform:
    translateY(-50%);
}


.hero-line::before {
  left: 0;
}


.hero-line::after {
  right: 0;
}


.hero-plane {
  position: absolute;

  top: 50%;
  left: 50%;

  padding:
    0 4px;

  background: #f7f7f7;

  color: #0b2d5c;

  font-size: 13px;

  line-height: 1;

  transform:
    translate(
      -50%,
      -50%
    );
}


.hero-distance {
  color: #666;

  font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| Szczegóły
|--------------------------------------------------------------------------
*/

.details-grid {
  display: grid;

  grid-template-columns:
    1fr 1fr;

  gap: 7px;

  margin-top: 12px;
}


.detail-card {
  min-height: 64px;

  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding:
    9px 8px;

  border:
    1px solid #e6e6e6;

  border-radius: 9px;

  background:
    rgba(
      255,
      255,
      255,
      0.72
    );

  text-align: center;
}


.detail-card span {
  color: #7a7a7a;

  font-size: 10px;
}


.detail-card strong {
  margin-top: 5px;

  color: #222;

  font-size: 12px;
  font-weight: 650;

  line-height: 1.25;
}


.notes-card {
  margin-top: 10px;

  padding: 11px;

  border:
    1px solid #e7e7e7;

  border-radius: 9px;

  background: white;
}


.notes-title {
  color: #777;

  font-size: 10px;
  font-weight: 700;
}


.notes-content {
  margin-top: 5px;

  color: #333;

  font-size: 11px;

  line-height: 1.45;

  white-space:
    pre-wrap;
}


.panel-state {
  padding:
    24px 10px;

  color: #666;

  font-size: 12px;

  text-align: center;
}


.panel-state--error {
  color: #a02121;
}


:deep(.fi) {
  display: inline-block;
}


@media (
  max-width: 900px
) {
  .flight-details-panel {
    top: auto;

    right: 10px;
    bottom: 10px;
    left: 10px;

    width: auto;

    max-height: 65vh;
  }
}
</style>