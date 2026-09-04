<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import mapaLotowSymbol from '../assets/branding/mapa-lotow-symbol.png'
import heroMapGraphic from '../assets/guest/hero-map.svg'
import worldBackground from '../assets/guest/guest-world-bg.png'
import heroMapRaster from '../assets/guest/hero-map.png'
import heroMyMapRaster from '../assets/guest/hero-my-map.png'
import recordsGraphic from '../assets/guest/records.svg'
import plannedGraphic from '../assets/guest/planned.svg'
import transAzjaLogo from '../assets/branding/transazja-logo.png'
import GuestTripAd from './GuestTripAd.vue'

const emit = defineEmits<{
  login: []
  register: []
}>()

type SlideType = 'map' | 'stats' | 'records' | 'badges' | 'share'

interface HeroSlide {
  key: string
  eyebrow: string
  title: string
  text: string
  type: SlideType
}

const slides: HeroSlide[] = [
  {
    key: 'map',
    eyebrow: 'Mapa świata',
    title: 'Całe Twoje lotnicze życie na jednej mapie',
    text: 'Każdy lot staje się częścią Twojej własnej historii podróży.',
    type: 'map',
  },
  {
    key: 'stats',
    eyebrow: 'Statystyki',
    title: 'Liczby, które naprawdę coś o Tobie mówią',
    text: 'Dystans, czas w powietrzu, lotniska, linie, trasy i więcej.',
    type: 'stats',
  },
  {
    key: 'records',
    eyebrow: 'Rekordy',
    title: 'Odkrywaj własne rekordy',
    text: 'Zobacz, gdzie latałeś najczęściej i które podróże były wyjątkowe.',
    type: 'records',
  },
  {
    key: 'badges',
    eyebrow: 'Osiągnięcia',
    title: 'Każda podróż to kolejny kamień milowy',
    text: 'Zdobywaj osiągnięcia za loty, dystans, kraje, lotniska i wyjątkowe rekordy.',
    type: 'badges',
  },
  {
    key: 'share',
    eyebrow: 'Udostępnianie',
    title: 'Pokaż swoją mapę innym',
    text: 'Udostępniaj mapę, liczby i osiągnięcia - tylko wtedy, kiedy chcesz.',
    type: 'share',
  },
]

const currentSlide = ref(0)
const hovered = ref(false)
let timer: number | null = null

const activeSlide = computed(() => slides[currentSlide.value])

function goTo(index: number): void {
  currentSlide.value = (index + slides.length) % slides.length
}

function next(): void {
  goTo(currentSlide.value + 1)
}

function previous(): void {
  goTo(currentSlide.value - 1)
}

function startTimer(): void {
  stopTimer()
  timer = window.setInterval(() => {
    if (!hovered.value) next()
  }, 6000)
}

function stopTimer(): void {
  if (timer !== null) {
    window.clearInterval(timer)
    timer = null
  }
}

function scrollTo(id: string): void {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

onMounted(startTimer)
onBeforeUnmount(stopTimer)
</script>


<template>
  <div class="guest-home">
    <div
      class="guest-world-background"
      :style="{ backgroundImage: `url(${worldBackground})` }"
      aria-hidden="true"
    ></div>

    <header class="guest-nav">
      <button class="guest-brand" type="button" @click="scrollTo('hero')" aria-label="Mapa Lotów">
        <img :src="mapaLotowSymbol" class="guest-brand__symbol" alt="">
        <span class="brand-board" aria-hidden="true">
          <span class="brand-board__row">
            <b>M</b><b>A</b><b>P</b><b>A</b><b class="brand-board__empty"></b>
          </span>
          <span class="brand-board__row">
            <b>L</b><b>O</b><b>T</b><b>Ó</b><b>W</b>
          </span>
        </span>
      </button>

      <nav class="guest-nav__links">
        <button type="button" @click="scrollTo('features')">Funkcje</button>
        <button type="button" @click="scrollTo('free')">Dlaczego warto</button>
        <button type="button" @click="scrollTo('legacy-privacy-v13')">Prywatność</button>
        <button type="button" @click="scrollTo('legacy-about-v13')">O projekcie</button>
      </nav>

      <div class="guest-nav__actions">
        <button type="button" class="nav-login" @click="emit('login')">Zaloguj się</button>
        <button type="button" class="nav-register" @click="emit('register')">Załóż darmowe konto</button>
      </div>
    </header>

    <main>
      <section id="hero" class="hero">
        <div class="hero__copy">
          <h1>Mapa Lotów</h1>
          <h2>Stwórz własną mapę lotów i podróży</h2>
          <p>
            Zapisuj swoje loty, oglądaj je na mapie świata,
            poznawaj statystyki, rekordy i osiągnięcia.
          </p>

          <div class="hero__actions">
            <button type="button" class="btn btn--primary" @click="emit('register')">
              Załóż darmowe konto
            </button>
            <button type="button" class="btn btn--secondary" @click="emit('login')">
              Zaloguj się
            </button>
          </div>

          <div class="trust-line">
            <span class="trust-item">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3"/><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M12 14v3"/></svg>
              Twoje dane
            </span>
            <span class="trust-item">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v5c0 5.2 3.4 8.4 8 10 4.6-1.6 8-4.8 8-10V6l-8-3Z"/><path d="m9 12 2 2 4-5"/></svg>
              Twój wybór prywatności
            </span>
            <span class="trust-item">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18h10a4 4 0 0 0 .8-7.9A6 6 0 0 0 6.4 8.7 4.5 4.5 0 0 0 7 18Z"/></svg>
              Eksport w każdej chwili
            </span>
          </div>
        </div>

        <section
          class="hero-slider hero-slider--v14"
          @mouseenter="hovered = true"
          @mouseleave="hovered = false"
        >
          <div class="hero-slider__header">
            <div>
              <span class="eyebrow">{{ activeSlide.eyebrow }}</span>
              <h3>{{ activeSlide.title }}</h3>
              <p>{{ activeSlide.text }}</p>
            </div>

            <div class="slider-arrows">
              <button type="button" aria-label="Poprzedni slajd" @click="previous">‹</button>
              <button type="button" aria-label="Następny slajd" @click="next">›</button>
            </div>
          </div>

          <div class="hero-visual hero-visual--v14">
            <div v-if="activeSlide.type === 'map'" class="graphic-frame">
              <img :src="heroMapRaster" alt="Przykładowa mapa lotów">
            </div>

            <div v-else-if="activeSlide.type === 'stats'" class="stats-mock">
              <article><strong>438</strong><span>lotów</span></article>
              <article><strong>1 320 487</strong><span>km</span></article>
              <article><strong>95</strong><span>lotnisk</span></article>
              <article><strong>44</strong><span>państwa</span></article>
              <article><strong>55</strong><span>linii</span></article>
              <article><strong>227</strong><span>tras</span></article>
            </div>

            <div v-else-if="activeSlide.type === 'records'" class="records-mock">
              <article>
                <div class="record-main"><span>Najdłuższy lot</span><strong>10 395 km</strong></div>
                <small>PEK → AMS</small>
              </article>
              <article>
                <div class="record-main"><span>Najczęstsza trasa</span><strong>WAW → FRA</strong></div>
                <small>24 razy</small>
              </article>
              <article>
                <div class="record-main"><span>Najwięcej lotów</span><strong>38</strong></div>
                <small>w jednym roku</small>
              </article>
            </div>

            <div v-else-if="activeSlide.type === 'badges'" class="badges-mock">
              <div class="badge"><strong>100</strong><span>lotów</span></div>
              <div class="badge"><strong>50</strong><span>lotnisk</span></div>
              <div class="badge"><strong>25</strong><span>państw</span></div>
              <div class="badge"><strong>1M</strong><span>km</span></div>
              <div class="badge"><strong>5</strong><span>kontynentów</span></div>
              <div class="badge"><strong>10</strong><span>linii</span></div>
            </div>

            <div v-else class="graphic-frame">
              <img :src="heroMyMapRaster" alt="Przykładowa własna Mapa Lotów">
            </div>
          </div>

          <div class="slider-dots slider-dots--v14" aria-label="Slajdy prezentacji">
            <button
              v-for="(slide, index) in slides"
              :key="slide.key"
              type="button"
              :class="{ active: index === currentSlide }"
              :aria-label="`Pokaż slajd ${index + 1}`"
              @click="goTo(index)"
            ></button>
          </div>
        </section>
      </section>

      <section id="free" class="free-strip">
        <div class="free-strip__copy">
          <h2>Mapa Lotów jest darmowa</h2>
          <p>Pełna funkcjonalność. Bez subskrypcji. Bez płatności.</p>
        </div>

        <div class="free-strip__facts">
          <article>
            <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>
            <strong>0 zł</strong><span>za konto</span>
          </article>
          <article>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 9h16"/><path d="M8 15h3"/></svg>
            <strong>0 zł</strong><span>miesięcznie</span>
          </article>
          <article>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m6 18 12-12"/></svg>
            <strong>Brak</strong><span>subskrypcji</span>
          </article>
          <article>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
            <strong class="free-strip__passion">Wspierana</strong><span>z pasji do podróży</span>
          </article>
        </div>
      </section>

      <section id="features" class="features-section">
        <div class="features-copy">
          <span>Nie tylko zapis lotów</span>
          <h2>Co potrafi Mapa Lotów</h2>
          <p>
            Mapa Lotów zamienia zwykłą historię podróży w coś,
            co można oglądać, analizować i odkrywać na nowo.
          </p>
        </div>

        <div class="feature-grid">
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/></svg>
            <h3>Mapa</h3><p>Wszystkie loty na interaktywnej mapie.</p>
          </article>
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19v-6M12 19V5M19 19V9"/></svg>
            <h3>Statystyki</h3><p>Dystans, czas, kraje, lotniska i więcej.</p>
          </article>
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8v4a4 4 0 0 1-8 0V4Z"/><path d="M8 6H4a4 4 0 0 0 4 4M16 6h4a4 4 0 0 1-4 4M12 12v5M9 21h6M9 17h6"/></svg>
            <h3>Rekordy</h3><p>Najdłuższe loty i najczęstsze trasy.</p>
          </article>
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.2 2.2 3.1-.2.2 3.1L20 10l-1.5 2.8.8 3-3 .8-1.8 2.6-2.5-1.8-2.5 1.8-1.8-2.6-3-.8.8-3L4 10l2.5-1.9.2-3.1 3.1.2L12 3Z"/><path d="m9.5 11 1.7 1.7 3.5-3.5"/></svg>
            <h3>Osiągnięcia</h3><p>Kamienie milowe Twojej podróży.</p>
          </article>
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M5 19h14"/></svg>
            <h3>Eksport</h3><p>CSV, Excel i JSON.</p>
          </article>
          <article>
            <svg class="feature-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.5-4.5M8.2 13.2l7.5 4.5"/></svg>
            <h3>Udostępnianie</h3><p>Prywatny link lub publiczny profil.</p>
          </article>
        </div>
      </section>

      <section class="numbers-strip">
        <div class="numbers-strip__copy">
          <span>Po kilku latach podróży</span>
          <strong>Robi się ciekawie</strong>
          <small>Mapa Lotów liczy wszystko automatycznie.</small>
        </div>

        <div class="numbers-grid">
          <article><strong>438</strong><span>lotów</span></article>
          <article><strong class="distance-number">1 320 487 <em>km</em></strong><span>w powietrzu</span></article>
          <article><strong>95</strong><span>lotnisk</span></article>
          <article><strong>44</strong><span>państwa</span></article>
          <article><strong>227</strong><span>tras</span></article>
        </div>
      </section>

      <!-- SEKCJE DODANE Z mapa_lotow_guest_home_v1_3_patch.zip -->
      <div class="legacy-sections-v13">
      <section class="section split records-section">
        <div class="section-graphic section-graphic--no-hover">
          <img :src="recordsGraphic" alt="Przykładowy panel rekordów Mapy Lotów">
        </div>

        <div class="split__copy">
          <span class="eyebrow">Rekordy</span>
          <h2>Twoje podróże mają własne rekordy</h2>
          <p>Najdłuższy lot, najczęstsza trasa, ulubiona linia, rekordowy miesiąc - Mapa Lotów odnajduje je za Ciebie.</p>
          <ul>
            <li>Najdłuższy lot: 10 395 km</li>
            <li>Najczęstsza trasa: WAW → FRA</li>
            <li>Najwięcej lotów w jednym roku: 38</li>
          </ul>
          <button type="button" class="text-cta" @click="emit('register')">Zacznij budować własną historię →</button>
        </div>
      </section>

      <section id="legacy-privacy-v13" class="section privacy">
        <div class="section-heading section-heading--center">
          <span class="eyebrow">Zaufanie</span>
          <h2>Twoja mapa. Twoje dane. Twoje zasady.</h2>
        </div>

        <div class="privacy-grid">
          <article><h3>Prywatność</h3><p>Mapa może pozostać widoczna wyłącznie dla Ciebie.</p></article>
          <article><h3>Udostępnianie</h3><p>Możesz wygenerować prywatny link albo włączyć publiczny profil.</p></article>
          <article><h3>Eksport</h3><p>W każdej chwili pobierzesz własne dane.</p></article>
        </div>

        <p class="privacy-note">Nie musisz publikować niczego, czego nie chcesz.</p>
      </section>

      <section class="section split planned">
        <div class="split__copy">
          <span class="eyebrow">Planowane loty</span>
          <h2>Mapa pokazuje nie tylko gdzie byłeś, ale także dokąd lecisz</h2>
          <p>Dodaj przyszłe loty i korzystaj z listy planów oraz kalendarza podróży.</p>
        </div>

        <div class="section-graphic section-graphic--no-hover">
          <img :src="plannedGraphic" alt="Kalendarz i lista przyszłych lotów">
        </div>
      </section>

      <section class="section achievements">
        <div class="section-heading section-heading--center">
          <span class="eyebrow">Osiągnięcia</span>
          <h2>Nie tylko liczby. Także osiągnięcia.</h2>
          <p>Każda odznaka dokumentuje kolejny etap Twojej podróżniczej historii.</p>
        </div>

        <div class="achievement-grid">
          <article><strong>100</strong><span>lotów</span></article>
          <article><strong>50</strong><span>lotnisk</span></article>
          <article><strong>25</strong><span>państw</span></article>
          <article><strong>1M</strong><span>kilometrów</span></article>
          <article><strong>10</strong><span>linii</span></article>
          <article><strong>5</strong><span>kontynentów</span></article>
        </div>
      </section>

      <section class="cta">
        <div>
          <span class="eyebrow eyebrow--light">Twoja historia podróży</span>
          <h2>Każdy lot to tylko jeden wpis. Razem tworzą historię.</h2>
          <p>Zacznij zapisywać ją dziś.</p>
        </div>
        <button type="button" class="btn btn--light" @click="emit('register')">Załóż bezpłatne konto</button>
      </section>

      <section id="legacy-about-v13" class="section about">
        <div class="about__intro">
          <div>
            <span class="eyebrow">O projekcie</span>
            <h2>Nowa odsłona Mapy Lotów</h2>
            <p>
              Mapa Lotów to nowa odsłona projektu, który od lat działał w ramach serwisu TransAzji.
              Przebudowaliśmy go od podstaw, zachowując ideę osobistej mapy podróży i rozwijając ją
              o nowoczesne statystyki, rekordy, planowane loty i udostępnianie.
            </p>
          </div>

          <div class="about__brand">
            <img :src="transAzjaLogo" alt="TransAzja" class="about__logo">
            <span>Najlepsze wyprawy do</span>
            <div class="about__trip-links">
              <a href="https://wyprawy.transazja.pl/nepal-wycieczka" target="_blank" rel="noopener noreferrer">Nepal</a>
              <a href="https://wyprawy.transazja.pl/indie-wycieczka" target="_blank" rel="noopener noreferrer">Indie</a>
              <a href="https://wyprawy.transazja.pl/laos-wycieczka" target="_blank" rel="noopener noreferrer">Laos</a>
              <a href="https://wyprawy.transazja.pl/tybet-wycieczka" target="_blank" rel="noopener noreferrer">Tybet</a>
              <a href="https://wyprawy.transazja.pl/kambodza-wycieczka" target="_blank" rel="noopener noreferrer">Kambodża</a>
              <a href="https://wyprawy.transazja.pl/sri-lanka-wycieczka" target="_blank" rel="noopener noreferrer">Sri Lanka</a>
              <a href="https://wyprawy.transazja.pl/oman-wycieczka" target="_blank" rel="noopener noreferrer">Oman</a>
              <a href="https://wyprawy.transazja.pl/birma-wycieczka" target="_blank" rel="noopener noreferrer">Birma</a>
              <a href="https://wyprawy.transazja.pl/bali-wycieczka" target="_blank" rel="noopener noreferrer">Indonezja</a>
              <a href="https://wyprawy.transazja.pl/chiny-wycieczka" target="_blank" rel="noopener noreferrer">Chiny</a>
              <a href="https://wyprawy.transazja.pl/namibia-wycieczka" target="_blank" rel="noopener noreferrer">Namibia</a>
            </div>
          </div>
        </div>

        <GuestTripAd class="about__trip-ad" />
      </section>
      </div>

      <footer class="guest-footer">
        <span>© 2026 Mapa Lotów</span>
        <nav>
          <a href="#">Regulamin</a>
          <a href="#">Polityka Prywatności</a>
          <button type="button" @click="scrollTo('legacy-about-v13')">O projekcie</button>
          <a href="#">Kontakt</a>
        </nav>
      </footer>
    </main>
  </div>
</template>



<style scoped>
:global(*){box-sizing:border-box}
.guest-home{
  position:fixed;inset:0;z-index:70;width:100%;height:100dvh;overflow-x:hidden;overflow-y:auto;
  scroll-behavior:smooth;color:#0a3262;background:#f5f9fc;
  font-family:Inter,"Segoe UI",Arial,sans-serif;font-weight:400;
}
.guest-world-background{
  position:fixed;inset:0;z-index:0;background-size:cover;background-position:center 34%;
  opacity:.44;filter:saturate(.96) contrast(1.02) brightness(1.03);pointer-events:none;
}
.guest-home>main,.guest-home>.guest-nav{position:relative;z-index:1}

.guest-nav{
  position:sticky;top:0;display:grid;grid-template-columns:auto 1fr auto;align-items:center;
  min-height:68px;padding:0 max(6vw,72px);border-bottom:1px solid rgba(10,50,98,.07);
  background:rgba(244,249,253,.72);backdrop-filter:blur(16px) saturate(1.15);
  box-shadow:0 1px 0 rgba(255,255,255,.5);transition:background .2s ease;
}
.guest-brand{display:flex;align-items:center;gap:9px;padding:0;border:0;background:transparent;cursor:pointer}
.guest-brand__symbol{width:45px;height:45px;object-fit:contain;filter:drop-shadow(0 2px 3px rgba(0,0,0,.18))}
.brand-board{display:grid;gap:2px}
.brand-board__row{display:flex;gap:2px}
.brand-board b{
  display:inline-flex;width:20px;height:21px;align-items:center;justify-content:center;border:1px solid #111820;border-radius:2px;
  background:linear-gradient(to bottom,#34383f 0%,#34383f 48%,#20242a 49%,#20242a 100%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.07),0 1px 1px rgba(0,0,0,.14);
  color:#f6f7f8;font-family:"Courier New",ui-monospace,monospace;font-size:14px;font-weight:700;line-height:1;
  text-shadow:0 1px 1px rgba(0,0,0,.7)
}
.brand-board__empty{color:transparent}

.guest-nav__links{display:flex;justify-content:center;gap:32px}
.guest-nav__links button{
  position:relative;padding:8px 2px;border:0;background:transparent;color:#0b3a70;cursor:pointer;
  font-size:13px;font-weight:650;letter-spacing:-.01em;transition:color .18s ease
}
.guest-nav__links button::after{
  content:"";position:absolute;left:50%;right:50%;bottom:3px;height:2px;border-radius:2px;background:#1b78d0;transition:left .18s ease,right .18s ease
}
.guest-nav__links button:hover{color:#07549a}
.guest-nav__links button:hover::after{left:0;right:0}
.guest-nav__actions{display:flex;align-items:center;gap:8px}
.nav-login,.nav-register{
  height:40px;padding:0 17px;border-radius:9px;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;
  transition:transform .18s ease,box-shadow .18s ease,background .18s ease
}
.nav-login{border:1px solid rgba(35,115,190,.48);background:rgba(255,255,255,.68);color:#0b3c73;box-shadow:inset 0 0 0 1px rgba(255,255,255,.5)}
.nav-register{border:1px solid #0b3b70;background:#0b3b70;color:#fff;box-shadow:0 7px 16px rgba(11,59,112,.14)}
.nav-login:hover,.nav-register:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(11,59,112,.16)}

.hero{
  display:grid;grid-template-columns:minmax(0,.92fr) minmax(590px,1.08fr);gap:52px;align-items:center;
  min-height:455px;padding:54px max(6vw,72px) 46px;
  background:linear-gradient(90deg,rgba(248,252,255,.86) 0%,rgba(248,252,255,.58) 40%,rgba(248,252,255,.14) 70%,rgba(248,252,255,.03) 100%)
}
.hero__copy{padding-top:2px}
.hero__copy h1{
  margin:0;color:#0a3568;font-size:clamp(58px,5.2vw,82px);font-weight:760;line-height:.95;letter-spacing:-.048em;
  text-shadow:0 1px 0 rgba(255,255,255,.55)
}
.hero__copy h2{margin:12px 0 12px;color:#0b3567;font-size:clamp(26px,2.55vw,38px);font-weight:760;line-height:1.08;letter-spacing:-.025em}
.hero__copy p{max-width:570px;margin:0;color:#48627e;font-size:17px;font-weight:400;line-height:1.55}
.hero__actions{display:flex;gap:12px;margin-top:23px}
.btn{height:47px;padding:0 20px;border-radius:9px;cursor:pointer;font-family:inherit;font-size:14px;font-weight:750;letter-spacing:-.01em;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
.btn--primary{border:1px solid #0b3d74;background:#0b3d74;color:#fff;box-shadow:0 8px 18px rgba(11,61,116,.15)}
.btn--secondary{border:1px solid #1a77d1;background:rgba(255,255,255,.78);color:#0b3d74}
.btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(11,61,116,.17)}

.trust-line{display:flex;flex-wrap:wrap;gap:20px 27px;margin-top:25px;color:#0b3c73;font-size:13px;font-weight:500}
.trust-item{display:inline-flex;align-items:center;gap:8px;white-space:nowrap}
.trust-item svg{width:27px;height:27px;fill:none;stroke:#0a4a88;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}

.hero-slider{
  position:relative;min-height:390px;overflow:hidden;padding:12px;border:4px solid rgba(255,255,255,.88);border-radius:20px;
  background:rgba(242,248,252,.5);box-shadow:0 18px 42px rgba(15,55,90,.18),inset 0 0 0 1px rgba(15,55,90,.08)
}
.hero-slider__overlay{
  position:absolute;left:25px;top:24px;z-index:3;max-width:52%;padding:13px 15px 12px;border-radius:12px;
  background:rgba(255,255,255,.91);box-shadow:0 7px 20px rgba(25,50,80,.1);backdrop-filter:blur(7px)
}
.eyebrow{color:#6d8197;font-size:10px;font-weight:800;letter-spacing:.055em;text-transform:uppercase}
.hero-slider__overlay h3{margin:5px 0 4px;color:#0b3567;font-size:20px;font-weight:780;line-height:1.05;letter-spacing:-.02em}
.hero-slider__overlay p{margin:0;color:#6a7c8d;font-size:9.5px;line-height:1.35}
.hero-visual{height:366px}
.graphic-frame,.stats-mock,.records-mock,.badges-mock,.free-slide{height:100%;overflow:hidden;border-radius:14px}
.graphic-frame img{display:block;width:100%;height:100%;object-fit:cover}
.stats-mock{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;place-content:center;padding:40px;background:rgba(236,243,248,.88)}
.stats-mock article,.records-mock article{background:#fff;border:1px solid #d8e2eb;box-shadow:0 7px 16px rgba(25,55,85,.06)}
.stats-mock article{padding:16px;border-radius:11px;text-align:center}
.stats-mock strong{display:block;color:#0b3567;font-size:24px;font-weight:800;line-height:1}.stats-mock span{display:block;margin-top:5px;color:#7d8c9a;font-size:9.5px}
.records-mock{display:grid;gap:9px;align-content:center;padding:39px;background:rgba(236,243,248,.88)}
.records-mock article{padding:13px 17px;border-radius:11px}.record-main{display:flex;justify-content:space-between;align-items:baseline;gap:18px}.record-main span,.record-main strong{color:#0b3567;font-size:15px;font-weight:760}.record-main strong{font-size:17px}.records-mock small{display:block;margin-top:5px;color:#7b8997;font-size:9.5px;text-align:center}
.badges-mock{display:grid;grid-template-columns:repeat(3,104px);place-content:center;gap:14px;background:rgba(236,243,248,.88)}
.badge{display:grid;width:104px;height:104px;place-content:center;border:1px solid #d6e0e8;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff,#edf2f6 72%);text-align:center;box-shadow:0 6px 15px rgba(25,55,85,.06)}
.badge strong{color:#0b3567;font-size:25px;font-weight:800}.badge span{margin-top:2px;color:#7c8997;font-size:9.5px}
.free-slide{display:grid;grid-template-columns:142px 1fr;gap:24px;align-items:center;padding:28px;background:linear-gradient(145deg,#0b3d75,#11558f);color:#fff}
.free-slide__zero{display:grid;aspect-ratio:1;place-items:center;border:1px solid rgba(255,255,255,.2);border-radius:50%;font-size:43px;font-weight:800;background:rgba(255,255,255,.04)}
.free-slide__points{display:grid;gap:9px}.free-slide__points span{padding:9px 11px;border:1px solid rgba(255,255,255,.1);border-radius:8px;background:rgba(255,255,255,.06);font-size:13px;font-weight:650}
.hero-arrow{position:absolute;top:50%;z-index:4;display:grid;width:34px;height:34px;place-items:center;border:0;border-radius:50%;background:rgba(255,255,255,.91);color:#0b3d74;cursor:pointer;font-size:20px;box-shadow:0 4px 12px rgba(20,50,80,.1);transform:translateY(-50%)}
.hero-arrow--left{left:11px}.hero-arrow--right{right:11px}
.slider-dots{position:absolute;left:50%;bottom:7px;z-index:4;display:flex;gap:5px;padding:6px 13px;border-radius:999px;background:rgba(255,255,255,.91);box-shadow:0 3px 8px rgba(20,50,80,.08);transform:translateX(-50%)}
.slider-dots button{width:7px;height:7px;padding:0;border:0;border-radius:999px;background:#c9d3dc;cursor:pointer}.slider-dots button.active{width:21px;background:#0c437c}

.free-strip{
  display:grid;grid-template-columns:minmax(0,1.05fr) minmax(560px,1.25fr);gap:24px;align-items:center;
  padding:22px max(6vw,72px);background:linear-gradient(104deg,#0a3e73 0%,#0c4e88 56%,#0a3e73 100%);color:#fff;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05),inset 0 -1px 0 rgba(0,0,0,.08)
}
.free-strip__kicker{display:inline-block;padding:4px 9px;border-radius:7px;background:rgba(255,255,255,.08);font-size:9.5px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}
.free-strip__copy h2{margin:7px 0 3px;font-size:29px;font-weight:760;line-height:1}.free-strip__copy p{margin:0;color:rgba(255,255,255,.84);font-size:14px}
.free-strip__facts{display:grid;grid-template-columns:repeat(4,1fr)}
.free-strip__facts article{display:grid;place-items:center;padding:2px 14px 0;border-left:1px solid rgba(255,255,255,.18);text-align:center}
.free-strip__facts svg{width:31px;height:31px;margin-bottom:5px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.free-strip__facts strong{display:block;font-size:20px;font-weight:600;line-height:1.05}.free-strip__passion{font-size:14px!important;font-weight:650!important}.free-strip__facts span{display:block;margin-top:3px;color:rgba(255,255,255,.85);font-size:10.5px}

.features-section{
  display:grid;grid-template-columns:minmax(0,.73fr) minmax(690px,1.27fr);gap:32px;align-items:center;
  padding:24px max(6vw,72px) 20px;background:rgba(249,252,254,.88);backdrop-filter:blur(2px)
}
.features-copy>span{color:#657b90;font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.features-copy h2{margin:5px 0 7px;color:#0b3567;font-size:30px;font-weight:760;letter-spacing:-.025em}
.features-copy p{max-width:390px;margin:0;color:#64788c;font-size:12px;line-height:1.45}
.feature-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:9px}
.feature-grid article{
  min-height:118px;padding:13px 9px;border:1px solid #d7e1e9;border-radius:11px;background:rgba(255,255,255,.91);
  text-align:center;box-shadow:0 4px 12px rgba(30,65,95,.035);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease
}
.feature-grid article:hover{transform:translateY(-4px);border-color:#c8d6e2;box-shadow:0 11px 22px rgba(25,55,85,.1)}
.feature-svg{width:31px;height:31px;fill:none;stroke:#1487f3;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.feature-grid h3{margin:7px 0 5px;color:#0b3567;font-size:12px;font-weight:760}.feature-grid p{margin:0;color:#748596;font-size:10.2px;line-height:1.35}

.numbers-strip{
  display:grid;grid-template-columns:315px 1fr;gap:16px;margin:18px max(6vw,72px) 18px;padding:13px 16px;
  border-radius:11px;background:linear-gradient(100deg,#0b3e73,#0d4c84);color:#fff;box-shadow:0 8px 18px rgba(20,55,88,.1)
}
.numbers-strip__copy{display:grid;align-content:center;padding-left:2px}.numbers-strip__copy span{font-size:9.5px;letter-spacing:.05em;text-transform:uppercase}.numbers-strip__copy strong{font-size:16px;font-weight:760;text-transform:uppercase}.numbers-strip__copy small{margin-top:4px;color:rgba(255,255,255,.78);font-size:9.8px}
.numbers-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:7px}.numbers-grid article{padding:10px 7px;border-radius:9px;background:rgba(255,255,255,.055);text-align:center}
.numbers-grid strong{display:block;font-size:23px;font-weight:800;line-height:1;white-space:nowrap}.distance-number em{font-style:normal;font-size:.67em;font-weight:800}.numbers-grid span{display:block;margin-top:4px;color:rgba(255,255,255,.84);font-size:9.2px}

.privacy-cta{
  display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:16px;margin:0 max(6vw,72px) 20px;padding:15px 20px;
  border:1px solid #d6e1ea;border-radius:11px;background:rgba(255,255,255,.77);box-shadow:0 5px 15px rgba(30,65,95,.035)
}
.privacy-cta__icon{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;background:#0b4d88;color:#fff}
.privacy-cta__icon svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.privacy-cta div:nth-child(2){display:grid}.privacy-cta strong{color:#0b3567;font-size:13.5px;font-weight:750}.privacy-cta span{margin-top:3px;color:#6a7c8d;font-size:10px}.privacy-cta__button{height:42px;min-width:205px}.privacy-cta__button span{margin-left:9px;color:#fff;font-size:15px}

.compact-about{display:flex;justify-content:center;gap:8px;padding:0 max(6vw,72px) 18px;color:#7c8996;font-size:9.5px}.compact-about strong{color:#63778b;font-weight:750}

@media(max-width:1180px){
  .guest-nav{padding:0 28px}.hero,.free-strip,.features-section{padding-right:28px;padding-left:28px}
  .hero{grid-template-columns:1fr}.hero__copy{max-width:800px}.guest-nav__links{gap:18px}
  .features-section{grid-template-columns:1fr}.feature-grid{grid-template-columns:repeat(3,1fr)}
  .free-strip{grid-template-columns:1fr}.numbers-strip,.privacy-cta{margin-right:28px;margin-left:28px}
}
@media(max-width:720px){
  .guest-nav{grid-template-columns:1fr auto;padding:0 14px}.guest-nav__links{display:none}.guest-brand__symbol{width:40px;height:40px}
  .brand-board b{width:18px;height:19px;font-size:12px}.nav-login{display:none}
  .hero{padding:30px 18px 24px}.hero__copy h1{font-size:54px}.hero__copy h2{font-size:27px}.hero__copy p{font-size:15px}
  .hero-slider{min-height:300px}.hero-visual{height:280px}.hero-slider__overlay{max-width:72%}
  .free-strip{padding:24px 18px}.free-strip__facts{grid-template-columns:repeat(2,1fr);gap:14px}.free-strip__facts article:nth-child(odd){border-left:0}
  .features-section{padding:24px 18px 18px}.feature-grid{grid-template-columns:repeat(2,1fr)}
  .numbers-strip{grid-template-columns:1fr;margin:0 18px 16px}.numbers-grid{grid-template-columns:repeat(2,1fr)}
  .privacy-cta{grid-template-columns:auto 1fr;margin:0 18px 16px}.privacy-cta__button{grid-column:1/3;width:100%}
  .compact-about{padding:0 18px 14px;text-align:center}
}

/* Oryginalne style sekcji z Guest Home V1.3, ograniczone do dodanego bloku. */

.legacy-sections-v13 .guest-home{position:fixed;inset:0;z-index:70;width:100%;height:100dvh;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;touch-action:pan-y;-webkit-overflow-scrolling:touch;scroll-behavior:smooth;background:#f7f9fb;color:#1d2d3f;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}


.legacy-sections-v13 .guest-world-background{position:fixed;inset:0;z-index:0;background-size:cover;background-position:center;background-repeat:no-repeat;opacity:.30;filter:saturate(.82) contrast(.94);pointer-events:none}
.legacy-sections-v13 .guest-home>main, .legacy-sections-v13 .guest-home>.guest-footer, .legacy-sections-v13 .guest-home>.guest-nav{position:relative;z-index:1}
.legacy-sections-v13 .guest-nav{position:sticky;top:0;z-index:60;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:28px;min-height:68px;padding:0 4vw;border-bottom:1px solid rgba(14,45,78,.08);background:rgba(247,249,251,.93);backdrop-filter:blur(14px)}
.legacy-sections-v13 .guest-brand{display:inline-flex;align-items:center;gap:10px;padding:0;border:0;background:transparent;color:#0b2d5c;cursor:pointer}
.legacy-sections-v13 .guest-brand__symbol{display:block;width:42px;height:42px;object-fit:contain}
.legacy-sections-v13 .brand-board{display:grid;gap:2px;text-align:left}
.legacy-sections-v13 .brand-board__row{display:flex;gap:2px}
.legacy-sections-v13 .brand-board b{display:inline-flex;width:21px;height:22px;align-items:center;justify-content:center;border:1px solid #111820;border-radius:2px;background:linear-gradient(to bottom,#30343a 0%,#30343a 48%,#1f2227 49%,#1f2227 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.08);color:#f3f4f6;font-family:"Courier New",ui-monospace,monospace;font-size:15px;font-weight:700;line-height:1;text-align:center;text-shadow:0 1px 1px rgba(0,0,0,.55)}
.legacy-sections-v13 .brand-board__empty{color:transparent}
.legacy-sections-v13 .guest-nav__links{display:flex;justify-content:center;gap:7px}.legacy-sections-v13 .guest-nav__links button, .legacy-sections-v13 .nav-login{padding:8px 10px;border:0;background:transparent;color:#546271;cursor:pointer;font-size:13px;font-weight:650}.legacy-sections-v13 .guest-nav__links button:hover, .legacy-sections-v13 .nav-login:hover{color:#0b2d5c}
.legacy-sections-v13 .guest-nav__actions{display:flex;align-items:center;gap:8px}.legacy-sections-v13 .nav-register{height:36px;padding:0 14px;border:1px solid #0b2d5c;border-radius:9px;background:#0b2d5c;color:#fff;cursor:pointer;font-size:12px;font-weight:760}
.legacy-sections-v13 .hero{display:grid;grid-template-columns:minmax(0,.8fr) minmax(520px,1.2fr);gap:48px;min-height:calc(100vh - 68px);align-items:center;padding:54px 5vw 62px}.legacy-sections-v13 .hero__copy{max-width:570px}.legacy-sections-v13 .eyebrow{color:#687483;font-size:12px;font-weight:820;letter-spacing:.08em;text-transform:uppercase}.legacy-sections-v13 .eyebrow--light{color:rgba(255,255,255,.74)}
.legacy-sections-v13 .hero h1{max-width:620px;margin:12px 0 18px;color:#0b2d5c;font-size:clamp(42px,5vw,70px);line-height:1.02;letter-spacing:-.035em}.legacy-sections-v13 .hero__lead{max-width:590px;margin:0;color:#5c6977;font-size:18px;line-height:1.65}.legacy-sections-v13 .hero__actions{display:flex;gap:10px;margin-top:26px}
.legacy-sections-v13 .btn{height:44px;padding:0 18px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:760}.legacy-sections-v13 .btn--primary{border:1px solid #0b2d5c;background:#0b2d5c;color:#fff;box-shadow:0 7px 18px rgba(11,45,92,.16)}.legacy-sections-v13 .btn--secondary{border:1px solid #d3dbe4;background:#fff;color:#0b2d5c}.legacy-sections-v13 .btn--light{border:1px solid #fff;background:#fff;color:#0b2d5c}
.legacy-sections-v13 .trust-line{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:17px;color:#7b8793;font-size:12px}.legacy-sections-v13 .trust-line span::before{content:"✓";margin-right:5px;color:#55708b}
.legacy-sections-v13 .hero-slider{position:relative;min-height:565px;overflow:hidden;padding:26px;border:1px solid rgba(14,45,78,.1);border-radius:24px;background:linear-gradient(145deg,rgba(255,255,255,.97),rgba(241,245,249,.96));box-shadow:0 25px 70px rgba(19,48,78,.15)}
.legacy-sections-v13 .hero-slider__header{display:grid;grid-template-columns:1fr auto;gap:18px;min-height:120px}.legacy-sections-v13 .hero-slider h2{max-width:620px;margin:8px 0;color:#0b2d5c;font-size:27px;line-height:1.15}.legacy-sections-v13 .hero-slider p{max-width:600px;margin:0;color:#667381;font-size:13px;line-height:1.5}
.legacy-sections-v13 .slider-arrows{display:flex;gap:6px}.legacy-sections-v13 .slider-arrows button{display:grid;width:34px;height:34px;place-items:center;border:1px solid #d7dee6;border-radius:9px;background:#fff;color:#687483;cursor:pointer;font-size:20px}
.legacy-sections-v13 .hero-visual{height:380px;margin-top:14px}.legacy-sections-v13 .mock-map, .legacy-sections-v13 .stats-mock, .legacy-sections-v13 .records-mock, .legacy-sections-v13 .badges-mock, .legacy-sections-v13 .share-mock{height:100%}
.legacy-sections-v13 .mock-map{position:relative;overflow:hidden;border-radius:18px;background:radial-gradient(circle at 23% 47%,rgba(18,58,94,.18),transparent 8%),radial-gradient(circle at 44% 35%,rgba(18,58,94,.15),transparent 10%),radial-gradient(circle at 70% 52%,rgba(18,58,94,.14),transparent 12%),linear-gradient(160deg,#dbe6ef,#eef3f7 38%,#d8e2ea)}
.legacy-sections-v13 .mock-map::before{content:"";position:absolute;inset:18% 8%;opacity:.75;background:radial-gradient(ellipse at 26% 40%,#b7c9d7 0 15%,transparent 16%),radial-gradient(ellipse at 44% 34%,#b7c9d7 0 13%,transparent 14%),radial-gradient(ellipse at 62% 46%,#b7c9d7 0 15%,transparent 16%),radial-gradient(ellipse at 78% 61%,#b7c9d7 0 9%,transparent 10%)}
.legacy-sections-v13 .route{position:absolute;height:2px;transform-origin:left center;border-radius:999px;background:linear-gradient(90deg,rgba(214,40,40,.75),rgba(124,58,237,.75))}.legacy-sections-v13 .route--1{left:23%;top:47%;width:46%;transform:rotate(-8deg)}.legacy-sections-v13 .route--2{left:30%;top:50%;width:34%;transform:rotate(15deg)}.legacy-sections-v13 .route--3{left:42%;top:36%;width:30%;transform:rotate(25deg)}.legacy-sections-v13 .route--4{left:58%;top:50%;width:24%;transform:rotate(18deg)}
.legacy-sections-v13 .airport{position:absolute;z-index:2;display:grid;min-width:34px;height:24px;place-items:center;padding:0 6px;border-radius:7px;background:rgba(255,255,255,.9);color:#0b2d5c;font-size:9px;font-weight:800;box-shadow:0 3px 10px rgba(21,56,90,.12)}.legacy-sections-v13 .airport--1{left:22%;top:43%}.legacy-sections-v13 .airport--2{left:39%;top:32%}.legacy-sections-v13 .airport--3{left:57%;top:50%}.legacy-sections-v13 .airport--4{left:75%;top:55%}.legacy-sections-v13 .airport--5{left:69%;top:68%}
.legacy-sections-v13 .map-chip{position:absolute;right:16px;bottom:14px;padding:8px 10px;border-radius:8px;background:rgba(255,255,255,.94);color:#4f5d6a;font-size:10px;font-weight:720;box-shadow:0 4px 12px rgba(17,54,90,.11)}
.legacy-sections-v13 .graphic-frame, .legacy-sections-v13 .section-graphic{overflow:hidden;border:1px solid #dce3e9;border-radius:18px;background:#fff;box-shadow:0 14px 36px rgba(19,48,78,.10)}
.legacy-sections-v13 .graphic-frame{height:100%}
.legacy-sections-v13 .graphic-frame img, .legacy-sections-v13 .section-graphic img{display:block;width:100%;height:100%;object-fit:cover}
.legacy-sections-v13 .section-graphic{max-height:520px}
.legacy-sections-v13 .stats-mock{display:grid;grid-template-columns:repeat(3,1fr);align-content:center;gap:12px}.legacy-sections-v13 .stats-mock article, .legacy-sections-v13 .records-mock article{padding:22px 16px;border:1px solid #dde4ea;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(19,48,78,.07)}.legacy-sections-v13 .stats-mock strong{display:block;color:#0b2d5c;font-size:28px;line-height:1}.legacy-sections-v13 .stats-mock span{display:block;margin-top:7px;color:#7c8792;font-size:11px}
.legacy-sections-v13 .records-mock{display:grid;align-content:center;gap:12px}.legacy-sections-v13 .records-mock article{display:grid;grid-template-columns:1fr auto;align-items:center;gap:4px 18px}.legacy-sections-v13 .records-mock span, .legacy-sections-v13 .records-mock small{color:#7b8793;font-size:11px}.legacy-sections-v13 .records-mock strong{color:#0b2d5c;font-size:22px}.legacy-sections-v13 .records-mock small{grid-column:1/3}
.legacy-sections-v13 .badges-mock{display:grid;grid-template-columns:repeat(3,132px);place-content:center;justify-content:center;gap:18px 24px}.legacy-sections-v13 .badge{display:grid;width:132px;height:132px;place-content:center;border:1px solid #d9e0e6;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff,#edf2f6 72%);text-align:center;box-shadow:inset 0 0 0 5px rgba(11,45,92,.025),0 8px 20px rgba(19,48,78,.07)}.legacy-sections-v13 .badge strong{color:#0b2d5c;font-size:30px}.legacy-sections-v13 .badge span{margin-top:5px;color:#66727e;font-size:12px;font-weight:650}.legacy-sections-v13 .achievement-grid article{display:grid;aspect-ratio:1;place-content:center;border:1px solid #d9e0e6;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff,#edf2f6 72%);text-align:center;box-shadow:inset 0 0 0 6px rgba(11,45,92,.025),0 8px 20px rgba(19,48,78,.07);transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}.legacy-sections-v13 .achievement-grid article:hover{transform:translateY(-7px) scale(1.035);border-color:#c6d1db;box-shadow:inset 0 0 0 6px rgba(11,45,92,.02),0 18px 34px rgba(19,48,78,.14)}.legacy-sections-v13 .achievement-grid strong{color:#0b2d5c;font-size:24px}.legacy-sections-v13 .achievement-grid span{margin-top:3px;color:#7c8792;font-size:10px}
.legacy-sections-v13 .share-mock{display:grid;place-items:center}.legacy-sections-v13 .share-card{width:min(520px,94%);overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 16px 40px rgba(19,48,78,.16)}.legacy-sections-v13 .share-card__header{padding:16px 18px;background:#0b2d5c;color:#fff;font-weight:780}.legacy-sections-v13 .share-card__map{position:relative;height:190px;background:linear-gradient(160deg,#e3ebf2,#f4f7fa)}.legacy-sections-v13 .share-card__map span{position:absolute;z-index:2;padding:4px 6px;border-radius:5px;background:#fff;color:#0b2d5c;font-size:9px;font-weight:800}.legacy-sections-v13 .share-card__map span:nth-child(1){left:18%;top:46%}.legacy-sections-v13 .share-card__map span:nth-child(2){left:48%;top:52%}.legacy-sections-v13 .share-card__map span:nth-child(3){left:77%;top:41%}.legacy-sections-v13 .share-card__map i{position:absolute;left:20%;top:52%;width:58%;height:2px;transform:rotate(-3deg);background:#d62828}.legacy-sections-v13 .share-card__map i:nth-of-type(2){left:48%;top:53%;width:31%;transform:rotate(-12deg);background:#7c3aed}.legacy-sections-v13 .share-card__stats{display:flex;justify-content:space-between;gap:12px;padding:14px 18px;color:#5c6976;font-size:10px;font-weight:720}

.legacy-sections-v13 .free-slide{display:grid;height:100%;grid-template-columns:minmax(170px,.8fr) minmax(0,1.2fr);align-items:center;gap:34px;padding:28px;border-radius:18px;background:linear-gradient(145deg,#0b2d5c,#153f72);color:#fff}
.legacy-sections-v13 .free-slide__zero{display:grid;aspect-ratio:1;place-items:center;border:1px solid rgba(255,255,255,.24);border-radius:50%;background:rgba(255,255,255,.08);font-size:58px;font-weight:850;box-shadow:inset 0 0 0 8px rgba(255,255,255,.025)}
.legacy-sections-v13 .free-slide__points{display:grid;gap:12px}
.legacy-sections-v13 .free-slide__points span{padding:12px 14px;border:1px solid rgba(255,255,255,.15);border-radius:10px;background:rgba(255,255,255,.07);font-size:15px;font-weight:720}
.legacy-sections-v13 .free-section{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(420px,.9fr) auto;align-items:center;gap:32px;padding:64px 6vw;background:linear-gradient(135deg,#0b2d5c,#153f72);color:#fff}
.legacy-sections-v13 .free-section h2{margin:8px 0;color:#fff;font-size:clamp(32px,4vw,48px);line-height:1.05}
.legacy-sections-v13 .free-section p{max-width:720px;margin:0;color:rgba(255,255,255,.78);font-size:15px;line-height:1.65}
.legacy-sections-v13 .free-section__facts{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}
.legacy-sections-v13 .free-section__facts article{padding:15px 10px;border-radius:12px;background:rgba(255,255,255,.08);text-align:center;transition:transform .22s ease,background .22s ease,box-shadow .22s ease}
.legacy-sections-v13 .free-section__facts article:hover{transform:translateY(-6px) scale(1.025);background:rgba(255,255,255,.12);box-shadow:0 14px 26px rgba(0,0,0,.13)}
.legacy-sections-v13 .free-section__facts strong{display:block;font-size:22px}
.legacy-sections-v13 .free-section__facts span{display:block;margin-top:4px;color:rgba(255,255,255,.72);font-size:10px}
.legacy-sections-v13 .slider-dots{display:flex;justify-content:center;gap:6px;margin-top:14px}.legacy-sections-v13 .slider-dots button{width:8px;height:8px;padding:0;border:0;border-radius:999px;background:#cbd3db;cursor:pointer}.legacy-sections-v13 .slider-dots button.active{width:22px;background:#0b2d5c}
.legacy-sections-v13 .section{padding:86px 6vw;background:rgba(247,249,251,.84);backdrop-filter:blur(1.5px)}.legacy-sections-v13 .section-heading{max-width:720px;margin-bottom:34px}.legacy-sections-v13 .section-heading--center{margin-right:auto;margin-left:auto;text-align:center}.legacy-sections-v13 .section-heading h2, .legacy-sections-v13 .split__copy h2, .legacy-sections-v13 .about h2{margin:8px 0 10px;color:#0b2d5c;font-size:clamp(30px,3.5vw,46px);line-height:1.08;letter-spacing:-.025em}.legacy-sections-v13 .section-heading p, .legacy-sections-v13 .split__copy p, .legacy-sections-v13 .about p{color:#65717e;font-size:15px;line-height:1.7}

.legacy-sections-v13 .feature-grid article, .legacy-sections-v13 .privacy-grid article, .legacy-sections-v13 .numbers-grid article, .legacy-sections-v13 .stats-mock article, .legacy-sections-v13 .records-mock article, .legacy-sections-v13 .section-graphic{transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
.legacy-sections-v13 .feature-grid article:hover, .legacy-sections-v13 .privacy-grid article:hover, .legacy-sections-v13 .numbers-grid article:hover, .legacy-sections-v13 .stats-mock article:hover, .legacy-sections-v13 .records-mock article:hover, .legacy-sections-v13 .section-graphic:hover{transform:translateY(-6px) scale(1.018);box-shadow:0 18px 34px rgba(19,48,78,.13);border-color:#cbd5df}
.legacy-sections-v13 .feature-grid, .legacy-sections-v13 .privacy-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.legacy-sections-v13 .feature-grid article, .legacy-sections-v13 .privacy-grid article{padding:23px;border:1px solid #e0e5ea;border-radius:15px;background:#fff}.legacy-sections-v13 .feature-icon{display:grid;width:36px;height:36px;place-items:center;margin-bottom:14px;border-radius:10px;background:#eef2f6;color:#0b2d5c;font-size:18px;font-weight:800}.legacy-sections-v13 .feature-grid h3, .legacy-sections-v13 .privacy-grid h3{margin:0 0 7px;color:#25384d;font-size:16px}.legacy-sections-v13 .feature-grid p, .legacy-sections-v13 .privacy-grid p{margin:0;color:#6d7884;font-size:13px;line-height:1.55}
.legacy-sections-v13 .numbers{padding:86px 6vw;background:#0b2d5c;color:#fff}.legacy-sections-v13 .numbers .section-heading h2{color:#fff}.legacy-sections-v13 .numbers-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;max-width:1180px;margin:0 auto}.legacy-sections-v13 .numbers-grid article{padding:20px 10px;border-radius:14px;background:rgba(255,255,255,.07);text-align:center}.legacy-sections-v13 .numbers-grid strong{display:block;font-size:clamp(24px,3vw,38px);line-height:1.04;white-space:nowrap}.legacy-sections-v13 .distance-number em{font-style:normal;font-size:.70em;font-weight:800}.legacy-sections-v13 .numbers-grid span{display:block;margin-top:5px;color:rgba(255,255,255,.72);font-size:11px}.legacy-sections-v13 .numbers>p{margin:20px 0 0;text-align:center;color:rgba(255,255,255,.72);font-size:12px}

.legacy-sections-v13 .records-section{max-width:1320px;margin:0 auto;border-radius:24px;background:rgba(255,255,255,.88);box-shadow:0 12px 40px rgba(19,48,78,.06)}
.legacy-sections-v13 .records-section .split__copy{text-align:left}
.legacy-sections-v13 .records-section .split__copy h2, .legacy-sections-v13 .records-section .split__copy p{text-align:left}
.legacy-sections-v13 .records-section .split__copy ul{margin:18px 0;padding-left:20px;text-align:left;list-style-position:outside}
.legacy-sections-v13 .records-section .split__copy li{padding-left:4px;text-align:left}
.legacy-sections-v13 .split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:center;gap:60px}.legacy-sections-v13 .records-panel, .legacy-sections-v13 .planned-mock{overflow:hidden;border:1px solid #dde4ea;border-radius:18px;background:#fff;box-shadow:0 18px 44px rgba(19,48,78,.1)}.legacy-sections-v13 .records-panel header{padding:15px 18px;border-bottom:1px solid #e4e8ec;color:#0b2d5c;font-size:15px;font-weight:800}.legacy-sections-v13 .records-panel>div{display:flex;justify-content:space-between;gap:18px;padding:14px 18px;border-bottom:1px solid #edf0f2;color:#6e7a86;font-size:13px}.legacy-sections-v13 .records-panel strong{color:#26384b}.legacy-sections-v13 .split__copy ul{padding-left:18px;color:#64717e;font-size:13px;line-height:1.9}.legacy-sections-v13 .text-cta{padding:0;border:0;background:transparent;color:#0b2d5c;cursor:pointer;font-size:13px;font-weight:780}
.legacy-sections-v13 .achievements{background:rgba(240,244,247,.90)}.legacy-sections-v13 .achievement-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:18px;max-width:1050px;margin:0 auto}
.legacy-sections-v13 .privacy{background:rgba(255,255,255,.90)}.legacy-sections-v13 .privacy-note{margin:20px 0 0;color:#7a8590;text-align:center;font-size:12px}
.legacy-sections-v13 .planned{background:rgba(238,243,247,.91)}.legacy-sections-v13 .planned-mock__top{padding:10px 14px;border-bottom:1px solid #e4e8ed;color:#0b2d5c;font-size:12px;font-weight:760}.legacy-sections-v13 .planned-mock__body{display:grid;grid-template-columns:.8fr 1.2fr;gap:12px;padding:14px}.legacy-sections-v13 .planned-list{display:grid;align-content:start;gap:8px;padding:12px;border-radius:10px;background:#f6f8fa;color:#62707d;font-size:10px}.legacy-sections-v13 .planned-list strong{color:#0b2d5c}.legacy-sections-v13 .mini-calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}.legacy-sections-v13 .mini-calendar div, .legacy-sections-v13 .mini-calendar span{display:grid;min-height:26px;place-items:center;border-radius:5px;color:#7a8590;font-size:9px}.legacy-sections-v13 .mini-calendar div{font-weight:800}.legacy-sections-v13 .mini-calendar span{background:#f7f9fb}.legacy-sections-v13 .mini-calendar .flight-day{background:rgba(124,58,237,.11);color:#5f42a8;font-weight:800}
.legacy-sections-v13 .cta{display:flex;align-items:center;justify-content:space-between;gap:30px;padding:70px 7vw;background:linear-gradient(135deg,#0b2d5c,#153f72);color:#fff}.legacy-sections-v13 .cta h2{max-width:820px;margin:8px 0 6px;color:#fff;font-size:clamp(30px,4vw,48px);line-height:1.08}.legacy-sections-v13 .cta p{margin:0;color:rgba(255,255,255,.75)}
.legacy-sections-v13 .about{display:grid;gap:32px;background:rgba(255,255,255,.92)}
.legacy-sections-v13 .about__intro{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:54px;align-items:center}
.legacy-sections-v13 .about__brand{display:grid;min-width:260px;gap:7px;padding:24px;border-left:3px solid #f0a127}
.legacy-sections-v13 .about__logo{display:block;width:min(260px,100%);height:auto;object-fit:contain}
.legacy-sections-v13 .about__brand span{color:#77838f;font-size:12px}
.legacy-sections-v13 .destinations{display:grid;gap:12px;padding:20px 0 4px;border-top:1px solid #e3e8ed}
.legacy-sections-v13 .destinations>strong{color:#0b2d5c;font-size:15px}
.legacy-sections-v13 .destinations__links{display:flex;flex-wrap:wrap;gap:7px 13px}
.legacy-sections-v13 .destinations__links a{color:#0b2d5c;font-size:12px;font-weight:800;text-decoration:none}
.legacy-sections-v13 .destinations__links a:hover{text-decoration:underline;text-underline-offset:3px}
.legacy-sections-v13 .about__trip-ad{margin-top:4px}
.legacy-sections-v13 .guest-footer{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:18px;padding:24px 5vw;border-top:1px solid #e3e7eb;background:#f7f9fb;color:#77838f;font-size:11px}.legacy-sections-v13 .guest-footer nav{display:flex;gap:16px}.legacy-sections-v13 .guest-footer a{color:#66727e;text-decoration:none}.legacy-sections-v13 .guest-footer>span:last-child{text-align:right}
.legacy-sections-v13 @media(max-width:980px){.free-section{grid-template-columns:1fr}.free-section__facts{max-width:560px}.about__intro{grid-template-columns:1fr}.guest-nav{grid-template-columns:1fr auto}.guest-nav__links{display:none}.hero{grid-template-columns:1fr;min-height:auto;padding-top:48px}.hero__copy{max-width:none}.hero-slider{min-height:520px}.feature-grid,.privacy-grid{grid-template-columns:repeat(2,1fr)}.numbers-grid{grid-template-columns:repeat(2,1fr)}.split{grid-template-columns:1fr}.achievement-grid{grid-template-columns:repeat(3,1fr)}.about__brand{border-left:0;border-top:3px solid #f0a127}}
.legacy-sections-v13 @media(max-width:640px){.free-section{padding:48px 18px}.free-section__facts{grid-template-columns:1fr}.free-slide{grid-template-columns:1fr;gap:16px}.free-slide__zero{width:130px;margin:0 auto;font-size:42px}.guest-nav{min-height:60px;padding:0 14px}.guest-nav__actions{gap:3px}.nav-login{padding:7px 6px}.hero,.section,.numbers{padding-right:18px;padding-left:18px}.hero h1{font-size:42px}.hero__actions{display:grid;grid-template-columns:1fr}.hero-slider{min-height:500px;padding:18px;border-radius:18px}.hero-slider__header{grid-template-columns:1fr;min-height:150px}.slider-arrows{position:absolute;right:18px;top:18px}.hero-visual{height:300px}.stats-mock{grid-template-columns:repeat(2,1fr)}.badges-mock{grid-template-columns:repeat(2,112px);gap:14px}.badge{width:112px;height:112px}.badge strong{font-size:26px}.badge span{font-size:11px}.feature-grid,.privacy-grid,.numbers-grid,.achievement-grid{grid-template-columns:1fr}.badge,.achievement-grid article{aspect-ratio:auto;min-height:130px;border-radius:22px}.planned-mock__body{grid-template-columns:1fr}.cta{display:grid;padding:54px 18px}.guest-footer{grid-template-columns:1fr;text-align:center}.guest-footer nav{justify-content:center;flex-wrap:wrap}.guest-footer>span:last-child{text-align:center}}


.legacy-sections-v13 .about__intro{
  grid-template-columns:minmax(0,2fr) minmax(260px,1fr);
  gap:48px;
  align-items:center;
}
.legacy-sections-v13 .about__brand{
  display:grid;
  gap:6px;
  min-width:0;
}
.legacy-sections-v13 .about__brand>span{
  color:#77838f;
  font-size:12px;
  font-weight:400;
}
.legacy-sections-v13 .about__trip-links{
  display:flex;
  flex-wrap:wrap;
  gap:4px 10px;
  max-width:310px;
}
.legacy-sections-v13 .about__trip-links a{
  color:#87929e;
  font-size:11px;
  font-weight:500;
  text-decoration:none;
}
.legacy-sections-v13 .about__trip-links a:hover{
  color:#0b2d5c;
}


/* V1.2 - korekty układu i hero */
.guest-home{
  scrollbar-gutter:stable;
  scrollbar-width:auto;
  scrollbar-color:#7f95a8 rgba(235,242,247,.92);
}
.guest-home::-webkit-scrollbar{width:13px}
.guest-home::-webkit-scrollbar-track{background:rgba(235,242,247,.92);border-left:1px solid rgba(11,45,92,.08)}
.guest-home::-webkit-scrollbar-thumb{background:#8ca0b2;border:3px solid rgba(235,242,247,.92);border-radius:999px}
.guest-home::-webkit-scrollbar-thumb:hover{background:#71899d}

/* Tło nieco wyraźniejsze. */
.guest-world-background{opacity:.50}

/* Teksty po lewej hero. */
.hero__copy{text-align:left}
.hero__copy h1,.hero__copy h2,.hero__copy p{margin-left:0;text-align:left}

/* Hero/slider wraca wizualnie do V1.4. */
.hero{min-height:620px;align-items:center;padding-top:58px;padding-bottom:58px}
.hero-slider--v14{
  min-height:565px;
  padding:26px;
  border:1px solid rgba(14,45,78,.10);
  border-radius:24px;
  background:linear-gradient(145deg,rgba(255,255,255,.97),rgba(241,245,249,.96));
  box-shadow:0 25px 70px rgba(19,48,78,.15);
}
.hero-slider--v14 .hero-slider__header{
  display:grid;
  grid-template-columns:1fr auto;
  gap:18px;
  min-height:112px;
}
.hero-slider--v14 .hero-slider__header h3{
  max-width:620px;
  margin:8px 0;
  color:#0b2d5c;
  font-size:27px;
  font-weight:650;
  line-height:1.15;
}
.hero-slider--v14 .hero-slider__header p{
  max-width:600px;
  margin:0;
  color:#667381;
  font-size:13px;
  line-height:1.5;
}
.hero-slider--v14 .slider-arrows{display:flex;gap:6px}
.hero-slider--v14 .slider-arrows button{
  display:grid;
  width:34px;
  height:34px;
  place-items:center;
  border:1px solid #d7dee6;
  border-radius:9px;
  background:#fff;
  color:#687483;
  cursor:pointer;
  font-size:20px;
}
.hero-visual--v14{height:380px;margin-top:14px}
.hero-slider--v14 .graphic-frame{
  height:100%;
  overflow:hidden;
  border:1px solid #dce3e9;
  border-radius:18px;
  background:#fff;
  box-shadow:0 14px 36px rgba(19,48,78,.10);
}
.hero-slider--v14 .graphic-frame img{display:block;width:100%;height:100%;object-fit:cover}
.slider-dots--v14{
  position:static;
  display:flex;
  justify-content:center;
  gap:6px;
  margin-top:14px;
  padding:0;
  background:transparent;
  box-shadow:none;
  transform:none;
}
.slider-dots--v14 button{
  width:8px;height:8px;padding:0;border:0;border-radius:999px;background:#cbd3db;cursor:pointer
}
.slider-dots--v14 button.active{width:22px;background:#0b2d5c}

/* Rekordy: szerokość jak górny pasek "Robi się ciekawie" i równy oddech góra/dół. */
.legacy-sections-v13 .records-section{
  width:auto;
  max-width:none;
  margin:20px max(6vw,72px) 20px;
  padding-top:72px;
  padding-bottom:72px;
  border-radius:24px;
}
.legacy-sections-v13 .records-section + .privacy{
  margin-top:0;
}

/* Brak animacji na grafikach rekordów i kalendarza. */
.legacy-sections-v13 .section-graphic--no-hover,
.legacy-sections-v13 .section-graphic--no-hover:hover{
  transform:none!important;
  transition:none!important;
  border-color:#dce3e9!important;
  box-shadow:0 14px 36px rgba(19,48,78,.10)!important;
}

/* O projekcie - mniejsze logo, teksty i linki przy lewej krawędzi kolumny logo. */
.legacy-sections-v13 .about__brand{
  justify-items:start;
  text-align:left;
  align-content:center;
}
.legacy-sections-v13 .about__logo{
  width:min(205px,100%);
  margin:0;
}
.legacy-sections-v13 .about__brand>span{
  width:100%;
  margin-top:3px;
  text-align:left;
}
.legacy-sections-v13 .about__trip-links{
  justify-content:flex-start;
  align-items:flex-start;
  gap:1px 9px;
  margin-top:1px;
  text-align:left;
  line-height:1.18;
}
.legacy-sections-v13 .about__trip-links a{
  line-height:1.18;
}

/* Stopka. */
.guest-footer{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:24px;
  padding:20px max(6vw,72px);
  border-top:1px solid rgba(11,45,92,.10);
  background:rgba(247,249,251,.92);
  color:#7b8793;
  font-size:11px;
}
.guest-footer nav{display:flex;flex-wrap:wrap;gap:16px}
.guest-footer a,.guest-footer button{
  padding:0;
  border:0;
  background:transparent;
  color:#687483;
  cursor:pointer;
  font:inherit;
  text-decoration:none;
}
.guest-footer a:hover,.guest-footer button:hover{color:#0b2d5c}

@media(max-width:1180px){
  .hero{min-height:auto}
  .legacy-sections-v13 .records-section{margin-left:28px;margin-right:28px}
}
@media(max-width:720px){
  .hero{padding-top:34px;padding-bottom:34px}
  .hero-slider--v14{min-height:500px;padding:18px}
  .hero-slider--v14 .hero-slider__header{grid-template-columns:1fr;min-height:140px}
  .hero-visual--v14{height:300px}
  .legacy-sections-v13 .records-section{margin:18px;padding-top:48px;padding-bottom:48px}
  .guest-footer{display:grid;padding:18px}
}


.legacy-sections-v13 .achievements{
  background:rgba(255,255,255,.86)!important;
}

/* V1.3 - limit szerokości treści na bardzo szerokich monitorach */
.guest-nav{
  padding-left:max(24px,calc((100vw - 1600px)/2 + 48px));
  padding-right:max(24px,calc((100vw - 1600px)/2 + 48px));
}
.hero,
.free-strip,
.features-section{
  padding-left:max(24px,calc((100vw - 1600px)/2 + 48px));
  padding-right:max(24px,calc((100vw - 1600px)/2 + 48px));
}
.numbers-strip,
.privacy-cta{
  margin-left:max(24px,calc((100vw - 1600px)/2 + 48px));
  margin-right:max(24px,calc((100vw - 1600px)/2 + 48px));
}
.legacy-sections-v13 > section,
.legacy-sections-v13 > .section,
.legacy-sections-v13 > .cta,
.legacy-sections-v13 > .about{
  width:min(calc(100% - 96px),1504px);
  margin-left:auto!important;
  margin-right:auto!important;
}
.legacy-sections-v13 .records-section{
  width:min(calc(100% - 96px),1504px);
  margin-left:auto!important;
  margin-right:auto!important;
}
.guest-footer{
  padding-left:max(24px,calc((100vw - 1600px)/2 + 48px));
  padding-right:max(24px,calc((100vw - 1600px)/2 + 48px));
}


/* V1.4 - mocniejsze wyeksponowanie tła strony */
.guest-world-background{
  opacity:1!important;
}

/* Hero - większa ekspozycja grafiki tła zgodnie z ustalonym gradientem. */
.hero{
  background:linear-gradient(
    90deg,
    rgba(248,252,255,.86) 0%,
    rgba(248,252,255,.58) 10%,
    rgba(248,252,255,.14) 40%,
    rgba(248,252,255,.03) 10%
  )!important;
}

/* Planowane loty - znacznie bardziej przezroczyste tło. */
.legacy-sections-v13 .planned{
  background:rgba(238,243,247,.41)!important;
}

/* Rekordy - znacznie bardziej przezroczyste tło. */
.legacy-sections-v13 .records-section{
  background:rgba(255,255,255,.41)!important;
}

</style>
