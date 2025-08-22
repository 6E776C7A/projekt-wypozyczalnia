<?php
// Plik: src/Service/MailerService.php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twig\Environment;

class MailerService
{
    private PHPMailer $mailer;
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
        $this->mailer = new PHPMailer(true); // 'true' włącza wyjątki

        // --- KONFIGURACJA SERWERA SMTP DLA POCZTY ONET Z PRZYKŁADOWYMI DANYMI ---
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.poczta.onet.pl';       // Serwer SMTP dla Onetu
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'auto_wypozyczalnia@onet.pl';   // Przykładowy e-mail
        $this->mailer->Password = 'auta_haslo1';                   // Przykładowe hasło
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Typ szyfrowania
        $this->mailer->Port = 465;                        // Port dla SMTPS/SSL

        // Ustawienia nadawcy i kodowanie
        $this->mailer->CharSet = 'UTF-8';
        // E-mail "od" musi być taki sam jak Username
        $this->mailer->setFrom('auto_wypozyczalnia@onet.pl', 'Wypożyczalnia Samochodów');
    }

    /**
     * Wysyła e-mail z potwierdzeniem rezerwacji.
     * @param string $toEmail Adres e-mail odbiorcy (ten, który wpisał użytkownik w formularzu)
     * @param array $details Szczegóły rezerwacji do użycia w szablonie
     */
    public function sendBookingConfirmation(string $toEmail, array $details)
    {
        try {
            // Odbiorca
            $this->mailer->addAddress($toEmail);

            // Treść
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Potwierdzenie rezerwacji samochodu';

            // Renderuj treść e-maila używając szablonu Twig
            $body = $this->twig->render('email_template.twig', ['details' => $details]);
            $this->mailer->Body = $body;

            // Wyślij
            $this->mailer->send();

        } catch (Exception $e) {
            // W prawdziwej aplikacji warto logować błędy
            error_log("Błąd wysyłki e-maila: {$this->mailer->ErrorInfo}");
        }
    }
}