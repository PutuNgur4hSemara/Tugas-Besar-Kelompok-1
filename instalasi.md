# Langkah-langkah Instalasi Project PharmaSystem

Prasyarat
PHP >= 8.2,
Laravel >= 12.11.0,
Composer,
Node.js & npm,
Git,
Postgre Database,


### Langkah Instalasi


#### 1. Clone Repository
`git clone https://github.com/PutuNgurahSemara/Tugas-Besar-Kelompok-1.git
cd Tugas-Besar-Kelompok-1/pharmasys-vite`

#### 2. Install Dependencies PHP
`composer install`

#### 3. Install Dependencies JavaScript
`composer install`

#### 4. Setup Environment
`cp .env.example .env
php artisan key:generate`

#### 5. Konfigurasi Database
Buka file .env
Sesuaikan konfigurasi database:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=username_database
DB_PASSWORD=password_database

#### 6. Jalankan Migrasi & Seeder
`php artisan migrate --seed`

#### 7. Build Assets
`npm run build`

#### 8. Setup Email untuk Forgot Password (Opsional)

Untuk mengaktifkan fitur lupa password, konfigurasi email di file `.env`:

**Untuk Gmail:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=PharmaSys
```

**Untuk Yahoo:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.yahoo.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yahoo.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

**Untuk Outlook:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

**Untuk Mailgun/SendGrid (Production):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@sandboxxxxxx.mailgun.org
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
```

#### 9. Jalankan aplikasi
`composer run dev`
#### 9. Jalankan aplikasi
`composer run dev`

