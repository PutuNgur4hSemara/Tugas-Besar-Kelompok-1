# Setup Email untuk Fitur Lupa Password

Fitur lupa password di PharmaSys sudah terintegrasi dengan sistem email Laravel dan terhubung ke database PostgreSQL untuk menyimpan token reset password.

## 📧 Cara Kerja Sistem

1. **User meminta reset password** → Input email di halaman login
2. **Token dibuat & disimpan** → Di tabel `password_resets` PostgreSQL
3. **Email dikirim** → Berisi link reset password
4. **User klik link** → Akses halaman reset password
5. **Password diubah** → Token dihapus dari database

## 🔧 Konfigurasi Email

### 1. Update File .env

Edit file `.env` di root project dan ubah konfigurasi MAIL:

```env
# Ganti dari:
MAIL_MAILER=log

# Menjadi:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=PharmaSys
```

### 2. Setup untuk Berbagai Provider Email

#### **Gmail**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

**Cara dapat App Password Gmail:**
1. Buka Google Account Settings
2. Security → 2-Step Verification → App passwords
3. Generate password untuk "Mail"
4. Gunakan password tersebut (bukan password akun)

#### **Yahoo Mail**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.yahoo.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yahoo.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

#### **Outlook/Hotmail**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

#### **Yandex**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yandex.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yandex.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### 3. Setup untuk Production

#### **Mailgun (Recommended)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@sandboxxxxxx.mailgun.org
MAIL_PASSWORD=your-mailgun-api-key
MAIL_ENCRYPTION=tls
```

#### **SendGrid**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

#### **AWS SES**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

## 🧪 Testing Email Setup

### 1. Test dengan Tinker
```bash
php artisan tinker
```

Kemudian jalankan:
```php
Mail::raw('Test email from PharmaSys', function($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

### 2. Test Forgot Password
1. Jalankan aplikasi: `php artisan serve`
2. Buka halaman login
3. Klik "Forgot your password?"
4. Input email yang valid
5. Cek email atau log Laravel

### 3. Cek Log Email (Development)
Jika masih menggunakan `MAIL_MAILER=log`, cek log di:
```
storage/logs/laravel.log
```

## 🗄️ Struktur Database

Tabel `password_resets` sudah otomatis dibuat dengan migration:
```sql
CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    INDEX password_resets_email_index (email)
);
```

Token akan expired otomatis setelah 60 menit (konfigurasi Laravel default).

## 🔒 Keamanan

- **Token Hash**: Token disimpan dalam bentuk hash SHA-256
- **Expiration**: Token expired dalam 60 menit
- **Rate Limiting**: Laravel otomatis limit request forgot password
- **CSRF Protection**: Form dilindungi CSRF token

## 🚀 Deployment Production

### 1. Environment Variables
Pastikan di server production, set environment variables:
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-production-email@gmail.com
MAIL_PASSWORD=your-production-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=PharmaSys
```

### 2. Queue untuk Email (Recommended)
Untuk performance, gunakan queue untuk mengirim email:
```bash
# .env
QUEUE_CONNECTION=database

# Jalankan queue worker
php artisan queue:work
```

### 3. Monitoring Email
Monitor pengiriman email dengan:
```bash
# Cek failed jobs
php artisan queue:failed

# Monitor email queue
php artisan queue:monitor
```

## 🐛 Troubleshooting

### Email Tidak Terkirim
1. **Cek konfigurasi .env** - Pastikan MAIL_MAILER=smtp
2. **Test koneksi SMTP** - Gunakan tools seperti telnet
3. **Cek firewall** - Port 587/465 mungkin blocked
4. **Verifikasi credentials** - Pastikan username/password benar

### Token Tidak Valid
1. **Cek URL** - Pastikan APP_URL di .env sudah benar
2. **Cek waktu server** - Token mungkin expired
3. **Cek database** - Pastikan tabel password_resets ada data

### Error Connection Timeout
1. **Cek DNS** - Pastikan hostname tersolve
2. **Cek port** - Beberapa ISP block port 587
3. **Gunakan port alternatif** - Coba port 465 dengan SSL

## 📞 Support

Jika mengalami masalah setup email, pastikan:
1. Email provider mendukung SMTP
2. Credentials (username/password) benar
3. Port dan encryption sesuai
4. Firewall tidak block koneksi SMTP
