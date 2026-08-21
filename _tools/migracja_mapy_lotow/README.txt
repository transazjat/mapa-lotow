MAPA LOTOW - POWTARZALNA PROCEDURA MIGRACYJNA
=============================================

CEL
---
Pakiet buduje od zera baze mapa_lotow_candidate na podstawie aktualnej bazy
transazja i czterech niezmienionych historycznych plikow SQL. Po pozytywnej
walidacji osobny krok eksportuje kandydata i importuje go pod nazwa mapa_lotow.

WYMAGANIA
---------
- Windows 11 i Laragon z uruchomionym MySQL 8.4.x.
- Python 3.
- Biblioteki timezonefinder i tzdata.
- Baza transazja zaimportowana na tym samym serwerze MySQL.
- Programy mysql.exe i mysqldump.exe dostepne w PATH albo w Laragonie na C: lub D:.

KONFIGURACJA
------------
Domyslne ustawienia znajduja sie w config.json:
- host: 127.0.0.1
- port: 3306
- user: root
- baza zrodlowa: transazja
- baza kandydujaca: mapa_lotow_candidate
- baza finalna: mapa_lotow

Jesli automatyczne wykrycie mysql.exe nie zadziala, wpisz pelne sciezki w polach
mysql_executable i mysqldump_executable.

Haslo nie jest zapisywane w config.json. Program pyta o nie przy uruchomieniu.
Przy standardowym pustym hasle Laragona nacisnij Enter.

PIERWSZE URUCHOMIENIE
--------------------
Jednorazowo uruchom 00_INSTALUJ_WYMAGANIA.bat. Skrypt zainstaluje biblioteki
potrzebne do wyznaczania stref IANA oraz obslugi tych stref w Windows.

BUDOWA KANDYDATA
----------------
1. Upewnij sie, ze baza transazja zawiera aktualny eksport produkcji.
2. Uruchom 01_BUDUJ_KANDYDATA.bat.
3. Narzedzie wykona preflight, usunie jedynie mapa_lotow_candidate, zbuduje ja
   ponownie i uruchomi cztery pliki SQL.
4. Wykona zweryfikowane korekty danych historycznych.
5. Porowna slownik lotnisk z dolaczona migawka OurAirports, zaktualizuje
   dopasowane rekordy bez zmiany ich ID i doda brakujace lotniska pasazerskie.
6. Rozstrzygnie zweryfikowane konflikty uzywanych lotnisk i przepnie loty do
   rekordow kanonicznych. Reguly z data graniczna uwzgledniaja date lotu.
7. Uporzadkuje historyczny slownik typow samolotow i doda szczegolowe warianty.
8. Uporzadkuje uzywane linie lotnicze, scali potwierdzone duplikaty i doda
   wspolczesne linie pasazerskie.
9. Dla wszystkich lotnisk wyznaczy timezone_name na podstawie wspolrzednych.
10. Przeliczy diagnostycznie czasy lotow w UTC dla calej bazy i osobno poda
   wyniki dla uzytkownika 75. Historyczne duration_seconds nie sa zmieniane.
11. Wykona kontrole integralnosci, w tym kontrole lotnisk bez strefy czasowej.
12. Sprawdz wynik PASS lub PASS WITH WARNINGS i pliki w katalogu reports.

Budowa kandydata nigdy nie usuwa ani nie zmienia bazy transazja ani mapa_lotow.

PROMOCJA
--------
1. Przetestuj aplikacje na bazie mapa_lotow_candidate.
2. Zatrzymaj mozliwosc zapisu w starej aplikacji i wykonaj finalny eksport.
3. Ponownie zbuduj kandydata.
4. Uruchom 02_PROMUJ_KANDYDATA.bat.
5. Wpisz dokladnie PROMUJ.

Procedura promocji:
- tworzy kopie obecnej mapa_lotow w katalogu backup,
- eksportuje mapa_lotow_candidate,
- tworzy pusta mapa_lotow,
- importuje eksport kandydata do mapa_lotow,
- porownuje podstawowe liczby rekordow,
- przy bledzie probuje automatycznie odtworzyc poprzednia mapa_lotow.

DODATKOWE MIGRACJE
------------------
Kolejne zmiany SQL nalezy zapisywac jako nowe pliki w sql/extensions, np.:
05_aktualizacja_lotnisk.sql
06_aktualizacja_linii.sql

Sa wykonywane alfabetycznie po czterech historycznych plikach. Nie nalezy
modyfikowac plikow w sql/legacy.

AKTUALIZACJA LOTNISK
--------------------
Pakiet zawiera nieruchoma migawke reference_data/ourairports_airports_2026-08-21.csv.
Nie pobiera danych z Internetu podczas migracji, dlatego ten sam eksport produkcji
i ta sama paczka zawsze daja ten sam wynik.

Reguly aktualizacji:
- istniejace ID lotnisk sa zachowywane,
- dopasowanie odbywa sie po jednoznacznym kodzie ICAO, a nastepnie IATA,
- sprzeczne dopasowania nie sa stosowane i trafiaja do raportu JSON,
- lotniska zamkniete sa oznaczane is_active=0, a nie usuwane,
- nowe aktywne lotniska sa dodawane, jesli sa duze, srednie, obsluguja regularne
  rejsy albo maja kod IATA,
- nowe ID maja postac 1000000 + ID OurAirports, dzieki czemu sa powtarzalne,
- powiazania ze zrodlem sa zapisywane w ml_airport_reference_links,
- strefy IANA sa wyznaczane dopiero po aktualizacji wspolrzednych.

Zrodlo i data migawki sa zapisane w reference_data/source_manifest.json.

ROZSTRZYGANIE UZYWANYCH KONFLIKTOW LOTNISK
------------------------------------------
Wersja 3.2 zawiera zweryfikowane reguly dla 22 uzywanych starych rekordow.
Jesli port zostal zastapiony nowym, odwolania w ml_flights sa przenoszone wedlug
daty odlotu lub przylotu. Stare rekordy pozostaja w bazie jako is_active=0.
Szczegoly wykonania sa w tabeli ml_airport_resolution_log i w sekcji
airport_conflict_resolution raportu BUILD.

Lotnisko ID 2614 zostalo jednoznacznie rozpoznane na podstawie lotu 4480 IGU-Rio.
Procedura ustawia Santa Cruz Air Force Base, IATA SNZ, ICAO SBSC i zrodlo 5969.

TYPY STATKOW POWIETRZNYCH
-------------------------
Pakiet zachowuje wszystkie 86 historycznych ID i istniejace przypisania lotow.
Nazwy sa ujednolicane, pole family sluzy do laczenia wariantow w statystykach,
a manufacturer, model, variant i icao_code sa uzupelniane tam, gdzie typ jest
jednoznaczny. Ogolne wpisy, np. Boeing 737, pozostaja dla starych danych.

Dodawanych jest 60 szczegolowych wariantow z trwalymi ID od 2000001. Obejmuja
m.in. A220, warianty A330/A340/A350, A320neo/A321neo, rodzine 737 i 737 MAX,
warianty 747/757/767/777/787, ATR -500/-600, CRJ, Dash 8, Embraer E2, COMAC
C909/C919 oraz MC-21. Zrodlem nazewnictwa i kodow jest ICAO Doc 8643; metadane
sa w reference_data/aircraft_source_manifest.json.

Procedura nie zgaduje szczegolowego wariantu dla starych lotow. Daty przypisania
oczywiscie pozniejsze od daty lotu sa raportowane w aircraft_type_reference_update,
ale historyczne rekordy ml_flights nie sa automatycznie zmieniane.

LINIE LOTNICZE
--------------
Pakiet zachowuje wszystkie historyczne ID linii. Aktualizacja koncentruje sie na
rekordach uzywanych w lotach. Poprawiane sa jednoznaczne nazwy i kody, natomiast
sam kod IATA lub ICAO nigdy nie jest wystarczajaca podstawa do scalenia.

Potwierdzone duplikaty British Airways, Pegasus Airlines, Jet Airways i Japan
Airlines Domestic sa przepinane do rekordow kanonicznych. Stary rekord pozostaje
w bazie jako nieaktywny. Swissair i SWISS, Tigerair i Scoot oraz historyczne
spolki Wizz Air pozostaja odrebne.

Dodawane sa 23 wspolczesne linie, m.in. ITA Airways, Norse Atlantic, Breeze,
Avelo, Akasa Air, Air Premia, STARLUX, Bamboo Airways, flyadeal, AJet, Discover
Airlines, Arajet, ZIPAIR, KM Malta Airlines oraz Scoot. Szczegoly sa zapisywane
w ml_airline_resolution_log i airline_reference_update raportu BUILD.

Zrodlami kodow sa oficjalne katalogi IATA i ICAO Doc 8585. Metadane zrodel sa
w reference_data/airline_source_manifest.json. Pelny stary slownik 5898 pozycji
nie jest automatycznie czyszczony ani usuwany.

KATALOGI
--------
backup  - eksport kandydata i kopia bazy przed promocja
reports - raporty JSON i pelne logi wykonania SQL
temp    - tymczasowe kopie SQL skierowane do bazy kandydujacej

STREFY CZASOWE I CZASY LOTOW
----------------------------
Strefy IANA sa odtwarzane przy kazdej budowie bazy kandydujacej. Migracja
otrzyma FAIL, jezeli po tym etapie jakies lotnisko nadal nie ma timezone_name
albo nazwa strefy nie jest obslugiwana przez zoneinfo.

Roznice pomiedzy duration_seconds a czasem obliczonym z dat, godzin i stref sa
diagnostyczne i trafiaja do raportu JSON. Nie nadpisujemy nimi historycznych
czasow lotow. Ujemne wyniki daja PASS WITH WARNINGS i wymagaja przegladu.

BEZPIECZENSTWO
--------------
- Nigdy nie uruchamiaj promocji bez aktualnej kopii serwera produkcyjnego.
- Nie usuwaj bazy transazja po publikacji.
- Przed finalnym eksportem zablokuj dopisywanie danych do starej aplikacji.
- Sam wynik BUILD nie powoduje podmiany bazy mapa_lotow.
