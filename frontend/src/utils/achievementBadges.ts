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

export const flightBadgeImages: Record<
  number,
  string
> = {
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

export function getFlightBadgeImage(
  threshold: number,
): string | null {
  return flightBadgeImages[threshold] ?? null
}
