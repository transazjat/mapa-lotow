export type FlightScope =
  | 'completed'
  | 'all'
  | 'planned'


export type SidebarTab =
  | 'map'
  | 'flights'
  | 'statistics'
  | 'account'


export type FlightType =
  | 'domestic'
  | 'continental'
  | 'intercontinental'
  | 'other'


export interface Flight {
  id: number

  departure_date: string | null
  departure_time: string | null

  arrival_date: string | null
  arrival_time: string | null

  flight_number: string | null

  distance_km: number | null
  duration_seconds: number | null

  travel_class: string | null
  seat_type: string | null
  travel_reason: string | null

  departure_airport_id: number
  departure_iata: string | null
  departure_airport_name: string
  departure_city: string

  departure_country:
    string | null

  departure_country_code:
    string | null

  departure_continent_code:
    string | null

  departure_latitude:
    string | number

  departure_longitude:
    string | number

  arrival_airport_id: number
  arrival_iata: string | null
  arrival_airport_name: string
  arrival_city: string

  arrival_country:
    string | null

  arrival_country_code:
    string | null

  arrival_continent_code:
    string | null

  arrival_latitude:
    string | number

  arrival_longitude:
    string | number

  airline_id: number | null
  airline_name: string | null

  aircraft_type_id:
    number | null

  aircraft_name:
    string | null

  flight_type:
    FlightType | null
}


export interface FlightsResponse {
  status: string

  user_id: number

  count: number

  flights: Flight[]
}


export interface AirportData {
  code: string | null

  name: string
  city: string

  longitude: number
  latitude: number

  flights: number
}


export interface AirportDirectionStat {
  code: string | null

  name: string
  city: string

  longitude: number
  latitude: number

  flights: number
}


export interface SelectedAirport {
  code: string | null

  name: string
  city: string

  longitude: number
  latitude: number

  flights: number

  departures: number
  arrivals: number

  topDestinations:
    AirportDirectionStat[]

  topOrigins:
    AirportDirectionStat[]
}


export interface RouteFlight {
  id: number

  departureDate:
    string | null

  departureTime:
    string | null

  arrivalDate:
    string | null

  arrivalTime:
    string | null

  flightNumber:
    string | null

  airlineName:
    string | null

  aircraftName:
    string | null

  distanceKm:
    number | null

  durationSeconds:
    number | null

  travelClass:
    string | null

  seatType:
    string | null

  travelReason:
    string | null
}


export interface SelectedRoute {
  departureCode:
    string | null

  departureName:
    string

  departureCity:
    string

  departureCountryCode?:
    string | null

  departureLongitude:
    number

  departureLatitude:
    number


  arrivalCode:
    string | null

  arrivalName:
    string

  arrivalCity:
    string

  arrivalCountryCode?:
    string | null

  arrivalLongitude:
    number

  arrivalLatitude:
    number


  flights: number

  totalDistanceKm:
    number

  totalDurationSeconds:
    number

  firstFlightDate:
    string | null

  lastFlightDate:
    string | null

  topAirline:
    string | null

  topAircraft:
    string | null

  flightList:
    RouteFlight[]
}


export interface FlightDetails {
  id: number
  user_id: number

  departure_date:
    string | null

  departure_time:
    string | null

  arrival_date:
    string | null

  arrival_time:
    string | null

  flight_number:
    string | null

  distance_km:
    number | null

  duration_seconds:
    number | null

  travel_class:
    string | null

  seat_type:
    string | null

  seat_number:
    string | null

  travel_reason:
    string | null

  aircraft_registration:
    string | null

  notes:
    string | null


  departure_airport_id:
    number

  departure_iata:
    string | null

  departure_icao:
    string | null

  departure_airport_name:
    string

  departure_city:
    string

  departure_country:
    string | null

  departure_country_code?:
    string | null

  departure_continent_code?:
    string | null

  departure_latitude:
    string | number

  departure_longitude:
    string | number

  departure_timezone:
    string | null


  arrival_airport_id:
    number

  arrival_iata:
    string | null

  arrival_icao:
    string | null

  arrival_airport_name:
    string

  arrival_city:
    string

  arrival_country:
    string | null

  arrival_country_code?:
    string | null

  arrival_continent_code?:
    string | null

  arrival_latitude:
    string | number

  arrival_longitude:
    string | number

  arrival_timezone:
    string | null


  airline_id:
    number | null

  airline_name:
    string | null

  airline_iata:
    string | null

  airline_icao:
    string | null


  aircraft_type_id:
    number | null

  aircraft_name:
    string | null

  aircraft_family:
    string | null

  aircraft_manufacturer:
    string | null

  aircraft_model:
    string | null

  aircraft_variant:
    string | null

  flight_type?:
    FlightType | null
}


export interface FlightDetailsResponse {
  status: string

  flight: FlightDetails
}