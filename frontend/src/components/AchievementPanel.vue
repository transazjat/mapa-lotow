<script setup lang="ts">
import {
  computed,
  onMounted,
  ref,
} from 'vue'

import { getAchievements } from '../services/achievementsApi'
import type {
  AchievementItem,
  AircraftCollectionAchievementItem,
  AchievementSummary,
  AchievementsResponse,
} from '../types/achievement'
import { downloadAchievementCard } from '../utils/achievementCard'
import { downloadAircraftCollectionCard } from '../utils/aircraftCollectionCard'
import {
  getAirlineBadgeImage,
  getAircraftBadgeImage,
  getAircraftManufacturerBadgeImage,
  getAircraftOriginBadgeImage,
  getAircraftUniqueBadgeImage,
  getAirportBadgeImage,
  getContinentBadgeImage,
  getCountryBadgeImage,
  getDistanceBadgeImage,
  getFlightBadgeImage,
} from '../utils/achievementBadges'

import AchievementSidebar from './achievements/AchievementSidebar.vue'
import AchievementHeader from './achievements/AchievementHeader.vue'
import AchievementSummaryCards from './achievements/AchievementSummaryCards.vue'
import AchievementProgressPanel from './achievements/AchievementProgressPanel.vue'
import AchievementBadgeGrid from './achievements/AchievementBadgeGrid.vue'
import AchievementDetailPanel from './achievements/AchievementDetailPanel.vue'
import AchievementLegend from './achievements/AchievementLegend.vue'
import AchievementFamilyPlaceholder from './achievements/AchievementFamilyPlaceholder.vue'
import AircraftCollectionDetailPanel from './achievements/AircraftCollectionDetailPanel.vue'
import AircraftUniqueDetailPanel from './achievements/AircraftUniqueDetailPanel.vue'

import { achievementFamilies, achievementIcons } from './achievements/achievementUi'
import './achievements/achievementShared.css'

const props = defineProps<{ nick: string }>()
const emit = defineEmits<{ close: [] }>()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<AchievementsResponse | null>(null)
const selectedKey = ref<string | null>(null)
const activeFamily = ref('flights')
const exporting = ref<'png' | 'jpg' | null>(null)
const activeAircraftTab = ref<'collection' | 'manufacturers' | 'origins' | 'special'>('collection')
const selectedManufacturerKey = ref<string | null>(null)
const selectedOriginKey = ref<string | null>(null)
const selectedUniqueKey = ref<string | null>(null)

const flightBadgeNames: Record<number, string> = {
  25: 'Pierwszy rozdział',
  50: 'Na dobre w powietrzu',
  100: 'Setny lot',
  200: 'Stały bywalec',
  300: 'Skrzydła doświadczenia',
  400: 'Wysokie loty',
  500: 'Pół tysiąca',
  600: 'Weteran przestworzy',
  750: 'Trzy czwarte tysiąca',
  1000: 'Tysiąc lotów',
}

const distanceBadgeNames: Record<number, string> = {
  10000: 'Ćwierć świata',
  25000: 'Ponad pół świata',
  50000: 'Więcej niż obwód Ziemi',
  100000: 'Ponad dwa obwody Ziemi',
  250000: 'Ponad sześć razy wokół Ziemi',
  500000: 'Pół miliona kilometrów w powietrzu',
  750000: 'Osiemnaście obwodów Ziemi',
  1000000: 'Milion kilometrów w powietrzu',
  1500000: 'Półtora miliona kilometrów w powietrzu',
  2000000: 'Pięćdziesiąt obwodów Ziemi',
}

const distanceBadgeDescriptions: Record<number, string> = {
  10000: 'Pierwsze 10 000 kilometrów w powietrzu. To już dystans odpowiadający mniej więcej jednej czwartej obwodu Ziemi.',
  25000: 'Łączny dystans przekroczył 25 000 kilometrów. Twoje podróże sięgają już dalej niż połowa obwodu naszej planety.',
  50000: 'Przekroczyłeś 50 000 kilometrów w powietrzu - więcej niż wynosi pełny obwód Ziemi.',
  100000: 'Sto tysięcy kilometrów w powietrzu to już ponad dwa pełne okrążenia naszej planety.',
  250000: 'Ćwierć miliona kilometrów podróży lotniczych. To dystans odpowiadający ponad sześciu podróżom dookoła świata.',
  500000: 'Pół miliona kilometrów przebytych samolotem. Jeden z najważniejszych kamieni milowych całej serii.',
  750000: 'Siedemset pięćdziesiąt tysięcy kilometrów to dystans zbliżony do osiemnastu pełnych okrążeń Ziemi.',
  1000000: 'Milion kilometrów przebytych samolotem. Osiągnięcie, które pokazuje prawdziwie globalną skalę podróży.',
  1500000: 'Półtora miliona kilometrów lotniczych podróży. To poziom zarezerwowany dla wyjątkowo intensywnego podróżowania.',
  2000000: 'Dwa miliony kilometrów w powietrzu - dystans odpowiadający około pięćdziesięciu podróżom dookoła Ziemi.',
}

const airportBadgeNames: Record<number, string> = {
  5: 'Pierwsze punkty na mapie',
  10: 'Coraz więcej kierunków',
  25: 'Sieć połączeń',
  50: 'Pół setki miejsc',
  75: 'Mapa się zapełnia',
  100: 'Sto lotnisk świata',
  125: 'Szeroki zasięg',
  150: 'Między portami świata',
  200: 'Dwieście lotnisk na mapie',
  250: 'Świat pełen punktów',
}

const airportBadgeDescriptions: Record<number, string> = {
  5: 'Pierwsze pięć różnych lotnisk odwiedzonych podczas podróży. Mapa zaczyna wypełniać się własnymi punktami.',
  10: 'Dziesięć różnych lotnisk to już wyraźnie szersza sieć podróży i więcej miejsc, z których zaczyna się lub kończy lot.',
  25: 'Dwadzieścia pięć różnych lotnisk tworzy już własną, rozpoznawalną sieć podróży.',
  50: 'Pięćdziesiąt różnych lotnisk na Twojej mapie. To już solidny zestaw odwiedzonych portów lotniczych.',
  75: 'Siedemdziesiąt pięć różnych lotnisk sprawia, że mapa podróży staje się coraz gęstsza i bardziej różnorodna.',
  100: 'Sto różnych lotnisk to ważny kamień milowy i wyraźny znak naprawdę szerokiego zasięgu podróży.',
  125: 'Sto dwadzieścia pięć różnych lotnisk pokazuje, jak daleko rozrosła się Twoja osobista mapa podróży.',
  150: 'Sto pięćdziesiąt różnych lotnisk oznacza podróże przez wiele regionów, krajów i dużych portów lotniczych.',
  200: 'Dwieście różnych lotnisk to już imponująca, globalna sieć miejsc odwiedzonych drogą lotniczą.',
  250: 'Dwieście pięćdziesiąt różnych lotnisk na mapie. To poziom, na którym mapa podróży staje się prawdziwie światowa.',
}

const countryBadgeNames: Record<number, string> = {
  5: 'Pięć krajów na mapie',
  10: 'Coraz więcej krajów',
  15: 'Szerzej po świecie',
  20: 'Świat nabiera kształtu',
  25: 'Ćwierć setki krajów',
  30: 'W wielu częściach świata',
  40: 'Czterdzieści krajów',
  50: 'Pół setki krajów świata',
  75: 'Wielki zasięg podróży',
  100: 'Świat bez granic',
}

const countryBadgeDescriptions: Record<number, string> = {
  5: 'Pierwsze pięć odwiedzonych państw. To moment, w którym osobista mapa podróży zaczyna wyraźnie wychodzić poza jeden kierunek.',
  10: 'Dziesięć odwiedzonych państw to już wyraźnie szerszy zasięg podróży i coraz bardziej różnorodna mapa doświadczeń.',
  15: 'Piętnaście państw oznacza, że podróże zaczynają obejmować coraz dalsze regiony i kolejne części świata.',
  20: 'Dwadzieścia odwiedzonych państw sprawia, że Twoja mapa staje się coraz pełniejsza i bardziej globalna.',
  25: 'Dwadzieścia pięć państw to ważny kamień milowy i wyraźny znak szerokiego doświadczenia podróżniczego.',
  30: 'Trzydzieści odwiedzonych państw pokazuje coraz większy zasięg i różnorodność podróży.',
  40: 'Czterdzieści państw na mapie to już imponujący zbiór odwiedzonych miejsc i regionów.',
  50: 'Pięćdziesiąt odwiedzonych państw to jeden z najważniejszych kamieni milowych całej serii.',
  75: 'Siedemdziesiąt pięć państw oznacza naprawdę globalny zasięg i podróże przez bardzo wiele regionów świata.',
  100: 'Sto odwiedzonych państw to wyjątkowe osiągnięcie i symbol niezwykle szerokiego doświadczenia podróżniczego.',
}

const continentBadgeNames: Record<number, string> = {
  1: 'Pierwszy kontynent',
  2: 'Dwa kontynenty',
  3: 'Trzy kontynenty',
  4: 'Cztery kontynenty',
  5: 'Pięć kontynentów',
  6: 'Sześć kontynentów',
  7: 'Siedem kontynentów',
}

const continentBadgeDescriptions: Record<number, string> = {
  1: 'Początek kolekcji kontynentów - na odznace wyróżniona jest Europa, czyli naturalny pierwszy krok na mapie podróży większości polskich użytkowników.',
  2: 'Dwa kontynenty na Twojej mapie. Odznaka podkreśla Europę i Azję jako pierwszy wyraźny krok poza jeden region świata.',
  3: 'Trzy kontynenty na mapie podróży - Europa, Azja i Afryka tworzą już bardzo wyraźny międzyregionalny zasięg.',
  4: 'Cztery kontynenty to poziom, na którym do Europy, Azji i Afryki dołącza Ameryka Północna.',
  5: 'Pięć kontynentów na jednej odznace. Do wcześniejszych kierunków dochodzi Ameryka Południowa, a mapa staje się naprawdę globalna.',
  6: 'Sześć kontynentów to niemal pełna mapa świata - odznaka obejmuje już także Australię i Oceanię.',
  7: 'Najwyższa odznaka serii. Na mapie zaznaczone są wszystkie kontynenty, łącznie z Antarktydą.',
}

const airlineBadgeNames: Record<number, string> = {
  5: 'Pierwsze barwy w podróży',
  10: 'Dziesięć linii',
  15: 'Rozwijająca się flota',
  20: 'Coraz więcej skrzydeł',
  30: 'Szeroka kolekcja przewoźników',
  40: 'Doświadczony pasażer',
  50: 'Pół setki linii lotniczych',
  60: 'Wielka kolekcja przewoźników',
  75: 'Elita częstych podróży',
  100: 'Kolekcjoner linii lotniczych',
}

const airlineBadgeDescriptions: Record<number, string> = {
  5: 'Pierwszych pięciu różnych przewoźników na Twojej liście. To początek kolekcji linii lotniczych poznanych podczas podróży.',
  10: 'Dziesięciu różnych przewoźników oznacza już wyraźnie większą różnorodność doświadczeń w powietrzu.',
  15: 'Piętnaście różnych linii lotniczych pokazuje, że Twoje podróże coraz częściej prowadzą przez różne siatki połączeń i regiony świata.',
  20: 'Dwadzieścia różnych przewoźników to już solidna kolekcja doświadczeń z różnymi liniami lotniczymi.',
  30: 'Trzydzieści różnych linii lotniczych oznacza szeroki przekrój przewoźników i coraz większy zasięg podróży.',
  40: 'Czterdziestu różnych przewoźników to poziom, który świadczy o naprawdę bogatym doświadczeniu lotniczym.',
  50: 'Pięćdziesięciu różnych przewoźników to ważny kamień milowy i imponująca różnorodność podróży.',
  60: 'Sześćdziesiąt różnych linii lotniczych tworzy już wyjątkowo rozbudowaną kolekcję przewoźników.',
  75: 'Siedemdziesięciu pięciu różnych przewoźników oznacza niezwykle szerokie doświadczenie zdobyte na wielu trasach i w wielu częściach świata.',
  100: 'Sto różnych linii lotniczych to wyjątkowe osiągnięcie i prawdziwie globalna kolekcja przewoźników.',
}

const aircraftBadgeNames: Record<number, string> = {
  5: 'Początek kolekcji',
  10: 'Dziesięć maszyn',
  15: 'Kolekcja nabiera kształtu',
  20: 'Dwadzieścia typów maszyn',
  25: 'Ćwierć setki w kolekcji',
  30: 'W wielu typach samolotów',
  40: 'Kolekcjoner maszyn',
  50: 'Pół setki typów maszyn',
  75: 'Elita kolekcjonerów',
  100: 'Mistrz kolekcji samolotów',
}

const aircraftBadgeDescriptions: Record<number, string> = {
  5: 'Pierwszych pięć różnych typów samolotów na Twojej liście. To moment, w którym zaczyna tworzyć się własna kolekcja maszyn poznanych w podróży.',
  10: 'Dziesięć różnych typów samolotów to już wyraźna różnorodność konstrukcji i pierwsza solidna kolekcja lotniczych doświadczeń.',
  15: 'Piętnaście różnych typów samolotów pokazuje, że Twoje podróże coraz częściej odbywają się na pokładach różnych konstrukcji.',
  20: 'Dwadzieścia różnych typów samolotów to już szeroki przekrój maszyn spotykanych na różnych trasach i w różnych częściach świata.',
  25: 'Dwadzieścia pięć różnych typów samolotów to ważny kamień milowy i coraz bardziej rozbudowana kolekcja lotnicza.',
  30: 'Trzydzieści różnych typów samolotów oznacza naprawdę duże zróżnicowanie maszyn i doświadczeń zdobytych w powietrzu.',
  40: 'Czterdzieści różnych typów samolotów to poziom, który wyraźnie wyróżnia pasjonata różnorodnych konstrukcji lotniczych.',
  50: 'Pięćdziesiąt różnych typów samolotów to imponująca kolekcja i jeden z najważniejszych progów całej serii.',
  75: 'Siedemdziesiąt pięć różnych typów samolotów oznacza wyjątkowo szerokie doświadczenie i bardzo bogatą kolekcję maszyn.',
  100: 'Sto różnych typów samolotów to niezwykle rzadkie osiągnięcie i prawdziwie wyjątkowa kolekcja konstrukcji lotniczych.',
}

const aircraftManufacturerDescriptions: Record<string, string> = {
  airbus: 'Pierwszy lot samolotem rodziny Airbus. Jedna z najważniejszych europejskich marek lotniczych trafia do Twojej kolekcji.',
  boeing: 'Pierwszy lot Boeingiem. Klasyka światowego lotnictwa pasażerskiego i jedna z najważniejszych rodzin samolotów w historii.',
  embraer: 'Pierwszy lot Embraerem. Brazylijska szkoła konstrukcyjna i jeden z najważniejszych producentów samolotów regionalnych.',
  atr: 'Pierwszy lot samolotem ATR. Charakterystyczna europejska rodzina turbośmigłowych maszyn regionalnych.',
  bombardier: 'Pierwszy lot samolotem Bombardier. Kanadyjska konstrukcja i ważna część historii regionalnego lotnictwa pasażerskiego.',
  'de-havilland-canada': 'Pierwszy lot maszyną De Havilland Canada. Rodzina samolotów znana szczególnie z regionalnych konstrukcji turbośmigłowych.',
  fokker: 'Pierwszy lot Fokkerem. Holenderska marka o bardzo charakterystycznym miejscu w historii europejskiego lotnictwa.',
  'mcdonnell-douglas': 'Pierwszy lot samolotem McDonnell Douglas. Klasyczna amerykańska rodzina konstrukcji pasażerskich.',
  'british-aerospace': 'Pierwszy lot samolotem British Aerospace. Brytyjska szkoła projektowania samolotów pasażerskich trafia do Twojej kolekcji.',
  tupolev: 'Pierwszy lot Tupolevem. Charakterystyczna rosyjska i radziecka szkoła konstrukcyjna trafia do Twojej kolekcji.',
  saab: 'Pierwszy lot Saabem. Szwedzka rodzina regionalnych samolotów pasażerskich wnosi do kolekcji wyraźnie skandynawski akcent.',
  comac: 'Pierwszy lot samolotem COMAC. Chiński producent nowej generacji samolotów pasażerskich trafia do Twojej kolekcji.',
}

const aircraftOriginDescriptions: Record<string, string> = {
  american: 'Pierwszy lot maszyną amerykańskiej szkoły lotniczej. Do kolekcji trafia klasyczny nurt konstrukcyjny rodem ze Stanów Zjednoczonych.',
  european: 'Pierwszy lot maszyną europejskiej szkoły lotniczej. W Twojej kolekcji pojawia się jedna z najważniejszych współczesnych tradycji konstrukcyjnych.',
  french: 'Pierwszy lot maszyną francuskiej szkoły lotniczej. To osobny akcent pochodzenia i ważna część europejskiego lotnictwa.',
  brazilian: 'Pierwszy lot maszyną brazylijskiej szkoły lotniczej. Do kolekcji trafia południowoamerykańska specjalność regionalnych konstrukcji pasażerskich.',
  canadian: 'Pierwszy lot maszyną kanadyjskiej szkoły lotniczej. To kolejny ważny rozdział w osobistej historii poznawania różnych tradycji konstrukcyjnych.',
  british: 'Pierwszy lot maszyną brytyjskiej szkoły lotniczej. Do kolekcji dołącza jeden z najbardziej rozpoznawalnych nurtów lotniczej inżynierii w Europie.',
  dutch: 'Pierwszy lot maszyną holenderskiej szkoły lotniczej. To odznaka przypominająca o ważnym, choć dziś bardziej niszowym, nurcie konstrukcyjnym.',
  russian: 'Pierwszy lot maszyną rosyjskiej szkoły lotniczej. Do kolekcji trafia wschodnioeuropejski nurt konstrukcyjny o bardzo wyraźnym charakterze.',
  soviet: 'Pierwszy lot maszyną radzieckiej szkoły lotniczej. Odblokowujesz odznakę jednego z najbardziej charakterystycznych historycznych nurtów lotniczych.',
  chinese: 'Pierwszy lot maszyną chińskiej szkoły lotniczej. To znak wejścia do kolekcji nowego, dynamicznie rozwijającego się kierunku w lotnictwie pasażerskim.',
  swedish: 'Pierwszy lot maszyną szwedzkiej szkoły lotniczej. Skandynawska precyzja i regionalna specjalizacja trafiają do Twojej kolekcji.',
  czechoslovak: 'Pierwszy lot maszyną czechosłowackiej szkoły lotniczej. To rzadki i bardzo charakterystyczny akcent pochodzenia w kolekcji.',
}

const aircraftUniqueCharacters: Record<string, string> = {
  'airbus-a380': 'Największy seryjny samolot pasażerski, ikona współczesnego lotnictwa.',
  'boeing-747': 'Absolutna klasyka i jedna z największych ikon lotnictwa.',
  'md-11': 'Szerokokadłubowy trójsilnikowiec, dziś bardzo rzadki.',
  'dc-10': 'Historyczny szerokokadłubowy trójsilnikowiec.',
  'airbus-a340': 'Czterosilnikowy Airbus, dziś coraz rzadszy.',
  'boeing-727': 'Klasyczny trójsilnikowiec z silnikami z tyłu kadłuba.',
  'boeing-757': 'Bardzo charakterystyczna i ceniona konstrukcja, stopniowo znikająca z ruchu.',
  'boeing-767': 'Klasyczny szerokokadłubowiec dalekiego zasięgu.',
  'bae-146-avro-rj': 'Niewielki regionalny odrzutowiec z czterema silnikami.',
  'tupolev-tu-154': 'Jedna z ikon radzieckiego lotnictwa pasażerskiego.',
  'ilyushin-il-18': 'Czterosilnikowy turbośmigłowiec, dziś wyjątkowo egzotyczny.',
  'yakovlev-yak-40': 'Niewielki trójsilnikowy regionalny odrzutowiec.',
  'comac-c919': 'Nowa generacja chińskiego lotnictwa pasażerskiego.',
  'comac-c909': 'Rzadszy chiński regionalny odrzutowiec.',
  'dhc-6-twin-otter': 'Ikoniczny STOL i samolot „trudnych miejsc”.',
  'dhc-7-dash-7': 'Czterosilnikowy turbośmigłowy STOL.',
  'let-l-410': 'Charakterystyczna konstrukcja czechosłowacka/czeska.',
  'saab-2000': 'Bardzo szybki i nietypowy turbośmigłowiec regionalny.',
  'cessna-172': 'Klasyk lekkiego lotnictwa i lotów widokowych.',
  'cessna-208': 'Bush flying, wyspy, safari, krótkie i egzotyczne trasy.',
  'cessna-210': 'Mocny i szybki samolot turystyczny klasy general aviation.',
  'pilatus-pc-6': 'Ekstremalne właściwości STOL, góry i trudne lądowiska.',
  'beechcraft-king-air': 'Klasyczny lekki samolot turbośmigłowy wyższej klasy.',
  helicopter: 'Osobna odznaka za pierwszy lot śmigłowcem, niezależnie od konkretnego typu.',
}

const aircraftUniqueHistories: Record<string, string> = {
  'airbus-a380': 'Airbus A380 wykonał pierwszy lot w 2005 roku i wszedł do służby dwa lata później. Dwupokładowy, czterosilnikowy gigant powstał z myślą o najbardziej obciążonych trasach międzykontynentalnych i stał się symbolem epoki bardzo dużych samolotów pasażerskich.',
  'boeing-747': 'Boeing 747 po raz pierwszy wzbił się w powietrze w 1969 roku. Charakterystyczny garb górnego pokładu sprawił, że Jumbo Jet stał się jednym z najbardziej rozpoznawalnych samolotów w historii i przez dekady zmieniał sposób podróżowania na dalekich trasach.',
  'md-11': 'MD-11 był rozwinięciem rodziny DC-10 i wszedł do służby na początku lat 90. Jego znakiem rozpoznawczym są trzy silniki, w tym jeden w podstawie statecznika pionowego. Dziś niemal zniknął z regularnego ruchu pasażerskiego.',
  'dc-10': 'DC-10 zadebiutował na początku lat 70. jako jeden z pierwszych szerokokadłubowych samolotów dalekiego zasięgu. Jego charakterystyczny układ trzech silników uczynił go jednym z symboli międzykontynentalnego lotnictwa tamtej epoki.',
  'airbus-a340': 'Airbus A340 wszedł do służby w 1993 roku i był przez lata podstawowym europejskim samolotem dalekiego zasięgu. Cztery silniki pozwalały mu obsługiwać bardzo długie trasy, zanim na rynku zaczęły dominować oszczędniejsze dwusilnikowe szerokokadłubowce.',
  'boeing-727': 'Boeing 727 pojawił się w latach 60. i szybko stał się jednym z najpopularniejszych samolotów średniego zasięgu. Trzy silniki umieszczone w tylnej części kadłuba i charakterystyczny ogon nadały mu niezwykle rozpoznawalną sylwetkę.',
  'boeing-757': 'Boeing 757 wszedł do służby w 1983 roku. Słynął z bardzo dobrych osiągów, dużego zasięgu i mocnych silników, dzięki czemu przez wiele lat łączył cechy samolotu średniego i dalekiego zasięgu.',
  'boeing-767': 'Boeing 767 zadebiutował na początku lat 80. i odegrał ogromną rolę w rozwoju dwusilnikowych lotów międzykontynentalnych. Przez dekady był jednym z podstawowych samolotów transatlantyckich wielu linii lotniczych.',
  'bae-146-avro-rj': 'BAe 146 powstał w Wielkiej Brytanii i wszedł do służby w latach 80. Jego niezwykłą cechą są cztery niewielkie silniki pod wysoko umieszczonym skrzydłem, dzięki czemu samolot dobrze radził sobie na krótkich i wymagających lotniskach.',
  'tupolev-tu-154': 'Tu-154 wszedł do służby na początku lat 70. i przez dziesięciolecia był podstawowym samolotem średniego zasięgu w ZSRR i wielu państwach bloku wschodniego. Trzy silniki z tyłu kadłuba nadały mu bardzo charakterystyczny wygląd.',
  'ilyushin-il-18': 'Ił-18 powstał pod koniec lat 50. i przez lata był jednym z najważniejszych radzieckich samolotów pasażerskich dalekiego zasięgu. Cztery silniki turbośmigłowe pozwalały mu łączyć duży zasięg z możliwością operowania z mniej rozwiniętych lotnisk.',
  'yakovlev-yak-40': 'Jak-40 został zaprojektowany w latach 60. jako odrzutowiec zdolny obsługiwać krótkie regionalne trasy i słabiej wyposażone lotniska. Trzy silniki i niewielkie rozmiary sprawiają, że pozostaje jedną z najbardziej charakterystycznych konstrukcji swojej epoki.',
  'comac-c919': 'COMAC C919 to współczesny chiński samolot wąskokadłubowy, którego pierwszy lot odbył się w 2017 roku. Jego powstanie oznacza wejście Chin do grupy państw zdolnych produkować nowoczesne samoloty pasażerskie tej klasy.',
  'comac-c909': 'Samolot początkowo znany jako ARJ21 był pierwszym chińskim regionalnym odrzutowcem nowej generacji. Jego rozwój rozpoczął się jeszcze przed programem C919, a konstrukcja stała się ważnym etapem rozwoju współczesnego chińskiego przemysłu lotniczego.',
  'dhc-6-twin-otter': 'Twin Otter wszedł do służby w latach 60. i szybko zdobył sławę dzięki możliwości startu i lądowania na bardzo krótkich pasach. Do dziś jest używany na wyspach, w górach, na lodzie i w miejscach trudno dostępnych.',
  'dhc-7-dash-7': 'Dash 7 powstał w latach 70. jako większy samolot regionalny zdolny operować z krótkich lotnisk. Cztery silniki i konstrukcja zoptymalizowana pod kątem krótkiego startu i lądowania czyniły go wyjątkowym w swojej klasie.',
  'let-l-410': 'L-410 został opracowany w Czechosłowacji pod koniec lat 60. jako mały samolot regionalny do pracy na krótkich i nieutwardzonych lotniskach. Konstrukcja okazała się wyjątkowo trwała i pozostaje w użyciu do dziś.',
  'saab-2000': 'Saab 2000 powstał w latach 90. jako jeden z najszybszych samolotów turbośmigłowych w ruchu pasażerskim. Miał konkurować z regionalnymi odrzutowcami, oferując wysoką prędkość przy niższym zużyciu paliwa.',
  'cessna-172': 'Cessna 172 jest produkowana od połowy lat 50. i stała się jednym z najbardziej rozpowszechnionych lekkich samolotów w historii. Jest używana do szkolenia, turystyki, lotów prywatnych i widokowych na całym świecie.',
  'cessna-208': 'Cessna 208 Caravan powstała w latach 80. jako wytrzymały samolot użytkowy zdolny przewozić pasażerów i ładunek na krótkich trasach. Jest szczególnie ceniona w regionach o słabo rozwiniętej infrastrukturze lotniczej.',
  'cessna-210': 'Cessna 210 Centurion była produkowana przez ponad 25 lat jako szybki, jednosilnikowy samolot turystyczny z chowanym podwoziem. Łączyła osiągi maszyn podróżnych z możliwością operowania z niewielkich lotnisk.',
  'pilatus-pc-6': 'Pilatus PC-6 Porter zasłynął z wyjątkowo krótkiego startu i lądowania oraz zdolności działania w bardzo trudnym terenie. Od końca lat 50. był wykorzystywany w górach, dżungli, na lodowcach i w operacjach specjalistycznych.',
  'beechcraft-king-air': 'Rodzina King Air pojawiła się w latach 60. i stała się jednym z najbardziej udanych typów biznesowych samolotów turbośmigłowych. Łączy komfort, prędkość i możliwość korzystania z mniejszych lotnisk.',
  helicopter: 'Śmigłowiec różni się od samolotu możliwością pionowego startu, zawisu i lądowania bez pasa. Dzięki temu od dziesięcioleci jest wykorzystywany w ratownictwie, transporcie, turystyce, pracach specjalistycznych i lotach widokowych.',
}

const numberFormatter = new Intl.NumberFormat('pl-PL')

function formatContinentLabel(value: number): string {
  const mod10 = value % 10
  const mod100 = value % 100

  const suffix = value === 1
    ? 'kontynent'
    : (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
      ? 'kontynenty'
      : 'kontynentów'

  return `${numberFormatter.format(value)} ${suffix}`
}

function formatAirlineLabel(value: number): string {
  const mod10 = value % 10
  const mod100 = value % 100

  const suffix = value === 1
    ? 'linia lotnicza'
    : (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
      ? 'linie lotnicze'
      : 'linii lotniczych'

  return `${numberFormatter.format(value)} ${suffix}`
}

function formatAircraftLabel(value: number): string {
  const mod10 = value % 10
  const mod100 = value % 100

  const suffix = value === 1
    ? 'typ samolotu'
    : (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
      ? 'typy samolotów'
      : 'typów samolotów'

  return `${numberFormatter.format(value)} ${suffix}`
}

function formatRemainingToNext(value: number | null): string {
  if (value === null) return ''

  if (activeFamily.value === 'aircraft') {
    if (value === 1) return 'Pozostał 1 typ samolotu'

    const mod10 = value % 10
    const mod100 = value % 100
    if (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14)) {
      return `Pozostały ${numberFormatter.format(value)} typy samolotów`
    }

    return `Pozostało ${numberFormatter.format(value)} typów samolotów`
  }

  if (activeFamily.value !== 'airlines') {
    return `Pozostało ${formatThreshold(value)}`
  }

  if (value === 1) return 'Pozostała 1 linia lotnicza'

  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14)) {
    return `Pozostały ${numberFormatter.format(value)} linie lotnicze`
  }

  return `Pozostało ${numberFormatter.format(value)} linii lotniczych`
}

interface FamilyViewState {
  completedValue: number
  achievements: AchievementItem[]
  summary: AchievementSummary
}

const familyState = computed<FamilyViewState | null>(() => {
  const state = data.value
  if (!state) return null

  if (activeFamily.value === 'distance') {
    return {
      completedValue: state.distance.completed_distance_km,
      achievements: state.distance.achievements,
      summary: state.distance.summary,
    }
  }

  if (activeFamily.value === 'airports') {
    return {
      completedValue: state.airports.completed_airports,
      achievements: state.airports.achievements,
      summary: state.airports.summary,
    }
  }

  if (activeFamily.value === 'countries') {
    return {
      completedValue: state.countries.completed_countries,
      achievements: state.countries.achievements,
      summary: state.countries.summary,
    }
  }

  if (activeFamily.value === 'continents') {
    return {
      completedValue: state.continents.completed_continents,
      achievements: state.continents.achievements,
      summary: state.continents.summary,
    }
  }

  if (activeFamily.value === 'airlines') {
    return {
      completedValue: state.airlines.completed_airlines,
      achievements: state.airlines.achievements,
      summary: state.airlines.summary,
    }
  }

  if (activeFamily.value === 'aircraft') {
    return {
      completedValue: state.aircraft.completed_aircraft_types,
      achievements: state.aircraft.achievements,
      summary: state.aircraft.summary,
    }
  }

  if (activeFamily.value === 'flights') {
    return {
      completedValue: state.completed_flights,
      achievements: state.achievements,
      summary: state.summary,
    }
  }

  return null
})

const achievements = computed(() => familyState.value?.achievements ?? [])

const selectedAchievement = computed<AchievementItem | null>(() => {
  const key = selectedKey.value
  const state = familyState.value
  if (!state) return null

  if (key) {
    const selected = state.achievements.find((item) => item.key === key)
    if (selected) return selected
  }

  const lastEarnedKey = state.summary.last_earned?.key
  if (lastEarnedKey) {
    return state.achievements.find((item) => item.key === lastEarnedKey) ?? null
  }

  return state.achievements.find((item) => item.earned) ?? state.achievements[0] ?? null
})

const futureFamily = computed(
  () => achievementFamilies.find((item) => item.key === activeFamily.value) ?? achievementFamilies[0],
)

const progressPercent = computed(() => {
  const state = familyState.value
  if (!state) return 0

  const next = state.summary.next_threshold
  if (!next) return 100

  const previous = [...state.achievements]
    .reverse()
    .find((item) => item.threshold <= state.completedValue)?.threshold ?? 0

  const segment = next - previous
  if (segment <= 0) return 100

  return Math.max(0, Math.min(100, ((state.completedValue - previous) / segment) * 100))
})

const currentThreshold = computed(() => {
  const state = familyState.value
  if (!state) return null

  return [...state.achievements]
    .reverse()
    .find((item) => item.active)?.threshold ?? null
})

const currentFamilyIcon = computed(
  () => achievementFamilies.find((item) => item.key === activeFamily.value)?.icon
    ?? achievementFamilies[0].icon,
)

function formatDate(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  const months = [
    'sty', 'lut', 'mar', 'kwi', 'maj', 'cze',
    'lip', 'sie', 'wrz', 'paź', 'lis', 'gru',
  ]

  return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`
}

function getBadgeName(threshold: number): string {
  if (activeFamily.value === 'distance') {
    return distanceBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} km`
  }
  if (activeFamily.value === 'airports') {
    return airportBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} lotnisk`
  }
  if (activeFamily.value === 'countries') {
    return countryBadgeNames[threshold] ?? `${numberFormatter.format(threshold)} państw`
  }
  if (activeFamily.value === 'continents') {
    return continentBadgeNames[threshold] ?? formatContinentLabel(threshold)
  }
  if (activeFamily.value === 'airlines') {
    return airlineBadgeNames[threshold] ?? formatAirlineLabel(threshold)
  }
  if (activeFamily.value === 'aircraft') {
    return aircraftBadgeNames[threshold] ?? formatAircraftLabel(threshold)
  }
  return flightBadgeNames[threshold] ?? `${threshold} lotów`
}

function getBadgeDescription(threshold: number): string {
  if (activeFamily.value === 'distance') {
    return distanceBadgeDescriptions[threshold] ?? 'Kolejny kamień milowy Twoich podróży lotniczych.'
  }
  if (activeFamily.value === 'airports') {
    return airportBadgeDescriptions[threshold] ?? 'Kolejny punkt na Twojej osobistej mapie podróży.'
  }
  if (activeFamily.value === 'countries') {
    return countryBadgeDescriptions[threshold] ?? 'Kolejny kraj na Twojej osobistej mapie świata.'
  }
  if (activeFamily.value === 'continents') {
    return continentBadgeDescriptions[threshold] ?? 'Kolejny kontynent na Twojej osobistej mapie świata.'
  }
  if (activeFamily.value === 'airlines') {
    return airlineBadgeDescriptions[threshold] ?? 'Kolejny przewoźnik w Twojej kolekcji linii lotniczych.'
  }
  if (activeFamily.value === 'aircraft') {
    return aircraftBadgeDescriptions[threshold] ?? 'Kolejny typ samolotu w Twojej osobistej kolekcji maszyn.'
  }
  return 'Symbol Twojej pasji do podróżowania i odkrywania świata.'
}

function formatThreshold(threshold: number): string {
  if (activeFamily.value === 'distance') return `${numberFormatter.format(threshold)} km`
  if (activeFamily.value === 'airports') return `${numberFormatter.format(threshold)} lotnisk`
  if (activeFamily.value === 'countries') return `${numberFormatter.format(threshold)} państw`
  if (activeFamily.value === 'continents') return formatContinentLabel(threshold)
  if (activeFamily.value === 'airlines') return formatAirlineLabel(threshold)
  if (activeFamily.value === 'aircraft') return formatAircraftLabel(threshold)
  return `${threshold} lotów`
}

function getBadgeImage(threshold: number): string | null {
  if (activeFamily.value === 'distance') return getDistanceBadgeImage(threshold)
  if (activeFamily.value === 'airports') return getAirportBadgeImage(threshold)
  if (activeFamily.value === 'countries') return getCountryBadgeImage(threshold)
  if (activeFamily.value === 'continents') return getContinentBadgeImage(threshold)
  if (activeFamily.value === 'airlines') return getAirlineBadgeImage(threshold)
  if (activeFamily.value === 'aircraft') return getAircraftBadgeImage(threshold)
  return getFlightBadgeImage(threshold)
}

function formatCurrentValue(value: number): string {
  if (activeFamily.value === 'distance') return `${numberFormatter.format(value)} km`
  if (activeFamily.value === 'airports') return `${numberFormatter.format(value)} lotnisk`
  if (activeFamily.value === 'countries') return `${numberFormatter.format(value)} państw`
  if (activeFamily.value === 'continents') return formatContinentLabel(value)
  if (activeFamily.value === 'airlines') return formatAirlineLabel(value)
  if (activeFamily.value === 'aircraft') return formatAircraftLabel(value)
  return `${numberFormatter.format(value)} lotów`
}

function currentValueCaption(): string {
  if (activeFamily.value === 'distance') return 'Twój łączny dystans przebyty w powietrzu'
  if (activeFamily.value === 'airports') return 'Liczba różnych odwiedzonych lotnisk'
  if (activeFamily.value === 'countries') return 'Liczba różnych odwiedzonych państw'
  if (activeFamily.value === 'continents') return 'Liczba odwiedzonych kontynentów'
  if (activeFamily.value === 'airlines') return 'Liczba różnych przewoźników'
  if (activeFamily.value === 'aircraft') return 'Liczba różnych typów samolotów'
  return 'Twoja łączna liczba odbytych lotów'
}

function selectFamily(key: string): void {
  activeFamily.value = key
  selectedKey.value = null
  if (key === 'aircraft') activeAircraftTab.value = 'collection'
}

function selectAchievement(item: AchievementItem): void {
  selectedKey.value = item.key
}

async function exportCard(format: 'png' | 'jpg'): Promise<void> {
  const item = selectedAchievement.value
  const state = familyState.value
  if (!item || !item.earned || !state) return

  exporting.value = format
  try {
    await downloadAchievementCard(item, state.completedValue, props.nick, format)
  } finally {
    exporting.value = null
  }
}

const aircraftManufacturerState = computed(() => data.value?.aircraft_manufacturers ?? null)
const aircraftOriginState = computed(() => data.value?.aircraft_origins ?? null)
const aircraftUniqueState = computed(() => data.value?.aircraft_unique ?? null)

const selectedManufacturer = computed<AircraftCollectionAchievementItem | null>(() => {
  const items = aircraftManufacturerState.value?.achievements ?? []
  if (!items.length) return null
  return items.find((item) => item.key === selectedManufacturerKey.value) ?? items[0]
})

const selectedOrigin = computed<AircraftCollectionAchievementItem | null>(() => {
  const items = aircraftOriginState.value?.achievements ?? []
  if (!items.length) return null
  return items.find((item) => item.key === selectedOriginKey.value) ?? items[0]
})


const selectedUnique = computed<AircraftCollectionAchievementItem | null>(() => {
  const items = aircraftUniqueState.value?.achievements ?? []
  if (!items.length) return null
  return items.find((item) => item.key === selectedUniqueKey.value) ?? items[0]
})

function selectManufacturer(item: AircraftCollectionAchievementItem) {
  selectedManufacturerKey.value = item.key
}

function selectOrigin(item: AircraftCollectionAchievementItem) {
  selectedOriginKey.value = item.key
}

function selectUnique(item: AircraftCollectionAchievementItem) {
  selectedUniqueKey.value = item.key
}

function manufacturerDescription(slug: string): string {
  return aircraftManufacturerDescriptions[slug] ?? 'Pierwszy lot samolotem tego producenta trafia do Twojej kolekcji.'
}

function originDescription(slug: string): string {
  return aircraftOriginDescriptions[slug] ?? 'Pierwszy lot maszyną tej szkoły lotniczej trafia do Twojej kolekcji.'
}

function uniqueCharacter(slug: string): string {
  return aircraftUniqueCharacters[slug] ?? 'Wyjątkowa konstrukcja lotnicza w Twojej kolekcji.'
}

function uniqueHistory(slug: string): string {
  return aircraftUniqueHistories[slug] ?? 'Krótka historia tej maszyny zostanie uzupełniona.'
}

async function exportManufacturerCard(format: 'png' | 'jpg'): Promise<void> {
  const item = selectedManufacturer.value
  if (!item || !item.earned) return

  exporting.value = format
  try {
    await downloadAircraftCollectionCard(
      item,
      'manufacturer',
      props.nick,
      manufacturerDescription(item.slug),
      format,
    )
  } finally {
    exporting.value = null
  }
}

async function exportOriginCard(format: 'png' | 'jpg'): Promise<void> {
  const item = selectedOrigin.value
  if (!item || !item.earned) return

  exporting.value = format
  try {
    await downloadAircraftCollectionCard(
      item,
      'origin',
      props.nick,
      originDescription(item.slug),
      format,
    )
  } finally {
    exporting.value = null
  }
}


async function exportUniqueCard(format: 'png' | 'jpg'): Promise<void> {
  const item = selectedUnique.value
  if (!item || !item.earned) return

  exporting.value = format
  try {
    await downloadAircraftCollectionCard(
      item,
      'unique',
      props.nick,
      uniqueCharacter(item.slug),
      format,
    )
  } finally {
    exporting.value = null
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await getAchievements()
    data.value = response
    selectedKey.value = response.summary.last_earned?.key
      ?? response.achievements.find((item) => item.earned)?.key
      ?? null
    selectedManufacturerKey.value = response.aircraft_manufacturers.achievements[0]?.key ?? null
    selectedOriginKey.value = response.aircraft_origins.achievements[0]?.key ?? null
    selectedUniqueKey.value = response.aircraft_unique.achievements[0]?.key ?? null
  } catch (err) {
    error.value = err instanceof Error
      ? err.message
      : 'Nie udało się pobrać osiągnięć.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="achievements-shell" role="dialog" aria-modal="true" aria-label="Osiągnięcia">
    <section class="achievements-panel">
      <button class="achievements-close" type="button" aria-label="Zamknij osiągnięcia" @click="emit('close')">×</button>

      <AchievementSidebar
        :families="achievementFamilies"
        :active-family="activeFamily"
        @select="selectFamily"
      />

      <main class="achievements-content">
        <AchievementHeader />

        <div v-if="loading" class="achievements-loading">Ładowanie osiągnięć…</div>
        <div v-else-if="error" class="achievements-error">{{ error }}</div>

        <template v-else-if="data && familyState">
          <AchievementSummaryCards
            v-if="activeFamily !== 'aircraft' || activeAircraftTab === 'collection'"
            :earned-value="`${familyState.summary.earned_count} z ${familyState.achievements.length}`"
            :active-value="`${familyState.summary.active_count} z ${familyState.summary.earned_count}`"
            :last-earned-value="familyState.summary.last_earned ? formatThreshold(familyState.summary.last_earned.threshold) : '—'"
            :last-earned-note="formatDate(familyState.summary.last_earned?.earned_at ?? null)"
            :next-threshold-value="familyState.summary.next_threshold ? formatThreshold(familyState.summary.next_threshold) : 'Seria ukończona'"
            :next-threshold-note="formatRemainingToNext(familyState.summary.remaining_to_next)"
          />

          <AchievementSummaryCards
            v-else-if="activeAircraftTab === 'manufacturers' && aircraftManufacturerState"
            :earned-value="`${aircraftManufacturerState.summary.earned_count} z ${aircraftManufacturerState.summary.total_count}`"
            :active-value="`${aircraftManufacturerState.summary.active_count} z ${aircraftManufacturerState.summary.total_count}`"
            :last-earned-value="aircraftManufacturerState.summary.last_earned?.label ?? '—'"
            :last-earned-note="formatDate(aircraftManufacturerState.summary.last_earned?.earned_at ?? null)"
            next-threshold-value="Kolekcja producentów"
            next-threshold-note="Każdy producent jest osobną odznaką"
          />

          <AchievementSummaryCards
            v-else-if="activeAircraftTab === 'origins' && aircraftOriginState"
            :earned-value="`${aircraftOriginState.summary.earned_count} z ${aircraftOriginState.summary.total_count}`"
            :active-value="`${aircraftOriginState.summary.active_count} z ${aircraftOriginState.summary.total_count}`"
            :last-earned-value="aircraftOriginState.summary.last_earned?.label ?? '—'"
            :last-earned-note="formatDate(aircraftOriginState.summary.last_earned?.earned_at ?? null)"
            next-threshold-value="Szkoły lotnicze"
            next-threshold-note="Każda szkoła pochodzenia jest osobną odznaką"
          />


          <AchievementSummaryCards
            v-else-if="activeAircraftTab === 'special' && aircraftUniqueState"
            :earned-value="`${aircraftUniqueState.summary.earned_count} z ${aircraftUniqueState.summary.total_count}`"
            :active-value="`${aircraftUniqueState.summary.active_count} z ${aircraftUniqueState.summary.total_count}`"
            :last-earned-value="aircraftUniqueState.summary.last_earned?.label ?? '—'"
            :last-earned-note="formatDate(aircraftUniqueState.summary.last_earned?.earned_at ?? null)"
            next-threshold-value="Unikalne maszyny"
            next-threshold-note="Każda maszyna jest osobną odznaką"
          />

          <nav v-if="activeFamily === 'aircraft'" class="aircraft-achievement-tabs" aria-label="Kategorie osiągnięć typów samolotów">
            <button
              type="button"
              :class="['aircraft-achievement-tabs__item', { 'is-active': activeAircraftTab === 'collection' }]"
              @click="activeAircraftTab = 'collection'"
            >
              Kolekcja
            </button>
            <button
              type="button"
              :class="['aircraft-achievement-tabs__item', { 'is-active': activeAircraftTab === 'manufacturers' }]"
              @click="activeAircraftTab = 'manufacturers'"
            >
              Producenci
            </button>
            <button
              type="button"
              :class="['aircraft-achievement-tabs__item', { 'is-active': activeAircraftTab === 'origins' }]"
              @click="activeAircraftTab = 'origins'"
            >
              Pochodzenie
            </button>
            <button
              type="button"
              :class="['aircraft-achievement-tabs__item', { 'is-active': activeAircraftTab === 'special' }]"
              @click="activeAircraftTab = 'special'"
            >
              Unikalne maszyny
            </button>
          </nav>

          <template v-if="activeFamily !== 'aircraft' || activeAircraftTab === 'collection'">
            <section class="achievement-workspace">
              <div class="achievement-collection">
                <div class="achievement-family-heading">
                  <div v-if="activeFamily === 'flights'">
                    <h2>Loty</h2>
                    <p>Liczba odbytych lotów. Każdy lot to nowa historia, nowe miejsce i nowe możliwości.</p>
                  </div>
                  <div v-else-if="activeFamily === 'distance'">
                    <h2>Dystans</h2>
                    <p>Łączny dystans przebyty podczas wszystkich Twoich lotów. Każdy kilometr przybliża Cię do kolejnego podróżniczego kamienia milowego.</p>
                  </div>
                  <div v-else-if="activeFamily === 'airports'">
                    <h2>Lotniska</h2>
                    <p>Liczba różnych lotnisk, z których rozpoczynały się lub na których kończyły się Twoje loty. Każdy nowy port to kolejny punkt na osobistej mapie podróży.</p>
                  </div>
                  <div v-else-if="activeFamily === 'countries'">
                    <h2>Państwa</h2>
                    <p>Liczba różnych państw odwiedzonych podczas Twoich podróży lotniczych. Każdy kolejny kraj poszerza Twoją osobistą mapę świata.</p>
                  </div>
                  <div v-else-if="activeFamily === 'continents'">
                    <h2>Kontynenty</h2>
                    <p>Liczba kontynentów obecnych na Twojej mapie lotniczych podróży. Każda kolejna część świata zwiększa globalny zasięg Twoich wypraw.</p>
                  </div>
                  <div v-else-if="activeFamily === 'airlines'">
                    <h2>Linie lotnicze</h2>
                    <p>Liczba różnych przewoźników, z którymi odbywały się Twoje loty. Każda nowa linia to kolejne barwy, samoloty i doświadczenia na Twojej mapie podróży.</p>
                  </div>
                  <div v-else>
                    <h2>Typy samolotów</h2>
                    <p>Liczba różnych typów samolotów, którymi odbywały się Twoje loty. Każda nowa konstrukcja poszerza Twoją osobistą kolekcję maszyn.</p>
                  </div>
                </div>

                <AchievementProgressPanel
                  :current-value="formatCurrentValue(familyState.completedValue)"
                  :current-value-caption="currentValueCaption()"
                  :current-badge-label="currentThreshold ? formatThreshold(currentThreshold) : 'jeszcze żadna'"
                  :next-badge-label="familyState.summary.next_threshold ? formatThreshold(familyState.summary.next_threshold) : 'ukończono serię'"
                  :progress-value-label="familyState.summary.next_threshold ? `${numberFormatter.format(familyState.completedValue)} / ${numberFormatter.format(familyState.summary.next_threshold)}` : numberFormatter.format(familyState.completedValue)"
                  :progress-percent="progressPercent"
                />

                <AchievementBadgeGrid
                  :achievements="achievements"
                  :selected-key="selectedAchievement?.key ?? null"
                  :get-badge-name="getBadgeName"
                  :get-badge-image="getBadgeImage"
                  :format-threshold="formatThreshold"
                  @select="selectAchievement"
                />
              </div>

              <AchievementDetailPanel
                :selected-achievement="selectedAchievement"
                :current-value-label="formatCurrentValue(familyState.completedValue)"
                :current-metric-icon="currentFamilyIcon"
                :detail-description="selectedAchievement ? getBadgeDescription(selectedAchievement.threshold) : ''"
                :exporting="exporting"
                :get-badge-name="getBadgeName"
                :get-badge-image="getBadgeImage"
                :format-threshold="formatThreshold"
                :format-date="formatDate"
                @export="exportCard"
              />
            </section>

            <AchievementLegend />
          </template>

          <section v-else-if="activeAircraftTab === 'manufacturers' && aircraftManufacturerState" class="aircraft-collection-section">
            <div class="achievement-family-heading">
              <div>
                <h2>Producenci</h2>
                <p>Każdy pierwszy lot samolotem nowego producenta odblokowuje osobną odznakę. Nieodkryte pozycje pozostają ukryte pod placeholderem, aby nie zdradzać ich wyglądu.</p>
              </div>
            </div>

            <div class="aircraft-collection-workspace">
              <div class="aircraft-collection-grid">
                <button
                  v-for="item in aircraftManufacturerState.achievements"
                  :key="item.key"
                  type="button"
                  :class="['aircraft-collection-card', item.status, { 'is-selected': selectedManufacturer?.key === item.key }]"
                  @click="selectManufacturer(item)"
                >
                  <img
                    v-if="item.earned"
                    :src="getAircraftManufacturerBadgeImage(item.slug) ?? ''"
                    :alt="`Odznaka ${item.label}`"
                  >
                  <div v-else class="achievement-badge-secret" aria-label="Nieodkryta odznaka">
                    <span class="achievement-badge-secret__medallion" aria-hidden="true"></span>
                    <span class="achievement-lock-icon" v-html="achievementIcons.lock"></span>
                  </div>
                  <strong>{{ item.label }}</strong>
                  <span>{{ item.earned ? 'Zdobyta' : 'Nieodblokowana' }}</span>
                </button>
              </div>

              <AircraftCollectionDetailPanel
                :selected-item="selectedManufacturer"
                :description="selectedManufacturer ? manufacturerDescription(selectedManufacturer.slug) : ''"
                :exporting="exporting"
                :badge-image="selectedManufacturer ? getAircraftManufacturerBadgeImage(selectedManufacturer.slug) : null"
                kind="manufacturer"
                :format-date="formatDate"
                @export="exportManufacturerCard"
              />
            </div>
          </section>

          <section v-else-if="activeAircraftTab === 'origins' && aircraftOriginState" class="aircraft-collection-section">
            <div class="achievement-family-heading">
              <div>
                <h2>Pochodzenie</h2>
                <p>Ten dział gromadzi odznaki szkół lotniczych i pochodzenia maszyn. Dominującym motywem jest flaga, glob lub symbol kraju powiązanego z daną tradycją konstrukcyjną.</p>
              </div>
            </div>

            <div class="aircraft-collection-workspace">
              <div class="aircraft-collection-grid">
                <button
                  v-for="item in aircraftOriginState.achievements"
                  :key="item.key"
                  type="button"
                  :class="['aircraft-collection-card', item.status, { 'is-selected': selectedOrigin?.key === item.key }]"
                  @click="selectOrigin(item)"
                >
                  <img
                    v-if="item.earned"
                    :src="getAircraftOriginBadgeImage(item.slug) ?? ''"
                    :alt="`Odznaka ${item.label}`"
                  >
                  <div v-else class="achievement-badge-secret" aria-label="Nieodkryta odznaka">
                    <span class="achievement-badge-secret__medallion" aria-hidden="true"></span>
                    <span class="achievement-lock-icon" v-html="achievementIcons.lock"></span>
                  </div>
                  <strong>{{ item.label }}</strong>
                  <span>{{ item.earned ? 'Zdobyta' : 'Nieodblokowana' }}</span>
                </button>
              </div>

              <AircraftCollectionDetailPanel
                :selected-item="selectedOrigin"
                :description="selectedOrigin ? originDescription(selectedOrigin.slug) : ''"
                :exporting="exporting"
                :badge-image="selectedOrigin ? getAircraftOriginBadgeImage(selectedOrigin.slug) : null"
                kind="origin"
                :format-date="formatDate"
                @export="exportOriginCard"
              />
            </div>
          </section>

          <section v-else-if="activeAircraftTab === 'special' && aircraftUniqueState" class="aircraft-collection-section aircraft-unique-section">
            <div class="achievement-family-heading">
              <div>
                <h2>Unikalne maszyny</h2>
                <p>Odznaki za loty wyjątkowymi, historycznymi, rzadkimi lub konstrukcyjnie charakterystycznymi maszynami. Każdy typ jest osobnym lotniczym trofeum.</p>
              </div>
            </div>

            <div class="aircraft-collection-workspace">
              <div class="aircraft-collection-grid aircraft-unique-grid">
                <button
                  v-for="item in aircraftUniqueState.achievements"
                  :key="item.key"
                  type="button"
                  :class="['aircraft-collection-card', 'aircraft-unique-card', item.status, { 'is-selected': selectedUnique?.key === item.key }]"
                  @click="selectUnique(item)"
                >
                  <img
                    v-if="item.earned"
                    :src="getAircraftUniqueBadgeImage(item.slug) ?? ''"
                    :alt="`Odznaka ${item.label}`"
                  >
                  <div v-else class="achievement-badge-secret" aria-label="Nieodkryta odznaka">
                    <span class="achievement-badge-secret__medallion" aria-hidden="true"></span>
                    <span class="achievement-lock-icon" v-html="achievementIcons.lock"></span>
                  </div>
                  <strong>{{ item.label }}</strong>
                  <span>{{ item.earned ? 'Zdobyta' : 'Nieodblokowana' }}</span>
                </button>
              </div>

              <AircraftUniqueDetailPanel
                :selected-item="selectedUnique"
                :character="selectedUnique ? uniqueCharacter(selectedUnique.slug) : ''"
                :history="selectedUnique ? uniqueHistory(selectedUnique.slug) : ''"
                :exporting="exporting"
                :badge-image="selectedUnique ? getAircraftUniqueBadgeImage(selectedUnique.slug) : null"
                :format-date="formatDate"
                @export="exportUniqueCard"
              />
            </div>
          </section>
        </template>

        <AchievementFamilyPlaceholder
          v-else
          :label="futureFamily.label"
          :icon="futureFamily.icon"
          @back="activeFamily = 'flights'"
        />
      </main>
    </section>
  </div>
</template>
