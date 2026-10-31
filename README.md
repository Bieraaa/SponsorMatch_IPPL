# SponsorMatch

**SponsorMatch** adalah aplikasi berbasis web yang membantu *Event Organizer* (EO) mencari dan mengelola sponsor. Aplikasi ini mempermudah pencarian mitra sponsor yang tepat, pengelolaan proposal, serta negosiasi kerja sama sponsorship, dilengkapi dengan rekomendasi berbasis AI untuk mencocokkan EO dengan sponsor yang relevan.

## Fitur Utama

- **Dua Peran**: Event Organizer dan Perusahaan/Sponsor
- **Autentikasi**: Email/Password, OTP, 2FA, Passkey, Google OAuth
- **Rekomendasi AI**: Pencocokan EO dengan sponsor yang sesuai kebutuhan acara
- **Manajemen Proposal**: Buat, kirim, dan kelola proposal sponsorship
- **Penawaran Sponsor**: Ajukan & respon tawaran antar EO dan perusahaan
- **Tracking Pipeline**: Pantau status proposal secara bertahap
- **Follow Up Log**: Catatan tindak lanjut untuk setiap proposal
- **Notifikasi**: Update terkait aktivitas proposal
- **Pencarian**: Temukan EO atau perusahaan yang relevan
- **Ekstraksi PDF**: Isi form proposal otomatis dari file PDF

## Tech Stack

- **Backend**: Laravel 13, Inertia.js, Laravel Fortify, Wayfinder
- **Frontend**: Vue 3, TypeScript, Vite, Tailwind CSS v4, Reka UI, Lucide Vue Next

## Instalasi

```bash
git clone https://github.com/Bieraaa/SponsorMatch.git
cd SponsorMatch
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

Akses: http://localhost:8000

## Lisensi

MIT License
