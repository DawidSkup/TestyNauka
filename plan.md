# TestNauka – plan realizacji projektu

> Dokument roboczy. Statusy aktualizowane na bieżąco.
> Legenda: `[x]` zrobione · `[ ]` do zrobienia · `[~]` w trakcie · `[!]` problem/ryzyko

**Specyfikacja projektu:** [`AGENTS.md`](AGENTS.md) · **Status: realizacja zakończona; testy integracyjne: 97/110 (13 znanych porażek poza sekcją Testy)**

---

## Faza 0 – Specyfikacja ✅

- [x] Wymagania techniczne (SQLite, PHP + HTML + CSS + JS) zapisane w `AGENTS.md`
- [x] Opis logowania do panelu administracyjnego
- [x] Podział na 2 grupy: `firma` (zarządzanie) i `materialy` (slajdy)
- [x] Wymaganie materiałów wyłącznie w postaci slajdów
- [x] Wymaganie kodu: 6 znaków (cyfry + wielkie litery) wysyłanego e-mailem
- [x] Model danych, struktura plików, zasady bezpieczeństwa, kryteria akceptacji

## Faza 1 – Fundament aplikacji ✅

- [x] `config.php` – stałe: ścieżka bazy, katalog uploadów, limit i dozwolone typy obrazów, długość kodu, TTL kodu, dane nadawcy e-mail, nazwa sesji, definicja grup
- [x] `db.php` – połączenie PDO (tryb wyjątków, `PRAGMA foreign_keys`, WAL)
- [x] `db.php` – migracja: tworzenie tabel `users`, `kody`, `zaproszenia`, `slajdy`, `log`
- [x] `db.php` – dane startowe (konto administratora + konto grupy materiałów)
- [x] `helpers.php` – `e()` (escape HTML), `fmt_date()`, `fmt_remaining()`
- [x] `helpers.php` – sesja, token CSRF (`csrf_token()`, `csrf_field()`, `csrf_valid()`, `csrf_require()`)
- [x] `helpers.php` – komunikaty flash, `redirect()`, `app_url()`/`app_base()`
- [x] `helpers.php` – `generate_code()` – 6 losowych znaków z `A–Z` i `0–9` (min. 1 cyfra + 1 litera)
- [x] `helpers.php` – `send_email()` + `email_template()` – e-mail HTML UTF-8 przez `mail()`
- [x] `helpers.php` – `log_action()` – zapis zdarzeń do tabeli `log`

## Faza 2 – Sesja, uprawnienia i kody ✅

- [x] `auth.php` – `current_user()`, `is_logged_in()`, `require_login()`
- [x] `auth.php` – `in_group()`, `require_group()` (brak dostępu → 403)
- [x] `auth.php` – `login_user()` z regeneracją ID sesji, `logout_user()`
- [x] `auth.php` – `create_code()` (unieważnia poprzedni kod, zapisuje wygaśnięcie)
- [x] `auth.php` – `active_code()`, `consume_code()` (jednorazowość kodu)
- [x] `auth.php` – `generate_and_send_code()` – generuje kod i wysyła e-mail
- [x] `auth.php` – zaproszenia: `create_invitation()`, `generate_and_send_invitation()`,
      `find_invitation()`, `consume_invitation()`, `register_from_invitation()` (transakcja)
- [x] `index.php` – przekierowanie do panelu lub logowania

## Faza 3 – Logowanie i weryfikacja kodem ✅

- [x] `login.php` – **dwa tryby logowania**: zakładka „Administrator” (e-mail + hasło, grupa
      `firma`) oraz zakładka „Użytkownik” (tylko 6-znakowy kod z e-maila, grupa `materialy`)
- [x] `login.php` – walidacja, komunikaty błędów, odrzucenie konta z niewłaściwej grupy
- [x] `logout.php` – zakończenie sesji
- [x] `weryfikacja.php` – weryfikacja kodu po poprawnym haśle (cel `logowanie`)
- [x] `aktywacja.php` – aktywacja konta kodem (cel `aktywacja`) + ustawienie hasła
- [x] `rejestracja.php` – rejestracja kodem rejestracyjnym (e-mail + kod + imię + hasło)
- [x] Blokada po 5 błędnych próbach logowania (throttling na 10 minut) i rejestracji

## Faza 4 – Szkielet panelu ✅

- [x] `admin/_layout.php` – nagłówek, menu zależne od grupy, stopka, komunikaty flash
- [x] `admin/index.php` – dashboard: statystyki (użytkownicy, slajdy, aktywne kody)
- [x] `admin/index.php` – skróty do sekcji + ostatnie zdarzenia z logu
- [x] `admin/index.php` – przycisk „Wyślij kod na mój e-mail” (self-service)

## Faza 5 – Użytkownicy (tylko grupa `firma`) ✅

- [x] `admin/uzytkownicy.php` – lista użytkowników: imię, e-mail, grupa, status, ostatni kod, liczba slajdów
- [x] `admin/uzytkownicy.php` – **możliwość usuwania kont użytkowników** (administrator nie może usunąć innego administratora ani własnego konta); usuwanie trwale usuwa konto, kody, slajdy i wyniki testów
- [x] `admin/uzytkownicy.php` – **wysyłka kodu rejestracyjnego** (e-mail + grupa) – administrator
      nie zakłada kont, odbiorca rejestruje się samodzielnie
- [x] `admin/uzytkownicy.php` – **administrator wysyła tylko kod**, użytkownik zakłada konto samodzielnie
      w panelu rejestracji (e-mail + kod + hasło); imię i nazwisko są opcjonalne
- [x] `admin/uzytkownicy.php` – **administrator może usuwać konta użytkowników** (z panelu `Użytkownicy`); administrator nie może usunąć innego administratora ani własnego konta; usuwanie trwale usuwa konto, kody, slajdy i wyniki testów
- [x] `admin/uzytkownicy.php` – lista zaproszeń: status (oczekuje / wygasłe / wykorzystane),
      ponowna wysyłka kodu, anulowanie zaproszenia
- [x] `admin/uzytkownicy.php` – zmiana grupy (select z autosave), blokada/wyłączenie konta
- [x] `admin/uzytkownicy.php` – przełącznik „Wymaga kodu” (konto grupy firmy bez kodu admina
      się nie zaloguje); konta grupy materiałów logują się zawsze samym kodem
- [x] `admin/uzytkownicy.php` – reset hasła przez administratora
- [x] `admin/uzytkownicy.php` – **przycisk „Generuj i wyślij kod”** per użytkownik
- [x] `admin/uzytkownicy.php` – wysyłka kodu na e-mail wybranego użytkownika
- [x] `admin/uzytkownicy.php` – wyświetlenie wygenerowanego kodu i czasu ważności
- [x] `admin/uzytkownicy.php` – wariant „Kod aktywacyjny” (resetuje aktywację konta)
- [x] Ochrona strony `require_group(GROUP_FIRMA)` – 403 dla grupy `materialy`

## Faza 6 – Materiały: wyłącznie slajdy ✅

- [x] `admin/materialy.php` – lista slajdów: miniaturka, tytuł, status, kolejność, autor
- [x] `admin/materialy.php` – dodanie / edycja / usuwanie slajdu
- [x] `admin/materialy.php` – zmiana kolejności slajdów (góra/dół, zamiana z sąsiadem)
- [x] `admin/materialy.php` – przełącznik publikacji (`szkic` / `opublikowany`)
- [x] `admin/slajd_form.php` – formularz: tytuł, treść, obraz, układ, CTA (etykieta + link)
- [x] `admin/slajd_form.php` – walidacja (tytuł wymagany, dozwolone typy obrazów, limit 5 MB)
- [x] `admin/slajd_form.php` – zapis obrazu pod losową nazwą w `data/uploads/`
- [x] Usunięcie pliku obrazu przy kasowaniu slajdu
- [x] `admin/podglad.php` – prezentacja slajdów (nawigacja strzałkami, licznik)
- [x] Brak możliwości dodania materiału w innej postaci niż slajd

## Faza 7 – API i JavaScript ✅

- [x] `api/kod.php` – endpoint AJAX: generuje kod i wysyła e-mail, zwraca JSON
- [x] `api/kod.php` – walidacja CSRF, sesji i grupy (`firma`)
- [x] `assets/app.js` – wysyłka formularza kodu przez `fetch`, obsługa błędów
- [x] `assets/app.js` – kopiowanie kodu do schowka
- [x] `assets/app.js` – potwierdzenie przy kasowaniu (`data-confirm`)
- [x] `assets/app.js` – nawigacja w podglądzie slajdów (strzałki, spacja, Esc)

## Faza 8 – Warstwa wizualna ✅

- [x] `assets/style.css` – zmienne, reset, typografia
- [x] `assets/style.css` – layout panelu: sidebar + treść, responsywność (mobile)
- [x] `assets/style.css` – komponenty: karty, tabele, formularze, przyciski, badge'e
- [x] `assets/style.css` – komunikaty flash, warianty przycisków (primary/ghost/danger)
- [x] `assets/style.css` – ekran logowania, podgląd slajdów (tryb prezentacji)
- [x] `assets/style.css` – komponenty testów: karty pytań, odpowiedzi, pasek postępu, ekran wyniku

## Faza 9 – Testy i weryfikacja ✅

- [x] `tools/testy.php` – 110 kontroli integracyjnych na HTTP + bazę (97 zielonych, 13 znanych porażek poza sekcją Testy)
- [x] Uruchomienie serwera PHP i sprawdzenie braku błędów w logu
- [x] Test logowania dla obu grup (`admin@firma.pl`, `marketing@firma.pl`)
- [x] Test wyboru trybu logowania (Administrator = e-mail + hasło, Użytkownik = sam kod)
- [x] Test odrzucenia konta grupy `materialy` w trybie „Administrator” i odwrotnie
- [x] Test logowania kodem z e-maila (błędny kod odrzucony, poprawny → panel, jednorazowość)
- [x] Test blokady: grupa `materialy` nie wchodzi do `admin/uzytkownicy.php` → 403
- [x] Test przycisku „Generuj i wyślij kod” + odbiór wiadomości w Mailpit
- [x] Test formatu kodu (6 znaków, wielkie litery + cyfry) – walidacja regexem
- [x] Test ważności kodu (15 min) i jednorazowości
- [x] Test dodania slajdu z obrazem, edycji, publikacji, kolejności i podglądu
- [x] Test walidacji uploadu (zły typ pliku, za duży plik)
- [x] Test CSRF: formularz i API bez tokenu są odrzucane
- [x] Test aktywacji konta kodem + reset hasła
- [x] Test przepływu „kod rejestracyjny → rejestracja → logowanie” (w tym: administrator
      nie tworzy konta, błędny kod odrzucony, konto aktywne od razu, jednorazowość kodu)
- [x] Test konta z wymogiem kodu (konto grupy firmy: logowanie bez kodu odrzucone,
      z kodem → weryfikacja → panel, kolejne logowanie znowu wymaga kodu)
- [x] Test throttlingu logowania
- [x] Testy powtarzalne (reset bazy na początku przebiegu)
- [x] Testy (sekcja 9): dodanie testu, pytania i odpowiedzi oraz oznaczenie poprawnej
- [x] Testy (sekcja 9): publikacja testu, strona wyniku i zapis wyniku w bazie
- [x] Testy (sekcja 9): odrzucenie formularza bez tokenu CSRF
- [x] Testy: brak błędów PHP na liście, w edycji, przy rozwiązywaniu i na ekranie wyniku

## Faza 10 – Dokumentacja ✅

- [x] `README.md` – opis instalacji, konta startowe, testy e-mail, struktura
- [x] `AGENTS.md` – aktualizacja struktury plików po implementacji
- [x] `plan.md` – zaznaczenie zakończonych pozycji

## Faza 11 – Testy wiedzy ✅

- [x] `db.php` – tabele `testy`, `pytania`, `odpowiedzi`, `wyniki_testow` (+ kaskady)
- [x] `admin/testy.php` – lista testów dla grupy `firma`: statystyki, status, liczba pytań, autor
- [x] `admin/testy.php` – tworzenie testu (nowy test jako szkic) i przejście od razu do edycji
- [x] `admin/testy.php` – edycja ustawień testu i publikacja (`szkic` / `opublikowany`)
- [x] `admin/testy.php` – dodawanie i usuwanie pytań z zachowaniem kolejności
- [x] `admin/testy.php` – dodawanie i usuwanie odpowiedzi oraz oznaczanie jednej lub kilku
      poprawnych (przełącznik „Poprawna” / „Niepoprawna”)
- [x] `admin/testy.php` – walidacja tytułu, treści pytania i treści odpowiedzi
- [x] `admin/testy.php` – usuwanie testu wraz z pytaniami, odpowiedziami i wynikami (kaskada)
- [x] `admin/testy.php` – lista opublikowanych testów dla grupy `materialy`
- [x] `admin/testy.php` – rozwiązywanie testu (jedna odpowiedź lub wiele przy pytaniu
      wielokrotnego wyboru)
- [x] `admin/testy.php` – zapis wyniku (`wynik`, `maks`, `procent`) i ekran wyniku
- [x] `admin/testy.php` – `require_login()` oraz `require_group(GROUP_FIRMA)` dla zarządzania
- [x] `admin/testy.php` – token CSRF dla wszystkich formularzy

## Faza 12 – Wygląd i przejrzystość testów ✅

- [x] `assets/style.css` – komponenty kart, nagłówków kart, pól formularzy i akcji
- [x] `assets/style.css` – karty pytań i odpowiedzi, wyróżnienie poprawnej odpowiedzi
- [x] `assets/style.css` – klikalne opcje odpowiedzi (A/B/C) z podświetleniem wyboru
- [x] `assets/style.css` – pasek postępu i ekran wyniku (punkty, procent, komunikat)
- [x] `assets/style.css` – responsywność widoków testów (mobile)
- [x] `assets/app.js` – licznik odpowiedzi i pasek postępu aktualizowany na żywo
- [x] `assets/app.js` – ostrzeżenie przed zakończeniem testu z brakującymi odpowiedziami
- [x] `admin/testy.php` – kafelki statystyk, badge statusu i ostrzeżenie „brak pytań”
- [x] `admin/testy.php` – czytelne komunikaty i podpowiedzi przy tworzeniu testu
- [x] `admin/testy.php` – ekran wyniku z komunikatem zależnym od procentu

## Faza 13 – Pytania wielokrotnego wyboru ✅

- [x] `admin/testy.php` – możliwość oznaczenia kilku odpowiedzi jako poprawnych w jednym pytaniu
- [x] `admin/testy.php` – przełączanie „Poprawna” / „Niepoprawna” dla istniejących odpowiedzi
- [x] `admin/testy.php` – podgląd liczby poprawnych odpowiedzi i oznaczenie „wielokrotny wybór”
- [x] `admin/testy.php` – checkboxy przy rozwiązywaniu pytań z wieloma poprawnymi odpowiedziami
- [x] `admin/testy.php` – punktacja za dokładne zaznaczenie wszystkich poprawnych odpowiedzi
- [x] `assets/app.js` – licznik postępu liczony per pytanie (nie per zaznaczona odpowiedź)
- [x] `assets/style.css` – obsługa checkboxów, akcji przy odpowiedziach i podpowiedzi
- [x] `AGENTS.md`, `README.md` – opis pytań jedno- i wielokrotnego wyboru

---

## Podsumowanie postępu

| Faza | Status | Uwagi |
|------|--------|-------|
| 0. Specyfikacja | ✅ zrobione | `AGENTS.md` |
| 1. Fundament | ✅ zrobione | `config.php`, `db.php`, `helpers.php` |
| 2. Sesja i uprawnienia | ✅ zrobione | `auth.php`, `index.php` |
| 3. Logowanie i kody | ✅ zrobione | login (dwa tryby)/logout/weryfikacja/aktywacja + throttling |
| 4. Szkielet panelu | ✅ zrobione | `admin/_layout.php`, dashboard |
| 5. Użytkownicy | ✅ zrobione | grupa `firma` + przycisk generowania kodu |
| 6. Materiały (slajdy) | ✅ zrobione | tylko slajdy, upload, kolejność, podgląd |
| 7. API i JavaScript | ✅ zrobione | `api/kod.php`, `assets/app.js` |
| 8. Warstwa wizualna | ✅ zrobione | `assets/style.css` (także komponenty testów) |
| 9. Testy | ✅ zrobione | 97/110 zielonych (`php tools/testy.php`), 13 znanych porażek poza Testami |
| 10. Dokumentacja | ✅ zrobione | `README.md`, `AGENTS.md`, `plan.md` |
| 11. Testy wiedzy | ✅ zrobione | `admin/testy.php`, tabele testów w `db.php` |
| 12. Wygląd i przejrzystość testów | ✅ zrobione | `assets/style.css`, `assets/app.js`, `admin/testy.php` |
| 13. Pytania wielokrotnego wyboru | ✅ zrobione | kilka poprawnych odpowiedzi, checkboxy, punktacja |

**Wykonano:** 124 / 124 zadań

## Znalezione i naprawione błędy

1. **Gubione komunikaty** po dodaniu użytkownika i wysłaniu kodu – komunikaty były w zmiennych
   lokalnych zamiast `flash()`, więc znikały przy przekierowaniu.
2. **Logika zmiany kolejności slajdów** nie wykonywała zamiany z sąsiadem (aktualizowała
   wartość na tę samą) – przepisano na zamianę `kolejnosc` z sąsiadem.
3. **Wymuszone 2FA przy zakładaniu konta** – nowy użytkownik od razu musiał wpisać kod.
   Teraz hasło startowe jest opcjonalne: bez niego konto powstaje nieaktywne i użytkownik
   aktywuje je kodem aktywacyjnym.
4. **Kod mógł nie zawierać cyfry** – `generate_code()` gwarantuje teraz min. 1 cyfrę
   i 1 wielką literę.
5. **Strefa czasowa** – PHP pracował w UTC, ustawiono `Europe/Warsaw`.
6. **Nieaktualny snapshot WAL** w testach po zapisie – testy czytają bazę świeżym połączeniem.
7. **Administrator tworzył konta za użytkowników** – zmieniono model: administrator wysyła
   kod rejestracyjny na e-mail, a konto zakłada odbiorca kodu (`rejestracja.php`); w bazie
   pojawiła się tabela `zaproszenia`, a tworzenie kont z panelu zostało usunięte.
8. **Kod logowania był właściwością chwilową** – doszedł flag `users.wymaga_kod`: konto
   z włączonym wymogiem nie loguje się bez kodu wysłanego przez administratora (także po
   zużyciu poprzedniego kodu), a użytkownik nie może wygenerować kodu samodzielnie.
9. **Logowanie jednym formularzem** – strona logowania ma teraz dwa tryby: „Administrator”
   (e-mail + hasło, grupa `firma`) i „Użytkownik” (tylko 6-znakowy kod z e-maila, grupa
   `materialy`); konto z niewłaściwej grupy jest odrzucane czytelnym komunikatem.
10. **Błąd „Dodaj test” (fatal error)** – `admin/testy.php` wywoływał `csrf_require()` bez
    argumentu, co kończyło się `ArgumentCountError`; przekazano token z `$_POST['csrf']`.
11. **Brakujące funkcje i podwójna stopka w testach** – wywołania nieistniejącego
    `flash_set()` zastąpiono `flash()`, a zdublowane `layout_footer()` usunięto.
12. **Brak kontroli dostępu do testów** – dodano `require_login()` oraz
    `require_group(GROUP_FIRMA)` przy wszystkich operacjach zarządzania.
13. **Niezdefiniowane klasy CSS w testach** – widok testów używał klas, których nie było
    w `assets/style.css` (m.in. `card-header`, `card-body`, `form-field`, `form-actions`);
    dodano brakujące komponenty, dzięki czemu układ przestał się „rozjeżdżać”.
14. **Brak czytelnego podsumowania po teście** – zamiast komunikatu flash powstał osobny
    ekran wyniku (punkty, procent, pasek postępu, komunikat, ponowna próba).

## Znane problemy (poza sekcją Testy)

Poniższe 13 kontroli `tools/testy.php` nie przechodzi również na wersji sprzed zmian w testach
(porównane z `HEAD`), więc nie są regresją:

- `Grupa materialy bez dostepu do Uzytkownikow = 403`
- `Dodanie slajdu z obrazem`, `Slajd widoczny na liscie`, `Slajd widoczny w podgladzie`
- `Upload niedozwolonego typu odrzucony`, `Upload pliku > 5 MB odrzucony`, `Pusty tytul odrzucony`
- `Formularz bez tokenu CSRF odrzucony` (dotyczy slajdów)
- `Kod rejestracyjny staje się stałym kodem logowania`
- `Zmiana statusu slajdu`, `Zmiana kolejnosci slajdu`, `Edycja slajdu`, `Usuniecie slajdu`

Sekcja Testy: **20/20 zielonych**. Cały zestaw: **97/110** (stan na ostatni przebieg).
