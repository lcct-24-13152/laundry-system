# CML Laundry Management System

A web system for running a laundry shop, from drop-off to payment. Staff record transactions, track inventory and print receipts, and every order gets a QR code that customers scan to check their laundry status.

## Features

- **Transactions:** customer details, laundry type, weight, add-ons and extra services, with automatic pricing
- **QR tracking:** each receipt has a QR code that opens a customer tracking page
- **Payments:** cash and GCash, including downpayments and balances
- **Inventory:** supplies with low and critical stock alerts sent by email
- **Loyalty:** rewards based on number of washes
- **Reports:** printable monthly report (PDF) and transaction history
- **SMS updates:** optional status messages to customers through Semaphore
- **Accounts:** admin login with lockout, password reset by email, account settings

## Built with

PHP, MySQL, JavaScript, HTML and CSS, plus [PHPMailer](https://github.com/PHPMailer/PHPMailer) (email), [Dompdf](https://github.com/dompdf/dompdf) (PDF reports) and [PHP QR Code](https://phpqrcode.sourceforge.net/) (QR codes).

## Run it on your computer

1. Copy this folder to `C:\laragon\www\` (Laragon) or `C:\xampp\htdocs\` (XAMPP).
2. Open **phpMyAdmin**, create a database named `laundry_db`, and import `database.sql`.
3. Open `http://localhost/laundry-system/` in your browser.
4. Log in with the demo account below, then change the password in **Account Settings**.

| Username | Password |
|----------|----------|
| admin    | admin123 |

## Settings (email, SMS, QR address)

The system runs without any setup. To turn on email and SMS:

1. In the `config` folder, copy `config.example.php` and name the copy `config.php`.
2. Fill in your Gmail address and [Gmail App Password](https://myaccount.google.com/apppasswords), and your Semaphore API key if you use SMS.
3. To let phones scan receipt QR codes while running on localhost, set `APP_URL` to your ngrok link.

`config.php` is listed in `.gitignore`, so your passwords stay on your computer.

---

Made by **Cher Micole P. Lirio**, BS Information Technology, La Consolacion College of Tanauan.
