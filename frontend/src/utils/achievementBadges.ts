import flights25 from '../assets/achievements/flights/flights-25.png'
import flights50 from '../assets/achievements/flights/flights-50.png'
import flights100 from '../assets/achievements/flights/flights-100.png'
import flights200 from '../assets/achievements/flights/flights-200.png'
import flights300 from '../assets/achievements/flights/flights-300.png'
import flights400 from '../assets/achievements/flights/flights-400.png'
import flights500 from '../assets/achievements/flights/flights-500.png'
import flights600 from '../assets/achievements/flights/flights-600.png'
import flights750 from '../assets/achievements/flights/flights-750.png'
import flights1000 from '../assets/achievements/flights/flights-1000.png'

import distance10000 from '../assets/achievements/distance/distance-10000.png'
import distance25000 from '../assets/achievements/distance/distance-25000.png'
import distance50000 from '../assets/achievements/distance/distance-50000.png'
import distance100000 from '../assets/achievements/distance/distance-100000.png'
import distance250000 from '../assets/achievements/distance/distance-250000.png'
import distance500000 from '../assets/achievements/distance/distance-500000.png'
import distance750000 from '../assets/achievements/distance/distance-750000.png'
import distance1000000 from '../assets/achievements/distance/distance-1000000.png'
import distance1500000 from '../assets/achievements/distance/distance-1500000.png'
import distance2000000 from '../assets/achievements/distance/distance-2000000.png'

import airports5 from '../assets/achievements/airports/airports-5.png'
import airports10 from '../assets/achievements/airports/airports-10.png'
import airports25 from '../assets/achievements/airports/airports-25.png'
import airports50 from '../assets/achievements/airports/airports-50.png'
import airports75 from '../assets/achievements/airports/airports-75.png'
import airports100 from '../assets/achievements/airports/airports-100.png'
import airports125 from '../assets/achievements/airports/airports-125.png'
import airports150 from '../assets/achievements/airports/airports-150.png'
import airports200 from '../assets/achievements/airports/airports-200.png'
import airports250 from '../assets/achievements/airports/airports-250.png'

import countries5 from '../assets/achievements/countries/countries-5.png'
import countries10 from '../assets/achievements/countries/countries-10.png'
import countries15 from '../assets/achievements/countries/countries-15.png'
import countries20 from '../assets/achievements/countries/countries-20.png'
import countries25 from '../assets/achievements/countries/countries-25.png'
import countries30 from '../assets/achievements/countries/countries-30.png'
import countries40 from '../assets/achievements/countries/countries-40.png'
import countries50 from '../assets/achievements/countries/countries-50.png'
import countries75 from '../assets/achievements/countries/countries-75.png'
import countries100 from '../assets/achievements/countries/countries-100.png'

import continents1 from '../assets/achievements/continents/continents-1.png'
import continents2 from '../assets/achievements/continents/continents-2.png'
import continents3 from '../assets/achievements/continents/continents-3.png'
import continents4 from '../assets/achievements/continents/continents-4.png'
import continents5 from '../assets/achievements/continents/continents-5.png'
import continents6 from '../assets/achievements/continents/continents-6.png'
import continents7 from '../assets/achievements/continents/continents-7.png'

export const flightBadgeImages: Record<number, string> = {
  25: flights25,
  50: flights50,
  100: flights100,
  200: flights200,
  300: flights300,
  400: flights400,
  500: flights500,
  600: flights600,
  750: flights750,
  1000: flights1000,
}

export const distanceBadgeImages: Record<number, string> = {
  10000: distance10000,
  25000: distance25000,
  50000: distance50000,
  100000: distance100000,
  250000: distance250000,
  500000: distance500000,
  750000: distance750000,
  1000000: distance1000000,
  1500000: distance1500000,
  2000000: distance2000000,
}


export const airportBadgeImages: Record<number, string> = {
  5: airports5,
  10: airports10,
  25: airports25,
  50: airports50,
  75: airports75,
  100: airports100,
  125: airports125,
  150: airports150,
  200: airports200,
  250: airports250,
}


export const countryBadgeImages: Record<number, string> = {
  5: countries5,
  10: countries10,
  15: countries15,
  20: countries20,
  25: countries25,
  30: countries30,
  40: countries40,
  50: countries50,
  75: countries75,
  100: countries100,
}

export const continentBadgeImages: Record<number, string> = {
  1: continents1,
  2: continents2,
  3: continents3,
  4: continents4,
  5: continents5,
  6: continents6,
  7: continents7,
}

export function getFlightBadgeImage(
  threshold: number,
): string | null {
  return flightBadgeImages[threshold] ?? null
}

export function getDistanceBadgeImage(
  threshold: number,
): string | null {
  return distanceBadgeImages[threshold] ?? null
}

export function getAirportBadgeImage(
  threshold: number,
): string | null {
  return airportBadgeImages[threshold] ?? null
}

export function getCountryBadgeImage(
  threshold: number,
): string | null {
  return countryBadgeImages[threshold] ?? null
}

export function getContinentBadgeImage(
  threshold: number,
): string | null {
  return continentBadgeImages[threshold] ?? null
}

export function getAchievementBadgeImage(
  family: 'flights' | 'distance' | 'airports' | 'countries' | 'continents',
  threshold: number,
): string | null {
  if (family === 'distance') return getDistanceBadgeImage(threshold)
  if (family === 'airports') return getAirportBadgeImage(threshold)
  if (family === 'countries') return getCountryBadgeImage(threshold)
  if (family === 'continents') return getContinentBadgeImage(threshold)
  return getFlightBadgeImage(threshold)
}
