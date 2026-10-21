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

- Logowanie e-mail + hasło z sesją i ochroną CSRF.
- Dwie grupy z ograniczonym dostępem (`firma` / `materialy`), brak dostępu → 403.
- Przycisk **„Generuj i wyślij kod”** – 6-znakowy kod z cyfr i wielkich liter,
  wysyłany na e-mail, ważny 15 minut, jednorazowy.
- Dodawanie i edycja slajdów: tytuł, treść, obraz, układ, przycisk CTA,
  status publikacji, kolejność.
- Podgląd slajdów w trybie prezentacji (nawigacja strzałkami).
