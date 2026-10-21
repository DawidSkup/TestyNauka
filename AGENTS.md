# TestNauka – panel administracyjny

## 1. Wymagania techniczne

- **Baza danych:** SQLite (plik `data/app.sqlite`, tworzony automatycznie przy starcie).
- **Technologie:** PHP + HTML + CSS + JS (bez frameworków, bez Composera).
- **Wersje:** PHP 8.1+, SQLite 3, przeglądarki z ES6.
- **Dostęp:** panel uruchamiany lokalnie w Laragonie (`http://localhost/testnauka/`).
- **Dodatki:** PDO z `ERRMODE_EXCEPTION`, sesje, wysyłka e-mail przez `mail()` (w Laragonie
  wiadomości odbiera Mailpit na `http://127.0.0.1:8025`).

## 2. Logowanie do panelu administracyjnego

Panel jest zabezpieczony logowaniem (e-mail + hasło). Po poprawnym zalogowaniu tworzona jest
sesja z regeneracją ID; wylogowanie kasuje sesję.

- **Grupy użytkowników (2):**

  | Grupa          | Zakres odpowiedzialności                                                        |
  |----------------|----------------------------------------------------------------------------------|
  | `firma`        | Zarządzanie użytkownikami i kodami, wgląd w statystyki, dostęp do materiałów     |
  | `materialy`    | Dodawanie i edycja materiałów – **wyłącznie w postaci slajdów**                |

- **Zakres sekcji w panelu:**

  - **Grupa firmy** – `Użytkownicy`: **wysyłka kodu rejestracyjnego** (administrator nie zakłada
    kont – podaje tylko e-mail i grupę), lista zaproszeń (status, wygaśnięcie, ponowna wysyłka,
    anulowanie), lista istniejących kont, przypisanie grupy, blokada konta, reset hasła oraz
    **przycisk „Generuj i wyślij kod”** (kod logowania 2FA na e-mail użytkownika).
  - **Grupa materiałów** – `Materiały`: dodawanie, edycja, układanie, publikowanie
    i podgląd slajdów. Użytkownik tej grupy **nie ma dostępu** do sekcji `Użytkownicy`.
- **Panel startowy** – statystyki (liczba użytkowników, slajdów, aktywnych kodów i oczekujących
  zaproszeń) oraz skróty do sekcji właściwych dla zalogowanej grupy.

## 2a. Zakładanie kont przez kod rejestracyjny

Administrator **nie tworzy kont użytkowników** – wysyła jedynie kod na adres e-mail,
a odbiorca zakłada konto samodzielnie:

1. Administrator w sekcji `Użytkownicy` wpisuje **e-mail odbiorcy** i wybiera **grupę**
   (`firma` / `materialy`), po czym naciska **„Generuj kod i wyślij”**.
2. Na wskazany adres leci e-mail z **6-znakowym kodem rejestracyjnym** oraz linkiem do
   strony `rejestracja.php`. Kod jest ważny 15 minut i jednorazowy.
3. Odbiorca wchodzi na `rejestracja.php` i podaje: e-mail, kod, imię i nazwisko oraz hasło
   (z potwierdzeniem).
4. Konto powstaje **aktywne i aktywowane**, w grupie wskazanej w zaproszeniu, a użytkownik
   zostaje zalogowany.

Dodatkowe reguły:

- istniejące zaproszenia dla tego samego e-maila są unieważniane przy wysłaniu nowego kodu,
- zaproszenie dla adresu, który ma już konto, jest odrzucane z czytelnym komunikatem,
- konsumpcja kodu i utworzenie konta odbywają się w jednej transakcji (brak wyścigu),
- 5 błędnych prób rejestracji blokuje sesję na 10 minut (throttling),
- administrator może zaproszenie anulować albo wysłać ponownie (generuje się nowy kod).

## 3. Materiały – wyłącznie slajdy

Materiały **nie mogą** być dodawane jako dokumenty (PDF, DOCX, XLSX itd.). Jedyną dopuszczalną
formą materiału jest **slajd** składający się z:

- tytułu (wymagany),
- treści / notatek,
- obrazka (opcjonalnie, JPG/PNG/GIF/WEBP, maks. 5 MB, nazwa losowa w `data/uploads/`),
- układu slajdu: `tekst-obraz`, `obraz-tekst`, `tylko-tekst`, `cytat`,
- etykiety i odnośnika przycisku (CTA),
- statusu: `szkic` albo `opublikowany`,
- kolejności na liście materiałów (zmiana kolejności + przełącznik publikacji).

Dodatkowo: podgląd slajdów w trybie prezentacji (sterowanie strzałkami na klawiaturze).

## 4. Generowanie i wysyłka kodu na e-mail

- **Przycisk „Generuj i wyślij kod”** w sekcji `Użytkownicy` (dla wybranego konta)
  oraz w panelu startowym (wysyłka kodu na własny adres e-mail).
- **Przycisk „Generuj kod i wyślij”** w sekcji `Użytkownicy` dla **nowego** e-maila –
  wysyła kod rejestracyjny (cel `rejestracja`) pozwalający odbiorcy założyć konto.
- **Format kodu:** 6 znaków – **wielkie litery (A–Z) oraz cyfry (0–9)**, losowo generowany
  (alfabet bez znaków mylących `I`, `O`, `0`, `1`, np. `K7M2QX`); kod zawsze zawiera
  co najmniej jedną cyfrę i jedną wielką literę.
- **Ważność kodu:** 15 minut; poprzedni niewykorzystany kod danego celu jest unieważniany
  przy generowaniu nowego.
- **Cele kodu:** `logowanie` (dodatkowe potwierdzenie przy logowaniu), `aktywacja`
  (reset hasła przez istniejące konto) oraz `rejestracja` (kod zaproszenia w tabeli `zaproszenia`).
- **Wysyłka:** e-mail HTML z tematem i treścią w UTF-8; informacja o błędzie wysyłki
  zapisywana w tabeli `log`. Kod jest jednorazowy – po poprawnym użyciu oznaczany jako zużyty.
- **Bezpieczeństwo:** kody weryfikacyjne przechowywane są w bazie, wysyłka nie ujawnia kodu
  w logach aplikacji poza przypadkiem błędu wysyłki.

## 5. Model danych (SQLite)

| Tabela  | Kolumny                                                                                       |
|---------|-----------------------------------------------------------------------------------------------|
| `users` | `id`, `imie`, `email` (UNIQUE), `haslo_hash`, `grupa`, `aktywny`, `aktywowany`, `created_at`, `updated_at` |
| `kody`  | `id`, `user_id` → `users`, `kod`, `cel`, `wygasa_at`, `uzyty_at`, `wyslany_at`, `created_at`        |
| `zaproszenia` | `id`, `email`, `grupa`, `kod`, `wygasa_at`, `uzyty_at`, `utworzony_przez` → `users`, `created_at` |
| `slajdy`| `id`, `tytul`, `tresc`, `obraz`, `uklad`, `przycisk`, `link`, `status`, `kolejnosc`, `autor_id` → `users`, `created_at`, `updated_at` |
| `log`   | `id`, `user_id`, `akcja`, `szczegoly`, `created_at`                                            |

- Hasła: `password_hash()` / `password_verify()` (`PASSWORD_DEFAULT`).
- Kaskady: `kody.user_id` → `users` (`ON DELETE CASCADE`), `slajdy.autor_id` → `users` (`SET NULL`),
  `zaproszenia.utworzony_przez` → `users` (`SET NULL`).

## 6. Struktura plików

```
TestNauka/
├── AGENTS.md            # ten dokument (specyfikacja)
├── config.php           # stałe konfiguracyjne
├── db.php               # połączenie PDO + schemat + dane startowe
├── helpers.php          # helpery (escape, CSRF, flash, kod, e-mail)
├── auth.php             # sesja, uprawnienia grupy, kody jednorazowe
├── index.php            # przekierowanie do panelu / logowania
├── login.php            # formularz logowania
├── logout.php
├── weryfikacja.php      # weryfikacja kodu po zalogowaniu
├── aktywacja.php        # aktywacja konta kodem (istniejące konto)
├── rejestracja.php      # rejestracja kodem rejestracyjnym (zakładanie własnego konta)
├── admin/
│   ├── _layout.php       # wspólny nagłówek/stopka panelu (menu wg grupy)
│   ├── index.php        # panel startowy (statystyki)
│   ├── uzytkownicy.php  # grupa firmy: wysyłka kodów rejestracyjnych + konta
│   ├── materialy.php    # grupa materiałów: lista i zarządzanie slajdami
│   ├── slajd_form.php   # dodawanie / edycja slajdu
│   └── podglad.php      # prezentacja slajdów
├── api/
│   └── kod.php          # AJAX: generuj i wyślij kod
├── assets/
│   ├── style.css
│   └── app.js
├── tools/               # skrypty pomocnicze i testy integracyjne
│   ├── testy.php        # 50 testów HTTP + baza (php tools/testy.php)
│   ├── kody.php         # podgląd aktywnych kodów i zaproszeń w bazie
│   ├── stan.php         # podgląd stanu bazy (konta, zaproszenia, slajdy)
│   ├── manual.php       # ręczna kontrola przepływu kod -> rejestracja -> logowanie
│   ├── mailpit.php      # podgląd wiadomości w Mailpit
│   └── utworz_admina.php # konto administratora bez kodu (CLI)
└── data/                # baza SQLite + pliki uploadowane (poza repo)
```

## 7. Zasady bezpieczeństwa

1. Każdy formularz POST zawiera token CSRF (`csrf_token()` / `csrf_require()`).
2. Wszystkie dane wstawiane do HTML przechodzą przez `e()` (`htmlspecialchars`).
3. Sesja: `httponly` + `samesite=Lax`, `session_regenerate_id(true)` przy logowaniu.
4. Dostęp do sekcji sterowany grupą (`require_group()`), brak dostępu → 403.
5. Upload: biała lista typów MIME, limit rozmiaru, losowa nazwa pliku, brak wykonywania.
6. Zapytania SQL wyłącznie z parametrami wiązanymi (PDO prepared statements).
7. Brak ujawniania haseł i kodów w logach aplikacji.
8. Throttling logowania: 5 błędnych próbl blokuje sesję na 10 minut (analogicznie rejestracja).
9. Kod rejestracyjny jest jednorazowy i powiązany z konkretnym adresem e-mail – nie pozwala
   założyć konta dla innego adresu niż ten, na który został wysłany.

## 8. Uruchomienie

1. Umieść katalog w `C:\laragon\www\TestNauka`.
2. Otwórz `http://localhost/testnauka/` – baza i katalog na pliki tworzą się same.
3. Konta startowe (hasła zmieniane po pierwszym logowaniu):

| E-mail              | Hasło            | Grupa      |
|----------------------|------------------|------------|
| `admin@firma.pl`     | `Admin123!`      | `firma`    |
| `marketing@firma.pl` | `Marketing123!`  | `materialy`|

4. Test wysyłki e-maili: Mailpit → `http://127.0.0.1:8025`.

## 9. Kryteria akceptacji

- [x] Logowanie e-mail + hasło działa, błędne dane pokazują czytelny komunikat.
- [x] Użytkownik grupy `firma` widzi sekcję `Użytkownicy`; użytkownik grupy `materialy` nie.
- [x] Użytkownik grupy `materialy` nie ma dostępu do strony `admin/uzytkownicy.php` (403).
- [x] Przycisk „Generuj i wyślij kod” tworzy 6-znakowy kod z cyfr i wielkich liter
      i wysyła go na e-mail użytkownika.
- [x] Kod wygasa po 15 minutach i działa tylko raz.
- [x] Materiały dodaje się wyłącznie jako slajdy – brak uploadu dokumentów.
- [x] Slajd da się edytować, opublikować, przesunąć i obejrzeć w podglądzie.
- [x] Administrator **nie zakłada kont** – sekcja `Użytkownicy` wysyła jedynie kod rejestracyjny
      na e-mail (bez tworzenia rekordu w tabeli `users`).
- [x] Odbiorca kodu zakłada konto samodzielnie w `rejestracja.php` (e-mail + kod + hasło),
      a konto od razu działa w grupie wskazanej w zaproszeniu.
- [x] Kod rejestracyjny jest jednorazowy, wygasa po 15 minutach i nie działa dla innego e-maila.

Status realizacji: **wszystkie kryteria spełnione** — potwierdzone testem integracyjnym
`php tools/testy.php` (50/50 testów zielonych).
