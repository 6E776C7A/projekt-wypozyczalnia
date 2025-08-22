# Konfiguracja Mailera - Wypożyczalnia Samochodów

## Przegląd
System wysyła automatyczne e-maile z potwierdzeniem rezerwacji i linkiem do anulowania. Używa PHPMailer z konfiguracją SMTP.

## Konfiguracja

### 1. Edytuj plik `config/mail.php`
Zmień następujące wartości na swoje dane:

```php
'username' => 'twoj.email@gmail.com', // ZMIEŃ NA SWÓJ EMAIL
'password' => 'twoje_haslo_aplikacji', // ZMIEŃ NA HASŁO APLIKACJI GMAIL
'from_email' => 'twoj.email@gmail.com', // ZMIEŃ NA SWÓJ EMAIL
```

### 2. Wybierz dostawcę e-mail

#### Poczta Onet (domyślny - skonfigurowany)
- Host: `smtp.poczta.onet.pl`
- Port: `465`
- Szyfrowanie: `ssl`
- **WAŻNE**: Użyj swojego hasła do konta Onet!

**Konfiguracja Onet:**
1. Edytuj `config/mail.php`
2. Zmień `username` i `from_email` na swój adres Onet
3. Zmień `password` na swoje hasło do Onet
4. Upewnij się, że masz włączone SMTP w ustawieniach konta Onet

**Jak włączyć SMTP w Onet:**
1. Zaloguj się na swoje konto Onet
2. Idź do Ustawienia → Poczta → Konfiguracja
3. Włącz "Serwer SMTP"
4. Upewnij się, że port 465 jest otwarty

#### Gmail (alternatywa)
- Host: `smtp.gmail.com`
- Port: `587`
- Szyfrowanie: `tls`
- **WAŻNE**: Użyj hasła aplikacji, nie zwykłego hasła do Gmail!

#### Outlook/Hotmail
- Host: `smtp-mail.outlook.com`
- Port: `587`
- Szyfrowanie: `tls`

#### Yahoo
- Host: `smtp.mail.yahoo.com`
- Port: `587`
- Szyfrowanie: `tls`

#### Poczta Onet
- Host: `smtp.poczta.onet.pl`
- Port: `465`
- Szyfrowanie: `ssl`

### 3. Testowanie
Po konfiguracji:
1. Utwórz rezerwację samochodu
2. Sprawdź czy e-mail został wysłany
3. Sprawdź logi błędów w `error_log`

## Rozwiązywanie problemów

### Błąd "SMTP connect() failed"
- Sprawdź czy port jest otwarty w firewallu
- Sprawdź czy dane logowania są poprawne
- Sprawdź czy włączono "Mniej bezpieczne aplikacje" (Gmail)

### Błąd "Authentication failed"
- Użyj hasła aplikacji (Gmail)
- Sprawdź czy włączono SMTP w ustawieniach konta
- Sprawdź czy nie masz blokady IP

### E-mail nie dociera
- Sprawdź folder spam
- Sprawdź czy adres odbiorcy jest poprawny
- Sprawdź logi serwera

## Bezpieczeństwo
- **NIGDY** nie commituj prawdziwych haseł do Git
- Użyj zmiennych środowiskowych w produkcji
- Regularnie zmieniaj hasła aplikacji
- Używaj HTTPS w produkcji

## Przykład zmiennych środowiskowych
```bash
# .env
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=twoj.email@gmail.com
MAIL_PASSWORD=twoje_haslo_aplikacji
MAIL_FROM_EMAIL=twoj.email@gmail.com
MAIL_FROM_NAME=Wypożyczalnia Samochodów
```
