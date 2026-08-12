<script setup lang="ts">
import 'flag-icons/css/flag-icons.min.css'


defineProps<{
  departureCode: string | null
  departureCity: string
  departureAirportName: string
  departureCountryCode?: string | null

  arrivalCode: string | null
  arrivalCity: string
  arrivalAirportName: string
  arrivalCountryCode?: string | null

  departureDate: string | null
  departureTime: string | null
  arrivalTime: string | null

  flightNumber: string | null
  airlineName: string | null
  aircraftName: string | null

  distanceKm: number | null
  durationSeconds: number | null

  planned?: boolean
  active?: boolean
}>()


const emit = defineEmits<{
  click: []
}>()


function formatDate(
  value: string | null,
): string {
  if (!value) {
    return 'brak daty'
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
    ? value.slice(0, 5)
    : '--:--'
}


function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    undefined,
  ).format(value)
}


function formatDuration(
  seconds: number | null,
): string {
  if (seconds === null) {
    return 'brak czasu'
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

  if (hours === 0) {
    return `${minutes} min`
  }

  return `${hours} h ${minutes} min`
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
  <button
    type="button"
    class="flight-card"
    :class="{
      'flight-card--planned':
        planned,

      'flight-card--active':
        active,
    }"
    @click="emit('click')"
  >

    <div class="boarding-route">

      <div class="airport-side">

        <div class="airport-code-row">

          <span
            v-if="
              flagClass(
                departureCountryCode,
              )
            "
            :class="
              flagClass(
                departureCountryCode,
              )!
            "
            class="route-flag"
          ></span>

          <strong class="iata-code">
            {{ departureCode ?? '---' }}
          </strong>

        </div>

        <div class="city-name">
          {{ departureCity }}
        </div>

        <div class="airport-name">
          {{ departureAirportName }}
        </div>

      </div>


      <div class="flight-symbol">

        <div class="flight-symbol__line"></div>

        <div class="flight-symbol__plane">
          ✈
        </div>

        <div class="flight-symbol__line"></div>

      </div>


      <div class="airport-side airport-side--right">

        <div class="airport-code-row airport-code-row--right">

          <strong class="iata-code">
            {{ arrivalCode ?? '---' }}
          </strong>

          <span
            v-if="
              flagClass(
                arrivalCountryCode,
              )
            "
            :class="
              flagClass(
                arrivalCountryCode,
              )!
            "
            class="route-flag"
          ></span>

        </div>

        <div class="city-name">
          {{ arrivalCity }}
        </div>

        <div class="airport-name">
          {{ arrivalAirportName }}
        </div>

      </div>

    </div>


    <div class="flight-meta-row">

      <div class="flight-meta-main">

        <span class="flight-number">
          {{ flightNumber ?? 'bez numeru' }}
        </span>

        <span class="meta-separator">
          ·
        </span>

        <span>
          {{ airlineName ?? 'linia nieznana' }}
        </span>

        <span class="meta-separator">
          ·
        </span>

        <span>
          {{ aircraftName ?? 'samolot nieznany' }}
        </span>

      </div>


      <div class="flight-date">

        <span
          v-if="planned"
          class="planned-badge"
        >
          planowany
        </span>

        <span>
          {{ formatDate(departureDate) }}
        </span>

      </div>

    </div>


    <div class="flight-stats-row">

      <span class="flight-time">
        {{ formatTime(departureTime) }}
        →
        {{ formatTime(arrivalTime) }}
      </span>

      <span>
        {{
          distanceKm !== null
            ? `${formatNumber(distanceKm)} km`
            : 'brak dystansu'
        }}
      </span>

      <span>
        {{ formatDuration(durationSeconds) }}
      </span>

    </div>

  </button>
</template>


<style scoped>
.flight-card {
  display: block;
  width: 100%;

  margin-bottom: 6px;
  padding: 9px 10px;

  border: 1px solid #e4e4e4;
  border-left: 4px solid #d62828;
  border-radius: 9px;

  background: rgba(255, 255, 255, 0.9);

  color: inherit;
  text-align: left;

  cursor: pointer;

  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    transform 0.15s ease;
}


.flight-card:hover {
  border-color:
    rgba(214, 40, 40, 0.28);

  border-left-color:
    #d62828;

  background:
    rgba(214, 40, 40, 0.035);

  transform:
    translateY(-1px);
}


.flight-card--planned {
  border-left-color:
    #f28c28;

  background:
    rgba(242, 140, 40, 0.055);
}


.flight-card--planned:hover {
  border-left-color:
    #f28c28;

  background:
    rgba(242, 140, 40, 0.09);
}


.flight-card--active {
  border-color:
    rgba(11, 45, 92, 0.32);

  border-left-color:
    #d62828;

  background:
    rgba(11, 45, 92, 0.075);

  box-shadow:
    inset 0 0 0 1px
    rgba(11, 45, 92, 0.07);
}


.flight-card--planned.flight-card--active {
  border-left-color:
    #f28c28;
}


.flight-card--active:hover {
  background:
    rgba(11, 45, 92, 0.095);
}


.boarding-route {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    76px
    minmax(0, 1fr);

  align-items: start;

  gap: 8px;
}


.airport-side {
  min-width: 0;
}


.airport-side--right {
  text-align: right;
}


.airport-code-row {
  display: flex;

  align-items: center;

  gap: 6px;
}


.airport-code-row--right {
  justify-content: flex-end;
}


.route-flag {
  width: 17px;
  height: 12px;

  flex: 0 0 auto;

  border-radius: 2px;

  box-shadow:
    0 0 0 1px
    rgba(0, 0, 0, 0.08);
}


.iata-code {
  color: #0b2d5c;

  font-size: 17px;
  font-weight: 800;

  line-height: 1;
}


.city-name {
  margin-top: 4px;

  overflow: hidden;

  color: #222;

  font-size: 12px;
  font-weight: 700;

  line-height: 1.2;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.airport-name {
  margin-top: 2px;

  overflow: hidden;

  color: #777;

  font-size: 10px;

  line-height: 1.2;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.flight-symbol {
  display: grid;

  grid-template-columns:
    1fr auto 1fr;

  align-items: center;

  gap: 4px;

  margin-top: 5px;

  color: #0b2d5c;
}


.flight-symbol__line {
  height: 1px;

  background:
    rgba(11, 45, 92, 0.45);
}


.flight-symbol__plane {
  font-size: 12px;

  line-height: 1;
}


.flight-meta-row {
  display: flex;

  align-items: center;

  justify-content: space-between;

  gap: 10px;

  margin-top: 8px;
  padding-top: 7px;

  border-top:
    1px solid #ececec;
}


.flight-meta-main {
  display: flex;

  flex-wrap: wrap;

  align-items: center;

  min-width: 0;

  gap: 3px;

  color: #555;

  font-size: 10px;
}


.flight-number {
  color: #222;

  font-weight: 700;
}


.meta-separator {
  color: #aaa;
}


.flight-date {
  display: flex;

  align-items: center;

  flex: 0 0 auto;

  gap: 6px;

  color: #666;

  font-size: 10px;

  white-space: nowrap;
}


.planned-badge {
  padding: 2px 5px;

  border-radius: 4px;

  background: #f28c28;

  color: #fff;

  font-size: 8px;
  font-weight: 700;

  text-transform: uppercase;
}


.flight-stats-row {
  display: grid;

  grid-template-columns:
    1fr 1fr 1fr;

  align-items: center;

  gap: 6px;

  margin-top: 6px;
  padding-top: 6px;

  border-top:
    1px solid #f0f0f0;

  color: #666;

  font-size: 10px;
}


.flight-stats-row span:nth-child(2) {
  text-align: center;
}


.flight-stats-row span:nth-child(3) {
  text-align: right;
}


.flight-time {
  color: #444;

  font-weight: 500;
}


:deep(.fi) {
  display: inline-block;
}
</style>