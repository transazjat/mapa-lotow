<script setup lang="ts">
import type {
  AirportDirectionStat,
  SelectedAirport,
} from '../types/flight'


defineProps<{
  airport: SelectedAirport
}>()


const emit = defineEmits<{
  close: []

  destination:
    [airport: AirportDirectionStat]

  origin:
    [airport: AirportDirectionStat]

  destinationDetails:
    [airport: AirportDirectionStat]

  originDetails:
    [airport: AirportDirectionStat]
}>()
</script>


<template>
  <section class="airport-panel">

    <button
      type="button"
      class="airport-panel__close"
      title="Zamknij"
      @click="emit('close')"
    >
      ×
    </button>


    <div class="airport-panel__code">
      {{ airport.code ?? '---' }}
    </div>


    <h2>
      {{ airport.name }}
    </h2>


    <div class="airport-panel__city">
      {{ airport.city }}
    </div>


    <div class="airport-panel__operations">

      <div class="operation-card">
        <strong>
          {{ airport.departures }}
        </strong>

        <span>
          odlotów
        </span>
      </div>


      <div class="operation-card">
        <strong>
          {{ airport.arrivals }}
        </strong>

        <span>
          przylotów
        </span>
      </div>


      <div class="operation-card">
        <strong>
          {{ airport.flights }}
        </strong>

        <span>
          operacji
        </span>
      </div>

    </div>


    <section
      v-if="airport.topDestinations.length"
      class="ranking-section"
    >

      <h3>
        Najczęstsze kierunki
      </h3>


      <div
        v-for="destination in airport.topDestinations"
        :key="`${destination.code}-${destination.name}`"
        class="ranking-row"
      >

        <button
          type="button"
          class="ranking-main"
          @click="emit('destination', destination)"
        >

          <div class="ranking-airport">

            <strong>
              {{ destination.code ?? '---' }}
            </strong>

            <span>
              {{ destination.city }}
            </span>

          </div>


          <div class="ranking-count">
            {{ destination.flights }}
          </div>

        </button>


        <button
          type="button"
          class="ranking-details"
          title="Pokaż szczegóły trasy"
          @click="emit('destinationDetails', destination)"
        >
          Szczegóły
        </button>

      </div>

    </section>


    <section
      v-if="airport.topOrigins.length"
      class="ranking-section"
    >

      <h3>
        Najczęstsze porty wylotu
      </h3>


      <div
        v-for="origin in airport.topOrigins"
        :key="`${origin.code}-${origin.name}`"
        class="ranking-row"
      >

        <button
          type="button"
          class="ranking-main"
          @click="emit('origin', origin)"
        >

          <div class="ranking-airport">

            <strong>
              {{ origin.code ?? '---' }}
            </strong>

            <span>
              {{ origin.city }}
            </span>

          </div>


          <div class="ranking-count">
            {{ origin.flights }}
          </div>

        </button>


        <button
          type="button"
          class="ranking-details"
          title="Pokaż szczegóły trasy"
          @click="emit('originDetails', origin)"
        >
          Szczegóły
        </button>

      </div>

    </section>


    <div class="airport-panel__coordinates">

      {{ airport.latitude.toFixed(4) }},
      {{ airport.longitude.toFixed(4) }}

    </div>

  </section>
</template>


<style scoped>
.airport-panel {
  position: absolute;

  top: 18px;
  right: 18px;

  z-index: 20;

  width: 360px;
  max-height: calc(100vh - 36px);

  overflow-y: auto;

  padding: 22px;

  background:
    rgba(255, 255, 255, 0.96);

  backdrop-filter:
    blur(12px);

  border:
    1px solid rgba(0, 0, 0, 0.08);

  border-radius: 16px;

  box-shadow:
    0 12px 35px
    rgba(0, 0, 0, 0.18);
}


.airport-panel__close {
  position: absolute;

  top: 10px;
  right: 12px;

  width: 32px;
  height: 32px;

  border: 0;
  border-radius: 8px;

  background: transparent;

  cursor: pointer;

  font-size: 24px;
}


.airport-panel__close:hover {
  background:
    rgba(0, 0, 0, 0.06);
}


.airport-panel__code {
  margin-bottom: 4px;

  font-size: 32px;
  font-weight: 800;
}


.airport-panel h2 {
  margin: 0;

  padding-right: 30px;

  font-size: 18px;
  line-height: 1.3;
}


.airport-panel__city {
  margin-top: 4px;

  color: #777;

  font-size: 13px;
}


.airport-panel__operations {
  display: grid;

  grid-template-columns:
    repeat(3, 1fr);

  gap: 8px;

  margin-top: 20px;
}


.operation-card {
  padding: 12px 8px;

  text-align: center;

  background: #f4f4f4;

  border-radius: 10px;
}


.operation-card strong {
  display: block;

  font-size: 22px;
}


.operation-card span {
  display: block;

  margin-top: 3px;

  color: #777;

  font-size: 11px;
}


.ranking-section {
  margin-top: 22px;
}


.ranking-section h3 {
  margin: 0 0 8px;

  font-size: 14px;
}


.ranking-row {
  display: flex;

  align-items: stretch;

  gap: 5px;

  border-bottom:
    1px solid #eee;
}


.ranking-row:last-child {
  border-bottom: 0;
}


.ranking-main {
  display: flex;

  flex: 1;

  align-items: center;

  justify-content: space-between;

  min-width: 0;

  gap: 12px;

  padding: 9px 6px;

  border: 0;

  background: transparent;

  color: inherit;

  text-align: left;

  cursor: pointer;
}


.ranking-main:hover {
  background:
    rgba(214, 40, 40, 0.07);
}


.ranking-airport {
  display: flex;

  align-items: baseline;

  min-width: 0;

  gap: 8px;
}


.ranking-airport strong {
  min-width: 34px;

  font-size: 13px;
}


.ranking-airport span {
  overflow: hidden;

  color: #666;

  font-size: 12px;

  text-overflow: ellipsis;

  white-space: nowrap;
}


.ranking-count {
  flex: 0 0 auto;

  min-width: 24px;

  text-align: right;

  font-size: 13px;
  font-weight: 700;
}


.ranking-details {
  flex: 0 0 auto;

  align-self: center;

  padding: 5px 7px;

  border: 0;

  border-radius: 6px;

  background:
    rgba(11, 45, 92, 0.07);

  color: #0b2d5c;

  cursor: pointer;

  font-size: 9px;
  font-weight: 700;
}


.ranking-details:hover {
  background:
    rgba(11, 45, 92, 0.15);
}


.airport-panel__coordinates {
  margin-top: 18px;

  color: #aaa;

  font-size: 10px;
}


@media (max-width: 700px) {
  .airport-panel {
    top: auto;

    right: 10px;
    bottom: 10px;
    left: 10px;

    width: auto;

    max-height: 55vh;
  }
}
</style>