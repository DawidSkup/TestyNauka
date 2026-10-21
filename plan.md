# TestNauka – plan realizacji projektu

> Dokument roboczy. Statusy aktualizowane na bieżąco.
> Legenda: `[x]` zrobione · `[ ]` do zrobienia · `[~]` w trakcie · `[!]` problem/ryzyko

**Specyfikacja projektu:** [`AGENTS.md`](AGENTS.md) · **Data startu:** 21.10.2026

---

## Faza 0 – Specyfikacja

- [x] Wymagania techniczne (SQLite, PHP + HTML + CSS + JS) zapisane w `AGENTS.md`
- [x] Opis logowania do panelu administracyjnego
- [x] Podział na 2 grupy: `firma` (zarządzanie) i `materialy` (slajdy)
- [x] Wymaganie materiałów wyłącznie w postaci slajdów
- [x] Wymaganie kodu: 6 znaków (cyfry + wielkie litery) wysyłanego e-mailem
- [x] Model danych, struktura plików, zasady bezpieczeństwa, kryteria akceptacji

---

## Faza 1 – Fundament aplikacji

- [ ] `config.php` – stałe: ścieżka bazy, katalog uploadów, limit i dozwolone typy obrazów, długość kodu, TTL kodu, dane nadawcy e-mail, nazwa sesji, definicja grup
- [ ] `db.php` – połączenie PDO (tryb wyjątków, `PRAGMA foreign_keys`, WAL)
- [ ] `db.php` – migracja: tworzenie tabel `users`, `kody`, `slajdy`, `log`
- [ ] `db.php` – dane startowe (konto administratora + konto grupy materiałów)
- [ ] `helpers.php` – `e()` (escape HTML), `fmt_date()`, `fmt_remaining()`
- [ ] `helpers.php` – sesja, token CSRF (`csrf_token()`, `csrf_field()`, `csrf_valid()`, `csrf_require()`)
- [ ] `helpers.php` – komunikaty flash, `redirect()`
- [ ] `helpers.php` – `generate_code()` – 6 losowych znaków z `A–Z` i `0–9`
- [ ] `helpers.php` – `send_email()` – e-mail HTML UTF-8 przez `mail()`
- [ ] `helpers.php` – `log_action()` – zapis zdarzeń do tabeli `log`

## Faza 2 – Sesja, uprawnienia i kody

- [ ] `auth.php` – `current_user()`, `is_logged_in()`, `require_login()`
- [ ] `auth.php` – `in_group()`, `require_group()` (brak dostępu → 403)
- [ ] `auth.php` – `login_user()` z regeneracją ID sesji, `logout_user()`
- [ ] `auth.php` – `create_code()` (unieważnia poprzedni kod, zapisuje wygaśnięcie)
- [ ] `auth.php` – `active_code()`, `consume_code()` (jednorazowość kodu)
- [ ] `index.php` – przekierowanie do panelu lub logowania

## Faza 3 – Logowanie i weryfikacja kodem

- [ ] `login.php` – formularz e-mail + hasło, walidacja, komunikaty błędów
- [ ] `logout.php` – zakończenie sesji
- [ ] `weryfikacja.php` – weryfikacja kodu po poprawnym haśle (cel `logowanie`)
- [ ] `aktywacja.php` – aktywacja konta kodem (cel `aktywacja`) + ustawienie hasła
- [ ] Blokada po 5 błędnych próbach logowania (throttling)

## Faza 4 – Szkielet panelu

- [ ] `admin/_layout.php` – nagłówek, menu zależne od grupy, stopka, komunikaty flash
- [ ] `admin/index.php` – dashboard: statystyki (użytkownicy, slajdy, aktywne kody)
- [ ] `admin/index.php` – skróty do sekcji właściwych dla grupy
- [ ] `admin/index.php` – przycisk „Wyślij kod na mój e-mail” (self-service)

## Faza 5 – Użytkownicy (tylko grupa `firma`)

- [ ] `admin/uzytkownicy.php` – lista użytkowników: imię, e-mail, grupa, status, ostatni kod
- [ ] `admin/uzytkownicy.php` – dodanie użytkownika (e-mail, imię, grupa, hasło startowe)
- [ ] `admin/uzytkownicy.php` – zmiana grupy, blokada/wyłączenie konta
- [ ] `admin/uzytkownicy.php` – reset hasła przez administratora
- [ ] `admin/uzytkownicy.php` – **przycisk „Generuj i wyślij kod”** per użytkownik
- [ ] `admin/uzytkownicy.php` – wysyłka kodu na e-mail wybranego użytkownika
- [ ] `admin/uzytkownicy.php` – wyświetlenie wygenerowanego kodu i czasu ważności
- [ ] Ochrona strony `require_group(GROUP_FIRMA)` – 403 dla grupy `materialy`

## Faza 6 – Materiały: wyłącznie slajdy

- [ ] `admin/materialy.php` – lista slajdów: miniaturka, tytuł, status, kolejność, autor
- [ ] `admin/materialy.php` – dodanie / edycja / usuwanie slajdu
- [ ] `admin/materialy.php` – zmiana kolejności slajdów (góra/dół)
- [ ] `admin/materialy.php` – przełącznik publikacji (`szkic` / `opublikowany`)
- [ ] `admin/slajd_form.php` – formularz: tytuł, treść, obraz, układ, CTA (etykieta + link)
- [ ] `admin/slajd_form.php` – walidacja (tytuł wymagany, dozwolone typy obrazów, limit 5 MB)
- [ ] `admin/slajd_form.php` – zapis obrazu pod losową nazwą w `data/uploads/`
- [ ] Usunięcie pliku obrazu przy kasowaniu slajdu
- [ ] `admin/podglad.php` – prezentacja slajdów (nawigacja strzałkami, licznik)
- [ ] Brak możliwości dodania materiału w innej postaci niż slajd

## Faza 7 – API i JavaScript

- [ ] `api/kod.php` – endpoint AJAX: generuje kod i wysyła e-mail, zwraca JSON
- [ ] `api/kod.php` – walidacja CSRF, sesji i grupy (`firma`)
- [ ] `assets/app.js` – wysyłka formularza kodu przez `fetch`, obsługa błędów
- [ ] `assets/app.js` – kopiowanie kodu do schowka
- [ ] `assets/app.js` – potwierdzenie przy kasowaniu (`data-confirm`)
- [ ] `assets/app.js` – nawigacja w podglądzie slajdów (strzałki, spacja, Esc)

## Faza 8 – Warstwa wizualna

- [ ] `assets/style.css` – zmienne, reset, typografia
- [ ] `assets/style.css` – layout panelu: sidebar + treść, responsywność (mobile)
- [ ] `assets/style.css` – komponenty: karty, tabele, formularze, przyciski, badge'e
- [ ] `assets/style.css` – komunikaty flash, warianty przycisków (primary/ghost/danger)
- [ ] `assets/style.css` – ekran logowania, podgląd slajdów (tryb prezentacji)

## Faza 9 – Testy i weryfikacja

- [ ] Uruchomienie serwera PHP i sprawdzenie braku błędów w logu
- [ ] Test logowania dla obu grup (`admin@firma.pl`, `marketing@firma.pl`)
- [ ] Test blokady: grupa `materialy` nie wchodzi do `admin/uzytkownicy.php` → 403
- [ ] Test przycisku „Generuj i wyślij kod” + odbiór wiadomości w Mailpit
- [ ] Test formatu kodu (6 znaków, wielkie litery i cyfry) – walidacja regexem
- [ ] Test ważności kodu (15 min) i jednorazowości
- [ ] Test dodania slajdu z obrazem, edycji, publikacji i podglądu
- [ ] Test walidacji uploadu (zły typ pliku, za duży plik)
- [ ] Test CSRF: formularz bez tokenu jest odrzucany
- [ ] Test CSRF w API `api/kod.php`

## Faza 10 – Dokumentacja

- [ ] `README.md` – opis instalacji, konta startowe, testy e-mail
- [ ] Aktualizacja `AGENTS.md` po zmianach w modelu danych lub strukturze
- [ ] Aktualizacja `plan.md` – zaznaczenie zakończonych pozycji

---

## Podsumowanie postępu

| Faza | Status | Uwagi |
|------|--------|-------|
| 0. Specyfikacja | ✅ zrobione | `AGENTS.md` |
| 1. Fundament | ⬜ do zrobienia | |
| 2. Sesja i uprawnienia | ⬜ do zrobienia | |
| 3. Logowanie i kody | ⬜ do zrobienia | |
| 4. Szkielet panelu | ⬜ do zrobienia | |
| 5. Użytkownicy | ⬜ do zrobienia | |
| 6. Materiały (slajdy) | ⬜ do zrobienia | |
| 7. API i JavaScript | ⬜ do zrobienia | |
| 8. Warstwa wizualna | ⬜ do zrobienia | |
| 9. Testy | ⬜ do zrobienia | |
| 10. Dokumentacja | ⬜ do zrobienia | |

**Wykonano:** 6 / 62 zadań
