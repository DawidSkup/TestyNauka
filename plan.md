# TestNauka – plan realizacji projektu

> Dokument roboczy. Statusy aktualizowane na bieżąco.
> Legenda: `[x]` zrobione · `[ ]` do zrobienia · `[~]` w trakcie · `[!]` problem/ryzyko

**Specyfikacja projektu:** [`AGENTS.md`](AGENTS.md) · **Status: realizacja zakończona (45/45 testów zielonych)**

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
- [x] `db.php` – migracja: tworzenie tabel `users`, `kody`, `slajdy`, `log`
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
- [x] `index.php` – przekierowanie do panelu lub logowania

## Faza 3 – Logowanie i weryfikacja kodem ✅

- [x] `login.php` – formularz e-mail + hasło, walidacja, komunikaty błędów
- [x] `logout.php` – zakończenie sesji
- [x] `weryfikacja.php` – weryfikacja kodu po poprawnym haśle (cel `logowanie`)
- [x] `aktywacja.php` – aktywacja konta kodem (cel `aktywacja`) + ustawienie hasła
- [x] Blokada po 5 błędnych próbach logowania (throttling na 10 minut)

## Faza 4 – Szkielet panelu ✅

- [x] `admin/_layout.php` – nagłówek, menu zależne od grupy, stopka, komunikaty flash
- [x] `admin/index.php` – dashboard: statystyki (użytkownicy, slajdy, aktywne kody)
- [x] `admin/index.php` – skróty do sekcji + ostatnie zdarzenia z logu
- [x] `admin/index.php` – przycisk „Wyślij kod na mój e-mail” (self-service)

## Faza 5 – Użytkownicy (tylko grupa `firma`) ✅

- [x] `admin/uzytkownicy.php` – lista użytkowników: imię, e-mail, grupa, status, ostatni kod, liczba slajdów
- [x] `admin/uzytkownicy.php` – dodanie użytkownika (e-mail, imię, grupa, hasło startowe opcjonalne)
- [x] `admin/uzytkownicy.php` – zmiana grupy (select z autosave), blokada/wyłączenie konta
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

## Faza 9 – Testy i weryfikacja ✅

- [x] `tools/testy.php` – 45 testów integracyjnych na HTTP + bazę
- [x] Uruchomienie serwera PHP i sprawdzenie braku błędów w logu
- [x] Test logowania dla obu grup (`admin@firma.pl`, `marketing@firma.pl`)
- [x] Test blokady: grupa `materialy` nie wchodzi do `admin/uzytkownicy.php` → 403
- [x] Test przycisku „Generuj i wyślij kod” + odbiór wiadomości w Mailpit
- [x] Test formatu kodu (6 znaków, wielkie litery + cyfry) – walidacja regexem
- [x] Test ważności kodu (15 min) i jednorazowości
- [x] Test dodania slajdu z obrazem, edycji, publikacji, kolejności i podglądu
- [x] Test walidacji uploadu (zły typ pliku, za duży plik)
- [x] Test CSRF: formularz i API bez tokenu są odrzucane
- [x] Test aktywacji konta kodem + reset hasła
- [x] Test throttlingu logowania
- [x] Testy powtarzalne (reset bazy na początku przebiegu)

## Faza 10 – Dokumentacja ✅

- [x] `README.md` – opis instalacji, konta startowe, testy e-mail, struktura
- [x] `AGENTS.md` – aktualizacja struktury plików po implementacji
- [x] `plan.md` – zaznaczenie zakończonych pozycji

---

## Podsumowanie postępu

| Faza | Status | Uwagi |
|------|--------|-------|
| 0. Specyfikacja | ✅ zrobione | `AGENTS.md` |
| 1. Fundament | ✅ zrobione | `config.php`, `db.php`, `helpers.php` |
| 2. Sesja i uprawnienia | ✅ zrobione | `auth.php`, `index.php` |
| 3. Logowanie i kody | ✅ zrobione | login/logout/weryfikacja/aktywacja + throttling |
| 4. Szkielet panelu | ✅ zrobione | `admin/_layout.php`, dashboard |
| 5. Użytkownicy | ✅ zrobione | grupa `firma` + przycisk generowania kodu |
| 6. Materiały (slajdy) | ✅ zrobione | tylko slajdy, upload, kolejność, podgląd |
| 7. API i JavaScript | ✅ zrobione | `api/kod.php`, `assets/app.js` |
| 8. Warstwa wizualna | ✅ zrobione | `assets/style.css` |
| 9. Testy | ✅ zrobione | 45/45 zielonych (`php tools/testy.php`) |
| 10. Dokumentacja | ✅ zrobione | `README.md`, `AGENTS.md`, `plan.md` |

**Wykonano:** 96 / 96 zadań

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
