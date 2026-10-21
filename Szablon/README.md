# Harmonogram Pracy

Aplikacja WWW (PHP + MySQL) do planowania grafików pracy oddziału szpitalnego.

* **Administrator** tworzy grafiki miesięczne, osobno ustawia normy i edytuje siatkę grafiku, zarządza pracownikami.
* **Pracownik** widzi własny kalendarz zmian, grafik całego zespołu, statystyki godzin i może wyeksportować zmiany do kalendarza w telefonie.

## Dyżury i liczenie godzin

| Kod | Dyżur | Czas pracy | Typowe godziny |
|---|---|---|---|
| `D` | Dzienna | 12 h | 7:00-19:00 |
| `N` | Nocna | 12 h | 19:00-7:00 |
| `D/N` | Doba (24 h, zwykle kontrakt) | 24 h | 7:00-7:00 |
| `7:35` | Dyżur podstawowy | 7 h 35 min | 7:00-14:35 |
| `R` | Rano (system trzyzmianowy) | 8 h | 7:00-15:00 |
| `P` | Popołudnie (system trzyzmianowy) | 8 h | 15:00-23:00 |
| `N8` | Noc 8 h (system trzyzmianowy) | 8 h | 23:00-7:00 |
| `W7+10` (przykład) | Dyżur wyrównawczy dodany przez administratora | Wprowadzona liczba godzin i minut | - |
| `PT12` `PT24` | Pod telefonem (gotowość) | 0 h pracy, 12 / 24 h gotowości | - |
| `UW` `UŻ` `L4` | Urlop wypoczynkowy, na żądanie, chorobowe | - | - |

Zasady liczenia są w `includes/schedule.php`; standardowe dyżury są w `SHIFTS`, a własne dyżury wyrównawcze administrator zapisuje osobno dla każdego grafiku.

* **Domyślna norma czasu pracy** = 7 h 35 min x liczba dni roboczych w miesiącu (dni pn-pt pomniejszone o święta wypadające w dni inne niż niedziela; uwzględniona jest też Wigilia od 2025 r.). Administrator ustala indywidualną liczbę dni pracy oraz dyżury 8- albo 12-godzinne w formularzu przed edycją siatki, a później może dowolnie przechodzić między tymi stronami i zmieniać ustawienia. Aplikacja zapisuje indywidualną normę i wylicza liczbę pełnych dyżurów oraz czas wyrównawczy.
* **Godziny pracy** to suma czasu dyżurów z tabeli. Norma dla indywidualnego grafiku wynosi 7 h 35 min za każdy przydzielony dzień i jest pokazywana w edytorze oraz panelu pracownika razem z przydzielonym rodzajem dyżurów i bilansem zmian wyrównawczych.
* Administrator może dodać do grafiku własny dyżur wyrównawczy, podając godziny i minuty. Kod, np. `W7+10`, oraz jego czas pracy pojawiają się w palecie tego grafiku i są widoczne w podsumowaniach oraz panelu pracownika.
* **Dyżur pod telefonem nie wlicza się do godzin pracy** ani do normy. Jest podsumowywany osobno jako godziny gotowości (płatne 50%). Jeśli pracownik zostanie wezwany, wpisz w tym dniu właściwy dyżur.
* **Godziny nocne** (21:00-7:00) liczone są osobno, jako podstawa dodatku nocnego (dyżury wyrównawcze nie mają znanej pory, więc nie są wliczane).
* **Obsada** na dzień/noc: `D`, `7:35`, `R`, `P`, `W*` liczą się do dnia, `N`, `N8` do nocy, `D/N` do obu; `PT*` nie liczą się do obsady (pracownik nie jest na oddziale); urlopy i L4 to nieobecności.

## Wymagania

PHP 7.4+ (z rozszerzeniami `pdo_mysql`, `mbstring`), MySQL 5.7+/MariaDB 10.3+, serwer WWW (Apache/XAMPP).

## Instalacja

1. Skopiuj katalog do `htdocs` (lub innego katalogu serwera WWW).
2. **Nowa baza:** zaimportuj `database/schema.sql` (phpMyAdmin -> Importuj).
   **Istniejąca baza `hematologia`:** możesz zaimportować `database/update_schedule_features.sql`, aby dodać kolumnę `passwordChanged` i tabele indywidualnych norm oraz własnych dyżurów wyrównawczych. Aplikacja również tworzy brakujące tabele automatycznie. Konwersja grafików do `utf8mb4` (konieczna dla kodu `UŻ`) odbywa się w razie potrzeby. Wymaga to uprawnień `CREATE` i `ALTER` (na XAMPP-ie `root` je ma).
3. Dane dostępowe do bazy: skopiuj `includes/config.local.example.php` jako `includes/config.local.php` i ustaw własne wartości (domyślnie `localhost`, baza `hematologia`, użytkownik `root`, puste hasło).
4. Utwórz konto administratora z wiersza poleceń:
   ```
   php tools/create_admin.php admin TwojeHaslo123
   ```
5. Wejdź na `http://localhost/…/Harmonogram-Pracy/` i zaloguj się.

Pracownicy logują się loginem `Imię@Nazwisko`, a hasło początkowe to `Nazwisko@OH3` (przy pierwszym logowaniu system wymusza zmianę).

## Struktura projektu

```
index.php, logout.php, export.php      logowanie, wylogowanie, eksport CSV/iCal
includes/                              rdzeń: konfiguracja, baza, uwierzytelnianie, logika grafików, szablon
  config.php / config.local.php        ustawienia (local jest ignorowany przez Git)
  schedule.php                         tabele grafików, zapis/odczyt, święta, norma czasu pracy, obsada
  employees.php                        pracownicy i hasła
  layout.php                           wspólny sidebar/topbar
adminPanel/php/                        dashboard, pracownicy, formularz norm, edytor grafiku
userPanel/php/                         panel pracownika, grafik zespołu, zmiana hasła (też dla admina)
assets/                                style.css, loginstyle.css, skrypty JS
database/schema.sql                    schemat nowej bazy
database/update_schedule_features.sql  migracja dla istniejącej bazy
tools/create_admin.php                 tworzenie administratora (tylko CLI)
```

## Funkcje

**Administrator**
* Edytor grafiku jak arkusz: „pędzel" (klik / przeciąganie po komórkach), szybkie wpisywanie klawiaturą (strzałki + `D N O R W Z L`, `Delete`), cofnij/ponów (`Ctrl+Z` / `Ctrl+Y`), zapis `Ctrl+S`, ostrzeżenie o niezapisanych zmianach.
* Własne dyżury wyrównawcze dodawane w godzinach i minutach; indywidualne normy ustawia się przed utworzeniem siatki, a potem edytuje na osobnej stronie dostępnej bezpośrednio z grafiku.
* Podświetlenie weekendów, polskich świąt (łącznie z Wigilią od 2025 r.) i dzisiejszego dnia; sumy godzin na pracownika względem normy czasu pracy; obsada dzień/noc na każdy dzień (brak obsady na czerwono).
* Szybkie wpisywanie klawiszem pokazanym na przycisku zmiany (np. `S` = 7:35, `T` = PT12), liczenie godzin i gotowości w czasie rzeczywistym.
* Wypełnianie dni roboczych jednym kliknięciem, czyszczenie wiersza/całego grafiku, przełączanie miesięcy strzałkami, drukowanie, eksport CSV (Excel), usuwanie grafiku.
* Nowy grafik z opcją skopiowania zmian z wcześniejszego miesiąca.
* Pracownicy: wyszukiwarka, edycja imienia/nazwiska, reset hasła do domyślnego, status hasła (domyślne/zmienione), usuwanie z potwierdzeniem.
* Dashboard: kto dziś pracuje, obsada na 7 dni, alerty (brak grafiku na bieżący/następny miesiąc, pracownicy z hasłem domyślnym).

**Pracownik**
* Najbliższa zmiana (też z kolejnego miesiąca), kalendarz z nawigacją po miesiącach, kliknięcie dnia pokazuje kto jeszcze wtedy pracuje.
* Bilans zmian i godzin względem normy, eksport zmian do kalendarza (`.ics`), grafik zespołu z wyszukiwarką i podświetleniem własnego wiersza.

**Ogólne**
* Zwijane menu (stan zapamiętywany), wersja mobilna, style do druku.
* Zmiana hasła z podglądem siły hasła i pokazywaniem hasła.

## Co naprawiono w stosunku do poprzedniej wersji

* **Grafiki nie były widoczne w panelu pracownika i na dashboardzie** – nazwy tabel (`2026_Październik`) były porównywane z kluczami bez polskich znaków. Teraz nazwy są rozpoznawane niezależnie od wielkości liter i diakrytyków (także na Windowsie, gdzie MySQL zmienia nazwy tabel na małe litery).
* **Statystyki nie liczyły zmian `D/N`** (zapis `D/N`, liczenie `DN`) – kody są normalizowane.
* **Każdy zalogowany mógł zmienić hasło dowolnej osoby** (imię/nazwisko z adresu URL) – zmiana hasła działa teraz wyłącznie na koncie z sesji i wymaga aktualnego hasła.
* **SQL injection** (nazwy tabel i wartości wklejane do zapytań) – wszystkie zapytania są parametryzowane, a nazwy tabel pochodzą z zweryfikowanej listy.
* Brakowała autoryzacji w `manageEmployees.php`, `deleteEmployee.php`, `CreateSchedule.php`; `adminadd.php` pozwalał każdemu utworzyć administratora -> zastąpiony narzędziem CLI.
* Po zmianie hasła domyślne hasło nadal pozwalało się zalogować – usunięte.
* Ochrona CSRF, `session_regenerate_id`, limit nieudanych logowań, wygasanie sesji po bezczynności, nagłówki bezpieczeństwa, komunikat „Nieprawidłowy login lub hasło" (bez zdradzania, czy login istnieje).
* Martwe linki (`addEmployee.html`, `adminPanelMain.html`, `UserPanelMain.php`, wielkość liter w `UserPanel.php`) i przycisk „Stwórz grafik" prowadzący do skryptu tylko-POST.
* W edytorze opcja `UW` była zaznaczana, gdy wartość to `R`; w liście wyboru brakowało `R`.
* Zduplikowany kod połączeń z bazą w każdym pliku -> jedna konfiguracja; zduplikowany CSS/JS -> wspólne pliki.

### Zmiany w strukturze
* Usunięte: `adminadd.php`, `adminPanel/php/addEmployee.php`, `deleteEmployee.php`, `CreateSchedule.php`, `InsertIntoSchedule.php`, `assets/cardFix.css`, `queries.sql`. Ich funkcje przejęły `manageEmployees.php`, `CreateSchedulePanel.php` + `editSchedule.php`.
* Menu boczne zwija się przyciskiem „hamburger" (wcześniej każde kliknięcie w treść zwijało menu, co przeszkadzało przy edycji grafiku).
* Nowe wymagania hasła (min. 8 znaków, wielka litera, cyfra – zmienisz w `includes/config.php`).

## Uwagi

* `includes/`, `tools/` i `database/` mają `.htaccess` blokujący dostęp z przeglądarki (Apache 2.4).
* Na produkcji ustaw `debug => false` (domyślnie) oraz użyj HTTPS i własnego użytkownika MySQL zamiast `root`.
* Godziny dyżurów i normę zmienisz w `includes/schedule.php` (stałe `SHIFTS` i `DAILY_NORM_MINUTES`). Rozliczenie okresu rozliczeniowego, nadgodzin i dni wolnych za nadwyżkę ponad 7 h 35 min pozostaje po stronie kadr.
