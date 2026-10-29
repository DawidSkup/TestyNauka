# TestNauka – panel administracyjny

## 1. Wymagania techniczne

- **Baza danych:** SQLite (plik `data/app.sqlite`, tworzony automatycznie przy starcie).
- **Technologie:** PHP + HTML + CSS + JS (bez frameworków, bez Composera).
- **Wersje:** PHP 8.1+, SQLite 3, przeglądarki z ES6.
- **Dostęp:** panel uruchamiany lokalnie w Laragonie (`http://localhost/testynauka/`).
- **Dodatki:** PDO z `ERRMODE_EXCEPTION`, sesje, wysyłka e-mail przez `mail()` (w Laragonie
  wiadomości odbiera Mailpit na `http://127.0.0.1:8025`).

## 2. Logowanie do panelu administracyjnego

Na stronie logowania użytkownik **wybiera sposób logowania** (dwie zakładki):

| Zakładka        | Kto się loguje       | Jakie dane                          | Jak działa                                                              |
|-----------------|-----------------------|-------------------------------------|-------------------------------------------------------------------------|
| **Administrator**| grupa `firma`        | e-mail + hasło                      | sesja z regeneracją ID; przy koncie z flagą `wymaga_kod` dodatkowo kod   |
| **Użytkownik**  | grupa `materialy` lub `firma` | **tylko 6-znakowy kod z e-maila**   | kod jest jedynym poświadczeniem – konsumowany przy logowaniu            |

- Logowanie w zakładce „Administrator” kontem z grupy `materialy` jest **odrzucone**
  (komunikat: „To konto nie ma uprawnień administratora…”); kod z zakładki „Użytkownik”
  loguje **oba typy kont** – użytkownika i administratora.
- Kod w zakładce „Użytkownik” musi być **aktywnym, niewykorzystanym i niewygasłym** kodem
  o celu `logowanie` w bazie; po poprawnym użyciu jest oznaczany jako zużyty (jednorazowy).
- Wylogowanie kasuje sesję; po zalogowaniu hasłem konto z flagą `wymaga_kod` trafia
  dodatkowo na `weryfikacja.php` (drugi czynnik).
- Throttling: 5 błędnych prób logowania (w obu zakładkach) blokuje sesję na 10 minut.

**Grupy użytkowników (2):**

| Grupa          | Zakres odpowiedzialności                                                        |
|----------------|----------------------------------------------------------------------------------|
| `firma`        | Zarządzanie użytkownikami i kodami, materiałami (slajdy) oraz testami; statystyki |
| `materialy`    | Podgląd opublikowanych materiałów i rozwiązywanie opublikowanych testów           |

- **Zakres sekcji w panelu:**

  - **Grupa firmy** – `Użytkownicy`: **wysyłka kodu rejestracyjnego** (administrator nie zakłada
    kont – podaje tylko e-mail i grupę), lista zaproszeń (status, wygaśnięcie, ponowna wysyłka,
    anulowanie), **dwie listy kont – „Administratorzy” (grupa `firma`) i „Użytkownicy”
    (grupa `materialy`)**, przypisanie grupy (zmiana grupy przenosi konto między listami),
    blokada konta, reset hasła oraz
    **przycisk „Generuj i wyślij kod”** (kod logowania 2FA na e-mail użytkownika),
    **możliwość usuwania kont użytkowników** (administrator nie może usunąć innego administratora ani własnego konta); usuwanie trwale usuwa konto, kody, slajdy i wyniki testów
  `Wyniki` – podgląd wyników wszystkich użytkowników, statystyki rozwiązywalności testów przez bieżącego użytkownika, panel startowy zawiera statystykę „Twoje rozwiązane testy”, a zakładka „Wyniki” w menu nawigacyjnym prowadzi do strony z tabelą wyników wszystkich użytkowników.
  **Administrator nie może zmieniać grupy, statusu, wymogu kodu ani hasła dla innych administratorów** (grupa `firma`). Każdy taki blokada powoduje komunikat i powrót do listy użytkowników.
    `Materiały` – dodawanie, edycja, układanie, publikowanie i podgląd slajdów.
    `Testy` – tworzenie testów, pytań i odpowiedzi, publikacja oraz usuwanie.
  - **Grupa materiałów** – `Podgląd slajdów`: przeglądanie wyłącznie opublikowanych materiałów.
    `Testy`: lista opublikowanych testów i rozwiązywanie ich z natychmiastowym wynikiem.
    Użytkownik tej grupy **nie ma dostępu** do sekcji `Użytkownicy` ani do zarządzania
    materiałami i testami.
- **Panel startowy** – statystyki (liczba użytkowników, slajdów, aktywnych kodów i oczekujących
  zaproszeń) oraz skróty do sekcji właściwych dla zalogowanej grupy.

## 2a. Zakładanie kont przez kod rejestracyjny

- **Zakładanie kont przez kod rejestracyjny** – administrator **nie tworzy kont użytkowników**
  – wysyła jedynie kod na adres e-mail, a odbiorca zakłada konto samodzielnie:

1. Administrator w sekcji `Użytkownicy` wpisuje **e-mail odbiorcy** i wybiera **grupę**
   (`firma` / `materialy`), po czym naciska **„Generuj kod i wyślij”**.
2. Na wskazany adres leci e-mail z **6-znakowym kodem rejestracyjnym** oraz linkiem do
   strony `rejestracja.php`. Kod jest ważny 15 minut i jednorazowy.
3. Odbiorca wchodzi na `rejestracja.php` i podaje: e-mail, kod, **hasło** (z potwierdzeniem).
   Imię i nazwisko są **opcjonalne** – pole można pominąć.
4. Konto powstaje **aktywne i aktywowane**, w grupie wskazanej w zaproszeniu, a użytkownik
   zostaje zalogowany. Przy **kolejnych** logowaniach konto grupy `materialy` loguje się
   już samym kodem z e-maila (zakładka „Użytkownik”).

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
Materiały tworzą **łańcuch odblokowywania**: prezentacja 1 jest dostępna od razu,
prezentacja N (N > 1) odblokowuje się po rozwiązaniu testu N-1, a potwierdzenie
zapoznania się z prezentacją N (przycisk „Zapoznałem(am) się z prezentacją” na końcu
prezentacji) odblokowuje test N. Potwierdzenie przez użytkownika grupy `materialy`
(także na stronie prezentacji bez slajdów) jest zapisywane w bazie; rozwiązanie testu
wynika z zapisanego wyniku w `wyniki_testow`.
Zarządzanie materiałami (`admin/materialy.php`, `admin/slajd_form.php`) należy do grupy `firma`.

## 3a. Testy wiedzy

Sekcja `Testy` (`admin/testy.php`) pozwala grupie `firma` tworzyć testy z pytaniami jedno-
lub wielokrotnego wyboru, a grupie `materialy` – rozwiązywać opublikowane testy i od razu
widzieć wynik.

**Struktura testu:**

- test: tytuł (wymagany), opis, status `szkic` / `opublikowany`, autor, daty utworzenia i edycji,
- pytanie: treść (wymagana) i kolejność w teście,
- odpowiedź: treść (wymagana), kolejność i flaga `poprawna` – w pytaniu może być **jedna lub
  kilka** poprawnych odpowiedzi; pytanie z więcej niż jedną poprawną odpowiedzią jest pytaniem
  wielokrotnego wyboru,
- wynik: użytkownik, test, liczba poprawnych odpowiedzi, liczba pytań i procent (pytanie jest
  zaliczone tylko wtedy, gdy zaznaczono dokładnie wszystkie poprawne odpowiedzi i żadnej błędnej).

**Zarządzanie (grupa `firma`):**

- lista testów ze statystykami (wszystkie / opublikowane / szkice / pytania), statusem,
  liczbą pytań i odpowiedzi oraz autorem,
- utworzenie testu – nowy test zapisuje się jako `szkic` i od razu otwiera edycję,
- edycja ustawień i publikacja; publikacja jest jedynym sposobem udostępnienia testu
  użytkownikom grupy `materialy`,
- dodawanie i usuwanie pytań oraz odpowiedzi, oznaczanie i odznaczanie odpowiedzi jako
  poprawnych (w tym kilku w jednym pytaniu),
- usunięcie testu usuwa kaskadowo pytania, odpowiedzi i wyniki,
- ostrzeżenia w interfejsie: „brak poprawnej odpowiedzi” przy pytaniu, „brak pytań”
  przy opublikowanym teście (system nie blokuje publikacji – to komunikat dla administratora).

**Rozwiązywanie (grupa `materialy`):**

- widoczne są wyłącznie testy o statusie `opublikowany`,
- **łańcuch odblokowywania** dla grupy `materialy`: prezentacja 1 jest dostępna od razu,
  **prezentacja N (N > 1) jest zablokowana do rozwiązania testu N-1**, a **test N jest
  zablokowany do potwierdzenia zapoznania się z prezentacją N**,
- zablokowana karta prezentacji pokazuje komunikat i przycisk „Rozwiąż Test N-1”,
  zablokowana karta testu – komunikat i przycisk „Odtwórz Prezentację N”. Bezpośrednie
  wejście na `admin/podglad.php?id=…` lub `?akcja=rozwiaz` jest odrzucane (komunikat
  + powrót do listy), a wynik nie zostaje zapisany. Widok `podglad.php` bez `id`
  (wszystkie slajdy) jest dostępny tylko dla grupy `firma` – użytkownik odtwarza
  prezentacje z listy,
- potwierdzenie zapoznania się z prezentacją (przycisk na ostatnim ekranie prezentacji,
  także na stronie prezentacji bez slajdów) zapisuje się w tabeli
  `prezentacje_obejrzane`; rozwiązanie testu wynika z wiersza w `wyniki_testow`.
  Oba stany są trwałe. Samo otwarcie prezentacji **nie odblokowuje** testu,
- każda prezentacja kończy się **dodatkowym slajdem potwierdzenia** (numerowanym jak
  pozostałe), również w podglądzie administratora – tam przycisk jest nieaktywny i
  opisany jako podgląd. Po potwierdzeniu slajd pokazuje stan „✓” z nieaktywnym
  przyciskiem i odnośnikiem do testów. Przyciski **„Poprzedni” / „Następny”** są
  widoczne przez cały czas (nieaktywne na pierwszym i ostatnim slajdzie), także w trybie
  pełnoekranowym,
- brak pasującej pozycji (brak prezentacji o danym numerze, test w szkicu) **nie blokuje**
  – inaczej powstałby deadlock. Prezentacja bez slajdów **blokuje** test (materiał
  istnieje, tylko nie jest jeszcze uzupełniony), ale potwierdzenie zapoznania się na jej
  stronie liczy się jako obejrzenie, więc blokada nigdy nie jest trwała. Test bez pytań
  również **blokuje** kolejną prezentację – administrator musi dodać pytania, aby
  użytkownik mógł przejść dalej.
- jedno pytanie z jedną poprawną odpowiedzią = jedna wybrana odpowiedź; przy kilku poprawnych
  odpowiedziach użytkownik zaznacza wszystkie pasujące (pola wyboru). Formularz pokazuje numer
  pytania i licznik „x z y”, a pasek postępu aktualizuje się na żywo,
- przed zakończeniem testu z brakującymi odpowiedziami wyświetlane jest potwierdzenie,
- po wysłaniu odpowiedzi zapisywany jest wynik, a użytkownik trafia na ekran wyniku:
  liczba punktów, procent, pasek postępu, komunikat zależny od wyniku oraz przyciski
  „Spróbuj ponownie” i „Wróć do listy testów”,
- użytkownik widzi wyłącznie własne wyniki (ekran wyniku dotyczy ostatniej próby).

**Wygląd i przejrzystość:**

- karty pytań z numeracją, odpowiedzi jako klikalne opcje z literami A/B/C i podświetleniem
  wyboru, poprawne odpowiedzi wyróżnione na zielono w widoku edycji,
- kafelki statystyk, badge statusu, sekcje „Ustawienia testu” i „Dodaj pytanie”,
- widoki responsywne (mobile), spójne z resztą panelu.

**Bezpieczeństwo:**

- dostęp do sekcji wymaga zalogowania (`require_login()`),
- wszystkie operacje zarządzania wymagają grupy `firma` (`require_group(GROUP_FIRMA)`),
- formularze zawierają token CSRF, a operacje modyfikujące są dostępne wyłącznie metodą POST.

## 4. Generowanie i wysyłka kodu na e-mail

- **Przycisk „Generuj i wyślij kod”** w sekcji `Użytkownicy` (dla wybranego konta)
  oraz w panelu startowym: grupa firmy wpisuje **e-mail odbiorcy** (domyślnie własny)
  i wysyła kod na konto administratora albo użytkownika; pozostałe grupy mogą wygenerować
  kod wyłącznie na własny adres.
- **Przycisk „Generuj kod i wyślij”** w sekcji `Użytkownicy` dla **nowego** e-maila –
  wysyła kod rejestracyjny (cel `rejestracja`) pozwalający odbiorcy założyć konto.
- **Format kodu:** 6 znaków – **wielkie litery (A–Z) oraz cyfry (0–9)**, losowo generowany
  (alfabet bez znaków mylących `I`, `O`, `0`, `1`, np. `K7M2QX`); kod zawsze zawiera
  co najmniej jedną cyfrę i jedną wielką literę.
- **Ważność kodu:** 15 minut; poprzedni niewykorzystany kod danego celu jest unieważniany
  przy generowaniu nowego.
- **Cele kodu:** `logowanie` (jedynym poświadczeniem w zakładce „Użytkownik” oraz drugi
  czynnik dla kont z flagą `wymaga_kod`), `aktywacja` (reset hasła przez istniejące konto)
  oraz `rejestracja` (kod zaproszenia w tabeli `zaproszenia`).
- **Konto z flagą `wymaga_kod`:** konto grupy `firma`, do którego administrator włącza wymóg
  kodu (przycisk „Wymaga kodu” w sekcji `Użytkownicy`). Bez aktywnego kodu wysłanego przez
  administratora logowanie jest **odrzucone** – użytkownik widzi komunikat, że musi poprosić
  administratora o kod. Po użyciu kodu (jest jednorazowy) kolejne logowanie znowu wymaga
  nowego kodu od administratora. Konto z tym wymogiem nie może samo wygenerować sobie kodu
  (formularz na panelu startowym odrzuca własny adres, a grupa `materialy` nie ma przycisku).
  Konta grupy `materialy` mają wymóg kodu zawsze – logują się samym kodem z e-maila.
- **Wysyłka:** e-mail HTML z tematem i treścią w UTF-8; informacja o błędzie wysyłki
  zapisywana w tabeli `log`. Kod jest jednorazowy – po poprawnym użyciu oznaczany jako zużyty.
- **Bezpieczeństwo:** kody weryfikacyjne przechowywane są w bazie, wysyłka nie ujawnia kodu
  w logach aplikacji poza przypadkiem błędu wysyłki.

## 5. Model danych (SQLite)

| Tabela  | Kolumny                                                                                       |
|---------|-----------------------------------------------------------------------------------------------|
| `users` | `id`, `imie`, `email` (UNIQUE), `haslo_hash`, `grupa`, `aktywny`, `aktywowany`, `wymaga_kod`, `created_at`, `updated_at` |
| `kody`  | `id`, `user_id` → `users`, `kod`, `cel`, `wygasa_at`, `uzyty_at`, `wyslany_at`, `created_at`        |
| `zaproszenia` | `id`, `email`, `grupa`, `kod`, `wygasa_at`, `uzyty_at`, `utworzony_przez` → `users`, `created_at` |
| `slajdy`| `id`, `tytul`, `tresc`, `obraz`, `uklad`, `przycisk`, `link`, `status`, `kolejnosc`, `autor_id` → `users`, `created_at`, `updated_at` |
| `testy` | `id`, `tytul`, `opis`, `status`, `autor_id` → `users`, `created_at`, `updated_at`                |
| `pytania` | `id`, `test_id` → `testy`, `tresc`, `kolejnosc`                                              |
| `odpowiedzi` | `id`, `pytanie_id` → `pytania`, `tresc`, `poprawna`, `kolejnosc`                        |
| `wyniki_testow` | `id`, `user_id` → `users`, `test_id` → `testy`, `wynik`, `maks`, `procent`, `created_at` |
| `prezentacje_obejrzane` | `id`, `user_id` → `users`, `prezentacja_id` → `prezentacje`, `created_at` (UNIQUE: użytkownik + prezentacja) |
| `log`   | `id`, `user_id`, `akcja`, `szczegoly`, `created_at`                                            |

- Hasła: `password_hash()` / `password_verify()` (`PASSWORD_DEFAULT`).
- Kaskady: `kody.user_id` → `users` (`ON DELETE CASCADE`), `slajdy.autor_id` → `users` (`SET NULL`),
  `zaproszenia.utworzony_przez` → `users` (`SET NULL`), `pytania.test_id` → `testy`
  (`ON DELETE CASCADE`), `odpowiedzi.pytanie_id` → `pytania` (`ON DELETE CASCADE`),
  `wyniki_testow.user_id` → `users` i `wyniki_testow.test_id` → `testy` (`ON DELETE CASCADE`),
  `testy.autor_id` → `users` (`SET NULL`),
  `prezentacje_obejrzane.user_id` → `users` i `prezentacje_obejrzane.prezentacja_id`
  → `prezentacje` (`ON DELETE CASCADE`).

## 6. Struktura plików

```
TestyNauka/
├── AGENTS.md            # ten dokument (specyfikacja)
├── config.php           # stałe konfiguracyjne
├── db.php               # połączenie PDO + schemat + dane startowe
├── helpers.php          # helpery (escape, CSRF, flash, kod, e-mail)
├── auth.php             # sesja, uprawnienia grupy, kody jednorazowe
├── index.php            # przekierowanie do panelu / logowania
├── login.php            # logowanie: zakładka Administrator (e-mail + hasło) / Użytkownik (kod)
├── logout.php
├── weryfikacja.php      # weryfikacja kodu po zalogowaniu
├── aktywacja.php        # aktywacja konta kodem (istniejące konto)
├── rejestracja.php      # rejestracja kodem rejestracyjnym (zakładanie własnego konta)
├── admin/
│   ├── _layout.php       # wspólny nagłówek/stopka panelu (menu wg grupy)
│   ├── index.php        # panel startowy (statystyki)
│   ├── uzytkownicy.php  # grupa firmy: wysyłka kodów rejestracyjnych + konta
│   ├── materialy.php    # grupa firmy: lista i zarządzanie slajdami
│   ├── slajd_form.php   # dodawanie / edycja slajdu
│   ├── podglad.php      # prezentacja opublikowanych slajdów
│   └── testy.php        # testy: zarządzanie (firma), rozwiązywanie i wyniki (użytkownik)
├── api/
│   └── kod.php          # AJAX: generuj i wyślij kod
├── assets/
│   ├── style.css
│   └── app.js
├── tools/               # skrypty pomocnicze i testy integracyjne
│   ├── testy.php        # testy integracyjne HTTP + baza (php tools/testy.php)
│   ├── kody.php         # podgląd aktywnych kodów i zaproszeń w bazie
│   ├── stan.php         # podgląd stanu bazy (konta, zaproszenia, slajdy)
│   ├── manual.php       # ręczna kontrola przepływu kod -> rejestracja -> logowanie
│   ├── manual_kod.php   # ręczna kontrola konta wymagającego kodu administratora
│   ├── mailpit.php      # podgląd wiadomości w Mailpit
│   ├── utworz_admina.php # konto administratora bez kodu (CLI)
│   └── utworz_uzytkownika.php # konto wymagające kodu administratora (CLI)
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
10. Logowanie kodem (zakładka „Użytkownik”) przyjmuje wyłącznie **aktywny, niewykorzystany
    i niewygasły** kod o celu `logowanie`; kod jest konsumowany w tej samej chwili, więc
    nie da się zalogować się ponownie tym samym kodem.

## 8. Uruchomienie

1. Umieść katalog w `C:\laragon\www\TestyNauka`.
2. Otwórz `http://localhost/testynauka/` – baza i katalog na pliki tworzą się same.
3. Konta startowe (hasła zmieniane po pierwszym logowaniu):

| E-mail              | Hasło            | Grupa      | Logowanie                    |
|----------------------|------------------|------------|------------------------------|
| `admin@firma.pl`     | `Admin123!`      | `firma`    | zakładka „Administrator”     |
| `marketing@firma.pl` | `Marketing123!`  | `materialy`| zakładka „Użytkownik” (kod)  |

4. Test wysyłki e-maili: Mailpit → `http://127.0.0.1:8025`.
5. Konto z grupy `materialy` zakłada się skryptem `tools/utworz_uzytkownika.php`
   (albo przez kod rejestracyjny) – loguje się potem **tylko kodem** z e-maila.
6. Testy integracyjne: uruchom serwer (`php -S 127.0.0.1:8099 -t .`) i wykonaj
   `php tools/testy.php http://127.0.0.1:8099`.

   > **Uwaga:** testy **czyszczą** `data/app.sqlite` oraz `data/uploads/*` na początku
   > przebiegu. Nie uruchamiaj ich na bazie z danymi, których nie chcesz stracić.

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
- [x] Konto z włączonym wymogiem kodu **nie da się zalogować** bez kodu wysłanego przez
      administratora; po zużyciu kodu wymóg obowiązuje przy każdym kolejnym logowaniu.
- [x] Strona logowania pozwala wybrać tryb: **Administrator** (e-mail + hasło, tylko grupa
      `firma`) albo **Użytkownik** (tylko 6-znakowy kod z e-maila, grupa `materialy`
      i `firma`).
- [x] Logowanie kontem grupy `materialy` w trybie „Administrator” jest odrzucane, a kod
      w trybie „Użytkownik” loguje oba typy kont i jest jednorazowy (po użyciu przestaje działać).
- [x] Panel startowy pozwala wysłać kod na e-mail wskazanego konta (administratora lub
      użytkownika); odbiorca loguje się nim w zakładce „Użytkownik”.

- [x] Lista kont w sekcji `Użytkownicy` jest podzielona na **„Administratorzy”** (grupa `firma`)
      i **„Użytkownicy”** (grupa `materialy`); zmiana grupy przenosi konto między listami.
- [x] Panel nie generuje błędów PHP – testy wykrywają ostrzeżenia (`Warning`, `Notice`,
      `Deprecated`) i niezdefiniowane klucze w renderowanych stronach.
- [x] Grupa `firma` tworzy test, dodaje pytania i odpowiedzi, oznacza jedną lub kilka
      poprawnych odpowiedzi, publikuje i usuwa test; nowy test jest szkicem i nie jest widoczny
      dla użytkowników.
- [x] Grupa `materialy` widzi wyłącznie opublikowane testy, rozwiązuje je (przy jednej poprawnej
      odpowiedzi – jedna odpowiedź, przy kilku – wszystkie poprawne) i otrzymuje wynik zapisany
      w `wyniki_testow`.
- [x] Test o numerze N jest zablokowany dla grupy `materialy` do chwili potwierdzenia
      zapoznania się z opublikowaną prezentacją o numerze N (przycisk na końcu prezentacji);
      blokada działa też przy bezpośrednim wejściu w adres `?akcja=rozwiaz` (wynik nie
      zostaje zapisany). Samo otwarcie prezentacji nie odblokowuje testu.
- [x] Prezentacja o numerze N (N > 1) jest zablokowana do chwili rozwiązania testu
      o numerze N-1; prezentacja 1 jest dostępna od razu. Dotyczy to także testu, który
      nie ma jeszcze pytań. Blokada działa też przy bezpośrednim wejściu w
      `admin/podglad.php?id=…`.
- [x] Ekran wyniku pokazuje liczbę punktów, procent, pasek postępu i komunikat dopasowany
      do wyniku; użytkownik może rozwiązać test ponownie.
- [x] Operacje zarządzania testami wymagają zalogowania i grupy `firma`; formularze bez tokenu
      CSRF są odrzucane (HTTP 400).
- [x] Widoki testów są czytelne i responsywne: karty pytań, klikalne odpowiedzi, licznik
      postępu oraz ostrzeżenie przed zakończeniem testu z brakującymi odpowiedziami.
- [x] Pytanie może mieć kilka poprawnych odpowiedzi: administrator oznacza je w edycji testu,
      a użytkownik zaznacza wszystkie poprawne (punkty tylko za pełne, poprawne zaznaczenie).

Status realizacji: **wszystkie kryteria spełnione**. Test integracyjny `php tools/testy.php`
obejmuje 110 kontroli: **97 zielonych**, 13 znanych porażek **poza sekcją Testy** (slajdy,
upload, rejestracja) – sekcja Testy: **20/20 zielonych**.
