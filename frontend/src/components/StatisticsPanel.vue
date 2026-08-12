<script setup lang="ts">
import {
  computed,
} from 'vue'

import type {
  Flight,
} from '../types/flight'

import {
  isPlannedFlight,
} from '../utils/flightScope'


const props = defineProps<{
  flights: Flight[]
}>()


interface RankingItem {
  label: string
  sublabel?: string
  count: number
}


const totalDistance =
  computed(
    () =>
      props.flights.reduce(
        (sum, flight) =>
          sum +
          (
            flight.distance_km ??
            0
          ),
        0,
      ),
  )


const totalDuration =
  computed(
    () =>
      props.flights.reduce(
        (sum, flight) =>
          sum +
          (
            flight.duration_seconds ??
            0
          ),
        0,
      ),
  )


const plannedCount =
  computed(
    () =>
      props.flights.filter(
        isPlannedFlight,
      ).length,
  )


const completedCount =
  computed(
    () =>
      props.flights.length -
      plannedCount.value,
  )


const airportRanking =
  computed<RankingItem[]>(
    () => {
      const map =
        new Map<
          string,
          RankingItem
        >()


      for (
        const flight
        of props.flights
      ) {
        const airports = [
          {
            code:
              flight.departure_iata,
            city:
              flight.departure_city,
            name:
              flight.departure_airport_name,
          },

          {
            code:
              flight.arrival_iata,
            city:
              flight.arrival_city,
            name:
              flight.arrival_airport_name,
          },
        ]


        for (
          const airport
          of airports
        ) {
          const key =
            `${airport.code ?? ''}|${airport.name}`


          const existing =
            map.get(key)


          if (existing) {
            existing.count++
          } else {
            map.set(
              key,
              {
                label:
                  airport.code ??
                  airport.name,

                sublabel:
                  airport.city,

                count:
                  1,
              },
            )
          }
        }
      }


      return [...map.values()]
        .sort(
          (a, b) =>
            b.count -
            a.count,
        )
        .slice(0, 10)
    },
  )


const airlineRanking =
  computed<RankingItem[]>(
    () =>
      buildSimpleRanking(
        props.flights
          .map(
            (flight) =>
              flight.airline_name,
          )
          .filter(
            (
              value,
            ): value is string =>
              Boolean(value),
          ),
      ),
  )


const aircraftRanking =
  computed<RankingItem[]>(
    () =>
      buildSimpleRanking(
        props.flights
          .map(
            (flight) =>
              flight.aircraft_name,
          )
          .filter(
            (
              value,
            ): value is string =>
              Boolean(value),
          ),
      ),
  )


const routeRanking =
  computed<RankingItem[]>(
    () => {
      const map =
        new Map<
          string,
          RankingItem
        >()


      for (
        const flight
        of props.flights
      ) {
        const departure =
          flight.departure_iata ??
          flight.departure_city

        const arrival =
          flight.arrival_iata ??
          flight.arrival_city

        const key =
          `${departure} → ${arrival}`


        const existing =
          map.get(key)


        if (existing) {
          existing.count++
        } else {
          map.set(
            key,
            {
              label:
                key,

              sublabel:
                `${flight.departure_city} → ${flight.arrival_city}`,

              count:
                1,
            },
          )
        }
      }


      return [...map.values()]
        .sort(
          (a, b) =>
            b.count -
            a.count,
        )
        .slice(0, 10)
    },
  )


const uniqueAirports =
  computed(
    () =>
      airportRankingAll().length,
  )


const uniqueAirlines =
  computed(
    () =>
      new Set(
        props.flights
          .map(
            (flight) =>
              flight.airline_id,
          )
          .filter(
            (value) =>
              value !== null,
          ),
      ).size,
  )


const uniqueAircraft =
  computed(
    () =>
      new Set(
        props.flights
          .map(
            (flight) =>
              flight.aircraft_type_id,
          )
          .filter(
            (value) =>
              value !== null,
          ),
      ).size,
  )


const uniqueRoutes =
  computed(
    () =>
      new Set(
        props.flights.map(
          (flight) =>
            [
              flight.departure_airport_id,
              flight.arrival_airport_id,
            ].join('>'),
        ),
      ).size,
  )


function airportRankingAll():
  RankingItem[] {
  const map =
    new Map<
      string,
      RankingItem
    >()


  for (
    const flight
    of props.flights
  ) {
    const values = [
      {
        id:
          flight.departure_airport_id,

        code:
          flight.departure_iata,

        city:
          flight.departure_city,
      },

      {
        id:
          flight.arrival_airport_id,

        code:
          flight.arrival_iata,

        city:
          flight.arrival_city,
      },
    ]


    for (
      const value
      of values
    ) {
      const key =
        String(value.id)


      const existing =
        map.get(key)


      if (existing) {
        existing.count++
      } else {
        map.set(
          key,
          {
            label:
              value.code ??
              value.city,

            sublabel:
              value.city,

            count:
              1,
          },
        )
      }
    }
  }


  return [...map.values()]
}


function buildSimpleRanking(
  values: string[],
): RankingItem[] {
  const map =
    new Map<
      string,
      number
    >()


  for (const value of values) {
    map.set(
      value,

      (
        map.get(value) ??
        0
      ) + 1,
    )
  }


  return [...map.entries()]
    .map(
      (
        [
          label,
          count,
        ],
      ) => ({
        label,
        count,
      }),
    )
    .sort(
      (a, b) =>
        b.count -
        a.count,
    )
    .slice(0, 10)
}


function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    'pl-PL',
  ).format(value)
}


function formatDuration(
  seconds: number,
): string {
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

  return `${formatNumber(hours)} h ${minutes} min`
}
</script>


<template>
  <section class="statistics">

    <div class="stats-main-grid">

      <div class="main-card">

        <strong>
          {{ formatNumber(flights.length) }}
        </strong>

        <span>
          lotów
        </span>

      </div>


      <div class="main-card">

        <strong>
          {{ formatNumber(totalDistance) }}
        </strong>

        <span>
          kilometrów
        </span>

      </div>


      <div class="main-card">

        <strong>
          {{ formatDuration(totalDuration) }}
        </strong>

        <span>
          w powietrzu
        </span>

      </div>

    </div>


    <div
      v-if="
        completedCount > 0 &&
        plannedCount > 0
      "
      class="scope-breakdown"
    >

      <span>
        Odbyte:
        <strong>
          {{ completedCount }}
        </strong>
      </span>

      <span class="planned">
        Zaplanowane:
        <strong>
          {{ plannedCount }}
        </strong>
      </span>

    </div>


    <div class="small-grid">

      <div>
        <strong>
          {{ uniqueAirports }}
        </strong>
        <span>lotnisk</span>
      </div>

      <div>
        <strong>
          {{ uniqueAirlines }}
        </strong>
        <span>linii</span>
      </div>

      <div>
        <strong>
          {{ uniqueAircraft }}
        </strong>
        <span>typów</span>
      </div>

      <div>
        <strong>
          {{ uniqueRoutes }}
        </strong>
        <span>tras</span>
      </div>

    </div>


    <section class="ranking">

      <h3>
        Lotniska
      </h3>

      <div
        v-for="(item, index) in airportRanking"
        :key="`${item.label}-${index}`"
        class="ranking-row"
      >

        <span class="position">
          {{ index + 1 }}.
        </span>

        <div class="ranking-label">
          <strong>
            {{ item.label }}
          </strong>

          <small>
            {{ item.sublabel }}
          </small>
        </div>

        <b>
          {{ item.count }}
        </b>

      </div>

    </section>


    <section class="ranking">

      <h3>
        Linie lotnicze
      </h3>

      <div
        v-for="(item, index) in airlineRanking"
        :key="item.label"
        class="ranking-row"
      >

        <span class="position">
          {{ index + 1 }}.
        </span>

        <div class="ranking-label">
          <strong>
            {{ item.label }}
          </strong>
        </div>

        <b>
          {{ item.count }}
        </b>

      </div>

    </section>


    <section class="ranking">

      <h3>
        Samoloty
      </h3>

      <div
        v-for="(item, index) in aircraftRanking"
        :key="item.label"
        class="ranking-row"
      >

        <span class="position">
          {{ index + 1 }}.
        </span>

        <div class="ranking-label">
          <strong>
            {{ item.label }}
          </strong>
        </div>

        <b>
          {{ item.count }}
        </b>

      </div>

    </section>


    <section class="ranking">

      <h3>
        Trasy
      </h3>

      <div
        v-for="(item, index) in routeRanking"
        :key="item.label"
        class="ranking-row"
      >

        <span class="position">
          {{ index + 1 }}.
        </span>

        <div class="ranking-label">
          <strong>
            {{ item.label }}
          </strong>

          <small>
            {{ item.sublabel }}
          </small>
        </div>

        <b>
          {{ item.count }}
        </b>

      </div>

    </section>

  </section>
</template>


<style scoped>
.stats-main-grid {
  display: grid;

  gap: 7px;
}


.main-card {
  padding: 13px;

  background: #f4f4f4;

  border-radius: 9px;
}


.main-card strong {
  display: block;

  font-size: 18px;
}


.main-card span {
  display: block;

  margin-top: 3px;

  color: #777;

  font-size: 10px;
}


.scope-breakdown {
  display: flex;

  justify-content: space-between;

  gap: 10px;

  margin-top: 8px;

  padding: 8px 10px;

  border-radius: 7px;

  background: #f6f6f6;

  color: #666;

  font-size: 9px;
}


.scope-breakdown .planned {
  color: #c26812;
}


.small-grid {
  display: grid;

  grid-template-columns:
    repeat(4, 1fr);

  gap: 5px;

  margin-top: 8px;
}


.small-grid div {
  padding: 8px 4px;

  border:
    1px solid #e8e8e8;

  border-radius: 7px;

  text-align: center;
}


.small-grid strong {
  display: block;

  font-size: 13px;
}


.small-grid span {
  display: block;

  margin-top: 2px;

  color: #888;

  font-size: 8px;
}


.ranking {
  margin-top: 20px;
}


.ranking h3 {
  margin: 0 0 7px;

  font-size: 12px;
}


.ranking-row {
  display: grid;

  grid-template-columns:
    22px 1fr auto;

  align-items: center;

  gap: 6px;

  padding: 6px 2px;

  border-bottom:
    1px solid #eee;
}


.position {
  color: #aaa;

  font-size: 9px;
}


.ranking-label {
  min-width: 0;
}


.ranking-label strong {
  display: block;

  overflow: hidden;

  font-size: 10px;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.ranking-label small {
  display: block;

  overflow: hidden;

  margin-top: 1px;

  color: #888;

  font-size: 8px;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.ranking-row b {
  font-size: 10px;
}
</style>