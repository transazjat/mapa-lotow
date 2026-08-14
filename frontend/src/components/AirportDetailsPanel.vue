<script setup lang="ts">
import {
  computed,
} from 'vue'

import 'flag-icons/css/flag-icons.min.css'

import type {
  AirportDirectionStat,
  Flight,
  SelectedAirport,
} from '../types/flight'


const props = defineProps<{
  airport: SelectedAirport
  flights: Flight[]
}>()


const emit = defineEmits<{
  close: []

  destination: [
    destination: AirportDirectionStat,
  ]

  origin: [
    origin: AirportDirectionStat,
  ]

  destinationDetails: [
    destination: AirportDirectionStat,
  ]

  originDetails: [
    origin: AirportDirectionStat,
  ]

  airport: [
    code: string,
  ]
}>()


/*
|--------------------------------------------------------------------------
| Dane państwa dla aktualnego lotniska
|--------------------------------------------------------------------------
*/

const countryCode =
  computed<string | null>(
    () => {
      for (
        const flight
        of props.flights
      ) {
        if (
          flight.departure_iata ===
          props.airport.code
        ) {
          return (
            flight.departure_country_code ??
            null
          )
        }

        if (
          flight.arrival_iata ===
          props.airport.code
        ) {
          return (
            flight.arrival_country_code ??
            null
          )
        }
      }

      return null
    },
  )


const countryLocale =
  computed(
    () => {
      const locale =
        navigator.language
          .toLowerCase()

      return locale.startsWith(
        'en',
      )
        ? 'en'
        : 'pl'
    },
  )


const countryName =
  computed(
    () => {
      if (
        !countryCode.value
      ) {
        return null
      }

      try {
        const names =
          new Intl.DisplayNames(
            [
              countryLocale.value,
            ],
            {
              type:
                'region',
            },
          )

        return (
          names.of(
            countryCode.value
              .toUpperCase(),
          ) ??
          countryCode.value
        )
      } catch {
        return countryCode.value
      }
    },
  )


/*
|--------------------------------------------------------------------------
| Kraj dla lotniska z listy rankingowej
|--------------------------------------------------------------------------
*/

function airportCountryCode(
  code:
    string | null,
): string | null {
  if (!code) {
    return null
  }


  for (
    const flight
    of props.flights
  ) {
    if (
      flight.departure_iata ===
      code
    ) {
      return (
        flight.departure_country_code ??
        null
      )
    }


    if (
      flight.arrival_iata ===
      code
    ) {
      return (
        flight.arrival_country_code ??
        null
      )
    }
  }


  return null
}


function flagClass(
  code:
    string | null,
): string | null {
  if (!code) {
    return null
  }

  const normalized =
    code
      .trim()
      .toLowerCase()

  if (
    normalized.length !==
    2
  ) {
    return null
  }

  return `fi fi-${normalized}`
}


/*
|--------------------------------------------------------------------------
| Nawigacja do innego lotniska
|--------------------------------------------------------------------------
*/

function openAirport(
  code:
    string | null,
): void {
  if (!code) {
    return
  }

  emit(
    'airport',
    code,
  )
}
</script>


<template>
  <aside class="airport-panel">

    <!-- ============================================================= -->
    <!-- NAGŁÓWEK                                                       -->
    <!-- ============================================================= -->

    <header class="airport-header">

      <div class="airport-icon">

        <svg
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            d="M22 16.5 13.5 12V5.5a1.5 1.5 0 0 0-3 0V12L2 16.5v2l8.5-2.5v4.5L8 22v1.5l4-1 4 1V22l-2.5-1.5V16l8.5 2.5z"
          />
        </svg>

      </div>


      <div class="airport-heading">

        <div class="airport-code">
          {{ airport.code ?? '---' }}
        </div>


        <div class="airport-name">
          {{ airport.name }}
        </div>


        <div class="airport-location">

          <span class="airport-city">
            {{ airport.city }}
          </span>


          <template
            v-if="
              countryName
            "
          >

            <span class="location-separator">
              •
            </span>


            <span
              v-if="
                flagClass(
                  countryCode,
                )
              "
              :class="
                flagClass(
                  countryCode,
                )!
              "
              class="country-flag"
            ></span>


            <span class="country-name">
              {{ countryName }}
            </span>

          </template>

        </div>

      </div>


      <button
        type="button"
        class="close-button"
        title="Zamknij"
        aria-label="Zamknij"
        @click="
          emit(
            'close',
          )
        "
      >
        ×
      </button>

    </header>


    <!-- ============================================================= -->
    <!-- PODSTAWOWE STATYSTYKI                                         -->
    <!-- ============================================================= -->

    <section class="airport-summary">

      <div class="summary-card">

        <strong>
          {{ airport.departures }}
        </strong>

        <span>
          odlotów
        </span>

      </div>


      <div class="summary-card">

        <strong>
          {{ airport.arrivals }}
        </strong>

        <span>
          przylotów
        </span>

      </div>


      <div class="summary-card">

        <strong>
          {{ airport.flights }}
        </strong>

        <span>
          operacji
        </span>

      </div>

    </section>


    <!-- ============================================================= -->
    <!-- NAJCZĘSTSZE KIERUNKI                                          -->
    <!-- ============================================================= -->

    <section
      v-if="
        airport.topDestinations.length
      "
      class="ranking-section"
    >

      <h3>
        Najczęstsze kierunki
      </h3>


      <div class="ranking-list">

        <div
          v-for="
            destination
            in airport.topDestinations
          "
          :key="
            `${destination.code}-${destination.name}`
          "
          class="ranking-row"
          @click="
            emit(
              'destination',
              destination,
            )
          "
        >

          <div class="ranking-place">

            <button
              type="button"
              class="airport-code-link"
              :disabled="
                !destination.code
              "
              :title="
                destination.code
                  ? `Pokaż lotnisko ${destination.code}`
                  : undefined
              "
              @click.stop="
                openAirport(
                  destination.code,
                )
              "
            >
              {{
                destination.code ??
                '---'
              }}
            </button>


            <span
              v-if="
                flagClass(
                  airportCountryCode(
                    destination.code,
                  ),
                )
              "
              :class="
                flagClass(
                  airportCountryCode(
                    destination.code,
                  ),
                )!
              "
              class="ranking-flag"
            ></span>


            <span class="ranking-city">
              {{ destination.city }}
            </span>

          </div>


          <div class="ranking-actions">

            <strong class="ranking-count">
              {{ destination.flights }}
            </strong>


            <button
              type="button"
              class="details-button"
              @click.stop="
                emit(
                  'destinationDetails',
                  destination,
                )
              "
            >

              <span>
                Szczegóły
              </span>


              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  d="M9 5l7 7-7 7"
                />
              </svg>

            </button>

          </div>

        </div>

      </div>

    </section>


    <!-- ============================================================= -->
    <!-- NAJCZĘSTSZE PORTY WYLOTU                                      -->
    <!-- ============================================================= -->

    <section
      v-if="
        airport.topOrigins.length
      "
      class="ranking-section"
    >

      <h3>
        Najczęstsze porty wylotu
      </h3>


      <div class="ranking-list">

        <div
          v-for="
            origin
            in airport.topOrigins
          "
          :key="
            `${origin.code}-${origin.name}`
          "
          class="ranking-row"
          @click="
            emit(
              'origin',
              origin,
            )
          "
        >

          <div class="ranking-place">

            <button
              type="button"
              class="airport-code-link"
              :disabled="
                !origin.code
              "
              :title="
                origin.code
                  ? `Pokaż lotnisko ${origin.code}`
                  : undefined
              "
              @click.stop="
                openAirport(
                  origin.code,
                )
              "
            >
              {{
                origin.code ??
                '---'
              }}
            </button>


            <span
              v-if="
                flagClass(
                  airportCountryCode(
                    origin.code,
                  ),
                )
              "
              :class="
                flagClass(
                  airportCountryCode(
                    origin.code,
                  ),
                )!
              "
              class="ranking-flag"
            ></span>


            <span class="ranking-city">
              {{ origin.city }}
            </span>

          </div>


          <div class="ranking-actions">

            <strong class="ranking-count">
              {{ origin.flights }}
            </strong>


            <button
              type="button"
              class="details-button"
              @click.stop="
                emit(
                  'originDetails',
                  origin,
                )
              "
            >

              <span>
                Szczegóły
              </span>


              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  d="M9 5l7 7-7 7"
                />
              </svg>

            </button>

          </div>

        </div>

      </div>

    </section>

  </aside>
</template>


<style scoped>
.airport-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 25;

  width: 390px;

  max-height:
    calc(
      100vh - 36px
    );

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
      0.17
    );
}


/*
|--------------------------------------------------------------------------
| Nagłówek
|--------------------------------------------------------------------------
*/

.airport-header {
  position: relative;

  display: grid;

  grid-template-columns:
    44px
    minmax(
      0,
      1fr
    )
    36px;

  align-items: start;

  gap: 10px;

  padding:
    4px 0
    3px;
}


.airport-icon {
  display: flex;

  width: 44px;
  height: 44px;

  align-items: center;

  justify-content: center;

  border:
    1px solid
    rgba(
      11,
      45,
      92,
      0.10
    );

  border-radius: 11px;

  background:
    rgba(
      11,
      45,
      92,
      0.055
    );

  color: #0b2d5c;
}


.airport-icon svg {
  width: 20px;
  height: 20px;

  fill: currentColor;
}


.airport-heading {
  min-width: 0;

  text-align: center;
}


.airport-code {
  color: #0b2d5c;

  font-size: 29px;
  font-weight: 800;

  letter-spacing:
    0.01em;

  line-height: 1;
}


/*
|--------------------------------------------------------------------------
| Lekko większa nazwa lotniska
|--------------------------------------------------------------------------
*/

.airport-name {
  margin-top: 3px;

  overflow: hidden;

  color: #333;

  font-size: 14px;
  font-weight: 650;

  text-overflow:
    ellipsis;

  white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| Miasto + kraj bliżej nazwy
|--------------------------------------------------------------------------
*/

.airport-location {
  display: flex;

  align-items: center;

  justify-content: center;

  flex-wrap: wrap;

  gap: 6px;

  margin-top: 4px;

  color: #777;

  font-size: 11px;
}


.airport-city {
  color: #666;
}


.location-separator {
  color: #c2c5ca;
}


.country-flag {
  width: 17px;
  height: 12px;

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


.country-name {
  color: #666;
}


.close-button {
  display: flex;

  width: 36px;
  height: 36px;

  align-items: center;

  justify-content: center;

  padding: 0;

  border: 0;

  border-radius: 8px;

  background: #f3f3f3;

  color: #555;

  cursor: pointer;

  font-size: 21px;

  line-height: 1;

  transition:
    background 0.15s ease,
    color 0.15s ease;
}


.close-button:hover {
  background: #e8e8e8;

  color: #222;
}


/*
|--------------------------------------------------------------------------
| Podstawowe liczby
|--------------------------------------------------------------------------
*/

.airport-summary {
  display: grid;

  grid-template-columns:
    repeat(
      3,
      1fr
    );

  gap: 7px;

  margin-top: 17px;
}


.summary-card {
  display: flex;

  min-height: 82px;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding:
    9px 6px;

  border:
    1px solid #e5e7eb;

  border-radius: 10px;

  background: #f7f7f8;

  text-align: center;
}


.summary-card strong {
  color: #9ca3af;

  font-size: 22px;
  font-weight: 700;

  line-height: 1;
}


.summary-card span {
  margin-top: 9px;

  color: #777;

  font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| Rankingi
|--------------------------------------------------------------------------
*/

.ranking-section {
  margin-top: 25px;
}


.ranking-section h3 {
  margin:
    0 0 9px;

  color: #444;

  font-size: 12px;
  font-weight: 700;

  text-align: center;
}


.ranking-list {
  display: grid;

  gap: 5px;
}


.ranking-row {
  display: flex;

  min-height: 43px;

  align-items: center;

  justify-content: space-between;

  gap: 8px;

  padding:
    5px 5px
    5px 9px;

  border:
    1px solid #e5e7eb;

  border-radius: 8px;

  background:
    rgba(
      255,
      255,
      255,
      0.74
    );

  cursor: pointer;

  transition:
    background 0.12s ease,
    border-color 0.12s ease;
}


.ranking-row:hover {
  border-color:
    rgba(
      11,
      45,
      92,
      0.13
    );

  background:
    rgba(
      11,
      45,
      92,
      0.025
    );
}


.ranking-place {
  display: flex;

  min-width: 0;

  align-items: center;

  gap: 7px;
}


.airport-code-link {
  flex: 0 0 auto;

  padding: 0;

  border: 0;
  border-bottom:
    1px solid
    rgba(
      11,
      45,
      92,
      0.20
    );

  background:
    transparent;

  color: #0b2d5c;

  cursor: pointer;

  font-size: 12px;
  font-weight: 800;

  line-height: 1.2;

  transition:
    border-color 0.12s ease,
    color 0.12s ease;
}


.airport-code-link:hover:not(:disabled) {
  border-bottom-color:
    #0b2d5c;
}


.airport-code-link:disabled {
  border-bottom: 0;

  color: #9ca3af;

  cursor: default;
}


/*
|--------------------------------------------------------------------------
| Flaga w rankingu
|--------------------------------------------------------------------------
*/

.ranking-flag {
  width: 16px;
  height: 11px;

  flex: 0 0 auto;

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


.ranking-city {
  overflow: hidden;

  color: #666;

  font-size: 11px;

  text-overflow:
    ellipsis;

  white-space: nowrap;
}


.ranking-actions {
  display: flex;

  flex: 0 0 auto;

  align-items: center;

  gap: 8px;
}


.ranking-count {
  min-width: 20px;

  color: #6b7280;

  font-size: 12px;
  font-weight: 700;

  text-align: right;
}


/*
|--------------------------------------------------------------------------
| Szczegóły trasy
|--------------------------------------------------------------------------
*/

.details-button {
  display: inline-flex;

  height: 30px;

  align-items: center;

  justify-content: center;

  gap: 4px;

  padding:
    0 8px;

  border:
    1px solid #dfe3e8;

  border-radius: 7px;

  background: #f8f9fa;

  color: #0b2d5c;

  cursor: pointer;

  font-size: 10px;
  font-weight: 650;

  transition:
    background 0.12s ease,
    border-color 0.12s ease;
}


.details-button:hover {
  border-color:
    rgba(
      11,
      45,
      92,
      0.22
    );

  background:
    rgba(
      11,
      45,
      92,
      0.055
    );
}


.details-button svg {
  display: block;

  width: 12px;
  height: 12px;

  fill: none;

  stroke: currentColor;

  stroke-width: 2;

  stroke-linecap:
    round;

  stroke-linejoin:
    round;
}


:deep(.fi) {
  display: inline-block;
}


@media (
  max-width: 900px
) {
  .airport-panel {
    top: auto;

    right: 10px;
    bottom: 10px;
    left: 10px;

    width: auto;

    max-height: 65vh;
  }
}
</style>