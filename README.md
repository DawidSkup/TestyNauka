# TestyNauka

Panel administracyjny do zarządzania materiałami szkoleniowymi.

Materiały dodaje się **wyłącznie w postaci slajdów**. Panel posiada logowanie
z podziałem na dwie grupy użytkowników: **grupę firmy** (zarządzanie kontami
i kodami) oraz **grupę materiałów** (dodawanie i edycja slajdów).

## Wymagania techniczne

- **Baza danych:** SQLite (plik `data/app.sqlite`, tworzony automatycznie)
- **Technologie:** PHP + HTML + CSS + JS (bez frameworków)
- **Środowisko:** Laragon (PHP 8.1+), e-maile odbiera Mailpit na `http://127.0.0.1:8025`

## Dokumentacja projektu

| Plik | Opis |
|------|------|
| [`AGENTS.md`](AGENTS.md) | Specyfikacja wymagań: grupy, uprawnienia, model danych, bezpieczeństwo |
| [`plan.md`](plan.md) | Plan realizacji z podziałem na fazy i statusami zadań |

## Uruchomienie

1. Umieść katalog w `C:\laragon\www\TestNauka`.
2. Otwórz `http://localhost/testnauka/` – baza i katalog na pliki tworzą się same.
3. Zaloguj się kontem startowym:

| E-mail | Hasło | Grupa | Logowanie |
|---------|-------|-------|-----------|
| `admin@firma.pl` | `Admin123!` | `firma` | zakładka „Administrator” |
| `marketing@firma.pl` | `Marketing123!` | `materialy` | zakładka „Użytkownik” (kod z e-maila) |
| `pracownik@firma.pl` | `Pracownik123!` | `materialy` | zakładka „Użytkownik” (kod z e-maila) |

4. Skrót do panelu: `http://localhost/testnauka/admin/`.

## Funkcje

- **Logowanie w dwóch trybach** – na stronie logowania wybór:
  - zakładka **Administrator** (e-mail + hasło) – dla kont grupy `firma`,
  - zakładka **Użytkownik** (tylko 6-znakowy kod z e-maila) – dla kont grupy `materialy`.
- Sesja, ochrona CSRF i blokada po 5 błędnych próbach (10 minut) w obu trybach.
- Dwie grupy z ograniczonym dostępem (`firma` / `materialy`), brak dostępu → 403.
- **Administrator nie zakłada kont** – wysyła kod rejestracyjny na e-mail, a odbiorca
  zakłada konto samodzielnie w `rejestracja.php` (e-mail + kod + hasło). Imię i nazwisko
  są opcjonalne przy rejestracji.
- **Administrator może usuwać konta użytkowników** (z panelu `Użytkownicy`); administrator
  nie może usunąć innego administratora ani własnego konta; usuwanie trwale usuwa konto,
  kody, slajdy i wyniki testów
- **Konta grupy firmy z wymogiem kodu** – administrator może włączyć dla konta flagę
  „Wymaga kodu” (przycisk w sekcji `Użytkownicy`). Takie konto **nie zaloguje się**
  bez kodu wysłanego przez administratora; po zużyciu kodu wymóg obowiązuje przy
  każdym kolejnym logowaniu.
- Przycisk **„Generuj i wyślij kod”** – 6-znakowy kod z cyfr i wielkich liter,
  wysyłany na e-mail, ważny 15 minut, jednorazowy.
- Dodawanie i edycja slajdów: tytuł, treść, obraz, układ, przycisk CTA,
  status publikacji, kolejność.
- Podgląd slajdów w trybie prezentacji (nawigacja strzałkami).
- **Testy wiedzy** – pytania z jedną lub kilkoma poprawnymi odpowiedziami. Administrator
  oznacza poprawne odpowiedzi w edycji testu, a użytkownik przy pytaniu wielokrotnego
  wyboru zaznacza wszystkie pasujące odpowiedzi. Wynik zapisuje się od razu po zakończeniu.
- **Odblokowywanie krok po kroku** – prezentacja 1 jest dostępna od razu. Prezentacja N,
  a potem test N odblokowują się naprzemiennie: rozwiązanie testu N-1 odblokowuje
  prezentację N, a obejrzenie prezentacji N odblokowuje test N. Zablokowane karty pokazują
  odpowiedni komunikat i przycisk prowadzący do poprzedniego kroku.

## Jak dodać nowego użytkownika

1. Zaloguj się jako administrator (grupa `firma`).
2. W sekcji `Użytkownicy` wpisz **e-mail odbiorcy** i wybierz **grupę**
   (`firma` / `materialy`), po czym nacisz **„Generuj kod i wyślij”**.
3. Na wskazany adres leci e-mail z 6-znakowym kodem i linkiem do rejestracji.
4. Odbiorca wchodzi na `rejestracja.php`, wpisuje e-mail, kod, imię i hasło –
   konto powstaje od razu aktywne, w grupie wskazanej w zaproszeniu.
5. Przy kolejnych logowaniach konto grupy `materialy` wpisuje **tylko kod** z e-maila
   (zakładka „Użytkownik”), a konto grupy `firma` – e-mail i hasło.

Sekcja `Użytkownicy` pokazuje też listę zaproszeń (status, termin ważności) z opcjami
**„Wyślij ponownie”** i **„Anuluj”**, oraz przycisk **„Generuj i wyślij kod”**,
którym administrator wysyła kod logowania do istniejącego konta.

Lista kont jest podzielona na dwie sekcje:

- **Administratorzy** – konta grupy `firma` (logowanie e-mail + hasło),
- **Użytkownicy** – konta grupy `materialy` (logowanie samym kodem z e-maila).

Zmiana grupy w tabeli (lista rozwijana „Grupa”) od razu przenosi konto do drugiej listy.

## Struktura

```
TestNauka/
├── config.php      # stałe konfiguracyjne
├── db.php          # PDO + schemat SQLite + dane startowe
├── helpers.php     # escape, CSRF, flash, generator kodu, e-mail
├── auth.php        # sesja, uprawnienia grupy, kody jednorazowe, zaproszenia
├── login.php        # logowanie: Administrator (e-mail + hasło) / Użytkownik (kod z e-maila)
├── logout.php / weryfikacja.php / aktywacja.php
├── rejestracja.php # zakładanie własnego konta kodem rejestracyjnym
├── admin/          # panel: _layout, index, uzytkownicy, materialy, slajd_form, podglad
├── api/kod.php     # AJAX: generuj i wyślij kod
├── assets/         # style.css + app.js
├── tools/          # testy integracyjne i skrypty pomocnicze
└── data/           # baza SQLite + uploady (ignorowane w repo)
```

## Konto administratora bez kodu

Jeśli potrzebujesz konta w grupie `firma` **bez przechodzenia przez kod weryfikacyjny**
(np. konto właściciela aplikacji), skrypt `tools/utworz_admina.php` zakłada lub resetuje
konto z poziomu linii poleceń, unieważniając przy tym niewykorzystane kody, żeby logowanie
nie wymagało kodu:

```bash
php tools\utworz_admina.php                                     # tryb interaktywny
php tools\utworz_admina.php admin@firma.pl NoweHaslo123         # e-mail + hasło
php tools\utworz_admina.php admin@firma.pl NoweHaslo123 "Jan Kowalski"
```

Skrypt przy istniejącym koncie resetuje hasło, ustawia grupę `firma` i przywraca
status aktywny/aktywowany.

Pozostałe konta zakładają się **wyłącznie** przez kod rejestracyjny (patrz sekcja
„Jak dodać nowego użytkownika”).

## Konto użytkownika logującego się kodem

Konto z grupy `materialy` loguje się **tylko 6-znakowym kodem z e-maila** (zakładka
„Użytkownik” na stronie logowania). Konto zakłada się skryptem
`tools/utworz_uzytkownika.php`:

```bash
php tools\utworz_uzytkownika.php pracownik@firma.pl Pracownik123! "Jan Kowalski" materialy
```

Konto powstaje aktywne i aktywowane w grupie `materialy`:

- logowanie e-mailem i hasłem → **odrzucone** („To konto nie ma uprawnień administratora –
  zaloguj się jako użytkownik, kodem z e-maila”),
- administrator w panelu (`Użytkownicy` → „Generuj i wyślij kod”) wysyła kod na e-mail konta,
- użytkownik wpisuje kod na stronie logowania (zakładka „Użytkownik”) i wchodzi do panelu,
- kod jest jednorazowy (15 minut) – do kolejnego logowania potrzebny jest nowy kod
  od administratora.

## Konto administratora wymagające kodu

Dla konta grupy `firma` administrator może włączyć flagę `wymaga_kod` (przycisk
„Wymaga kodu” / „Bez wymogu kodu” w sekcji `Użytkownicy`). Takie konto loguje się
e-mailem i hasłem, ale **dodatkowo** musi podać kod wysłany przez administratora
(ekran `weryfikacja.php`). Po zużyciu kodu wymóg obowiązuje przy każdym kolejnym logowaniu,
a konto nie może wygenerować sobie kodu samodzielnie (przycisk na panelu startowym jest
ukryty i odrzucany po stronie serwera).

## Testy

```bash
# 1. serwer deweloperski
php -S 127.0.0.1:8099 -t C:\laragon\www\TestNauka

# 2. testy integracyjne (110 sprawdzeń: HTTP + baza + Mailpit)
php tools/testy.php
```

Testy obejmują wybór trybu logowania (Administrator = e-mail + hasło, Użytkownik = sam kod),
odrzucanie konta z niewłaściwej grupy, logowanie kodem z e-maila (w tym jednorazowość kodu),
blokadę 403, generowanie i wysyłkę kodu (sprawdzany jest kod w Mailpit), format i ważność
kodu, przepływ „kod rejestracyjny → rejestracja → logowanie”, konto administratora
z wymogiem kodu, dodawanie slajdów z obrazem, walidację uploadu, ochronę CSRF, aktywację
konta oraz throttling logowania.
Skrypt na początku resetuje bazę, więc przebieg jest powtarzalny.

Pomocnicze skrypty (wymagają uruchomionego serwera):

```bash
php tools/kody.php      # aktywne kody i zaproszenia w bazie
php tools/stan.php      # stan bazy (konta, zaproszenia, slajdy)
php tools/manual.php    # ręczna kontrola: kod -> rejestracja -> logowanie kodem
php tools/manual_kod.php # ręczna kontrola logowania kodem (konto grupy materiałów)
php tools/mailpit.php   # ostatnie wiadomości w Mailpit
php tools\utworz_admina.php     # konto administratora bez kodu
php tools\utworz_uzytkownika.php # konto użytkownika (logowanie kodem)
```
