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

| E-mail | Hasło | Grupa |
|---------|-------|-------|
| `admin@firma.pl` | `Admin123!` | `firma` |
| `marketing@firma.pl` | `Marketing123!` | `materialy` |

4. Skrót do panelu: `http://localhost/testnauka/admin/`.

## Funkcje

- Logowanie e-mail + hasło z sesją, ochroną CSRF i blokadą po 5 błędnych próbach.
- Dwie grupy z ograniczonym dostępem (`firma` / `materialy`), brak dostępu → 403.
- **Administrator nie zakłada kont** – wysyła kod rejestracyjny na e-mail, a odbiorca
  zakłada konto samodzielnie w `rejestracja.php` (e-mail + kod + imię + hasło).
- Przycisk **„Generuj i wyślij kod”** – 6-znakowy kod z cyfr i wielkich liter,
  wysyłany na e-mail, ważny 15 minut, jednorazowy.
- Dodawanie i edycja slajdów: tytuł, treść, obraz, układ, przycisk CTA,
  status publikacji, kolejność.
- Podgląd slajdów w trybie prezentacji (nawigacja strzałkami).

## Jak dodać nowego użytkownika

1. Zaloguj się jako administrator (grupa `firma`).
2. W sekcji `Użytkownicy` wpisz **e-mail odbiorcy** i wybierz **grupę**
   (`firma` / `materialy`), po czym nacisz **„Generuj kod i wyślij”**.
3. Na wskazany adres leci e-mail z 6-znakowym kodem i linkiem do rejestracji.
4. Odbiorca wchodzi na `rejestracja.php`, wpisuje e-mail, kod, imię i hasło –
   konto powstaje od razu aktywne, w grupie wskazanej w zaproszeniu.

Sekcja `Użytkownicy` pokazuje też listę zaproszeń (status, termin ważności) z opcjami
**„Wyślij ponownie”** i **„Anuluj”**.

## Struktura

```
TestNauka/
├── config.php      # stałe konfiguracyjne
├── db.php          # PDO + schemat SQLite + dane startowe
├── helpers.php     # escape, CSRF, flash, generator kodu, e-mail
├── auth.php        # sesja, uprawnienia grupy, kody jednorazowe, zaproszenia
├── login.php / logout.php / weryfikacja.php / aktywacja.php
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

## Testy

```bash
# 1. serwer deweloperski
php -S 127.0.0.1:8099 -t C:\laragon\www\TestNauka

# 2. testy integracyjne (50 sprawdzeń: HTTP + baza + Mailpit)
php tools/testy.php
```

Testy obejmują logowanie dla obu grup, blokadę 403, generowanie i wysyłkę kodu
(sprawdzany jest kod w Mailpit), format i ważność kodu, przepływ
„kod rejestracyjny → rejestracja → logowanie”, dodawanie slajdów z obrazem,
walidację uploadu, ochronę CSRF, aktywację konta oraz throttling logowania.
Skrypt na początku resetuje bazę, więc przebieg jest powtarzalny.

Pomocnicze skrypty (wymagają uruchomionego serwera):

```bash
php tools/kody.php      # aktywne kody i zaproszenia w bazie
php tools/stan.php      # stan bazy (konta, zaproszenia, slajdy)
php tools/manual.php    # ręczna kontrola: kod -> rejestracja -> logowanie
php tools/mailpit.php   # ostatnie wiadomości w Mailpit
php tools\utworz_admina.php   # konto administratora bez kodu
```
