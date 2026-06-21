# PG Manager

เครื่องมือจัดการฐานข้อมูล PostgreSQL ผ่านเว็บเบราว์เซอร์ เขียนด้วย PHP แบบ pure (ไม่มี dependency ภายนอก) — ใช้งานคล้าย phpMyAdmin แต่ออกแบบมาสำหรับ PostgreSQL โดยเฉพาะ

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-9.6%2B-336791?logo=postgresql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

---

## สารบัญ

- [ความต้องการของระบบ](#ความต้องการของระบบ)
- [การติดตั้ง](#การติดตั้ง)
- [การใช้งานเบื้องต้น](#การใช้งานเบื้องต้น)
- [ฟีเจอร์](#ฟีเจอร์)
- [โครงสร้างโปรเจ็ค](#โครงสร้างโปรเจ็ค)
- [การตั้งค่า](#การตั้งค่า)
- [การ deploy บน Production](#การ-deploy-บน-production)
- [ความปลอดภัย](#ความปลอดภัย)
- [ข้อจำกัดที่ควรทราบ](#ข้อจำกัดที่ควรทราบ)
- [Roadmap / ข้อเสนอแนะ](#roadmap--ข้อเสนอแนะ)
- [License](#license)

---

## ความต้องการของระบบ

| รายการ | รายละเอียด |
|--------|-----------|
| PHP | 8.1 ขึ้นไป |
| PHP Extensions | `pdo`, `pdo_pgsql`, `session`, `json` |
| PostgreSQL | 9.6 ขึ้นไป |
| Web Server | Apache หรือ Nginx |

ตรวจสอบ extension:

```bash
php -m | grep -E 'pdo_pgsql|pgsql'
```

---

## การติดตั้ง

### 1. คัดลอกไฟล์

```bash
git clone <repository-url> /var/www/html/pgadmin
# หรือคัดลอกโฟลเดอร์ pgadmin ไปยัง web root ของคุณ
```

### 2. ตั้งค่า Web Server

#### Apache

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/pgadmin
    <Directory /var/www/html/pgadmin>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    root /var/www/html/pgadmin;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # ป้องกันการเข้าถึงไฟล์ config โดยตรง
    location ~ /(config\.php|bootstrap\.php|classes/) {
        deny all;
    }
}
```

### 3. ตั้งค่า (ถ้าต้องการ)

แก้ไข `config.php` ตาม [การตั้งค่า](#การตั้งค่า)

### 4. เปิดใช้งาน

เปิดเบราว์เซอร์ไปที่ URL ที่ติดตั้ง เช่น:

```
http://localhost/pgadmin/
```

กรอกข้อมูลการเชื่อมต่อ PostgreSQL (Host, Port, Database, Username, Password) แล้วกด **เชื่อมต่อ**

---

## การใช้งานเบื้องต้น

1. **Login** — หน้า `index.php` ใช้ credential ของ PostgreSQL โดยตรง (ไม่มี user แยกของแอป)
2. **เลือก Database** — ไปที่เมนู *ฐานข้อมูล* เพื่อสลับหรือสร้าง database
3. **จัดการ Schema / ตาราง** — สร้าง schema, ตาราง, ดูโครงสร้าง, browse ข้อมูล
4. **SQL Query** — รันคำสั่ง SQL ใดก็ได้ (ตามสิทธิ์ของ user ที่ login)
5. **Import / Export** — นำเข้า SQL หรือส่งออกตารางเป็น SQL / CSV / JSON + Backup ทั้ง database
6. **Logout** — กด *ออกจากระบบ* เพื่อล้าง session (auto timeout เมื่อ idle)

---

## ฟีเจอร์

| โมดูล | รายละเอียด |
|-------|-----------|
| **แดชบอร์ด** | ข้อมูลเซิร์ฟเวอร์, version, encoding, timezone, จำนวน connection |
| **ฐานข้อมูล** | สร้าง, ลบ, สลับ database |
| **Schemas** | สร้าง, ลบ schema (รองรับ CASCADE) |
| **ตาราง** | สร้าง, ลบ, truncate, ดูรายการตาม schema |
| **โครงสร้างตาราง** | ดู columns, indexes, constraints; เพิ่ม/แก้ไข/ลบ column |
| **ข้อมูล** | Browse, Insert, Update, Delete พร้อม pagination, เรียงลำดับ, ค้นหา |
| **Views** | แสดงรายการ views |
| **Sequences** | แสดงรายการ sequences |
| **Functions** | แสดงรายการ functions |
| **SQL Query** | รัน SQL แบบอิสระ พร้อมแสดงผลลัพธ์และเวลาที่ใช้ |
| **Import** | นำเข้าไฟล์ SQL หรือวาง SQL โดยตรง (transaction + rollback เมื่อ error) |
| **Export** | ส่งออกตารางเป็น SQL, CSV หรือ JSON + Backup ทั้ง database |
| **Roles / Users** | สร้าง, ลบ PostgreSQL roles |

---

## โครงสร้างโปรเจ็ค

```
pgadmin/
├── index.php              # หน้า Login / เชื่อมต่อ PostgreSQL
├── app.php                # Router หลัก + จัดการ POST actions
├── bootstrap.php          # Session, helpers, autoload classes
├── config.php             # การตั้งค่าแอป (merge กับ config.local.php ได้)
├── config.example.php     # ตัวอย่าง config
├── docker-compose.yml     # Dev environment (PHP + PostgreSQL)
├── .htaccess              # ป้องกันไฟล์ sensitive (Apache)
├── classes/
│   ├── Database.php       # PDO wrapper สำหรับ PostgreSQL
│   ├── PgManager.php      # Business logic (DDL, DML, export, import)
│   ├── Session.php        # จัดการ session และ credentials
│   ├── Csrf.php           # CSRF token protection
│   ├── I18n.php           # ระบบหลายภาษา (TH/EN)
│   └── RateLimiter.php    # จำกัดความถี่ login
├── locale/                # ไฟล์แปลภาษา (th.php, en.php)
├── tests/run.php          # Unit tests
├── views/
│   ├── layout.php         # Layout หลัก (sidebar + content)
│   ├── pages/             # หน้าแต่ละ module
│   └── partials/          # Component ย่อย (เช่น column field)
└── assets/
    ├── css/style.css
    └── js/app.js
```

### สถาปัตยกรรมโดยย่อ

```
Browser → index.php (login) → Session (เก็บ DB credentials)
                            ↓
                         app.php (router)
                            ↓
                    PgManager → Database (PDO)
                            ↓
                       PostgreSQL
```

---

## การตั้งค่า

แก้ไขไฟล์ `config.php` หรือสร้าง `config.local.php` (จะ merge ทับค่าเดิม):

```php
return [
    'app_name' => 'PG Manager',
    'session_timeout' => 1800,              // วินาที (30 นาที) — idle logout
    'login_rate_limit' => 5,                  // จำนวนครั้ง login ที่อนุญาต
    'login_rate_window' => 300,               // ภายในกี่วินาที
    'login_rate_block' => 900,                // บล็อกกี่วินาทีเมื่อเกิน limit
    'read_only' => false,                     // true = อ่านอย่างเดียว (ไม่มี DDL/DML)
    'default_locale' => 'th',                 // th หรือ en
    'query_history_limit' => 20,
    'allowed_export_formats' => ['sql', 'csv', 'json'],
];
```

ดูรายละเอียดเพิ่มเติมใน `config.example.php`

### รัน dev ด้วย Docker Compose

```bash
docker compose up -d
# เปิด http://localhost:8080 — เชื่อมต่อ PostgreSQL ที่ host=postgres, password=secret
```

---

## การ deploy บน Production

> **คำเตือน:** โปรแกรมนี้ให้สิทธิ์เต็มตาม PostgreSQL user ที่ login — ควรใช้ในเครือข่ายภายใน (dev/staging/VPN) เท่านั้น ไม่ควรเปิดสู่ internet โดยตรง

### Checklist ก่อน deploy

- [ ] ใช้ HTTPS (TLS certificate)
- [ ] จำกัด IP ที่เข้าถึงได้ (firewall / web server)
- [ ] ใช้ PostgreSQL user ที่มีสิทธิ์น้อยที่สุด (least privilege)
- [x] ตั้ง session timeout ใน config (`session_timeout`)
- [ ] ปิด `display_errors` ใน production

### ตัวอย่างจำกัด IP (Apache)

```apache
<Directory /var/www/html/pgadmin>
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</Directory>
```

### ตัวอย่างจำกัด IP (Nginx)

```nginx
location /pgadmin {
    allow 192.168.1.0/24;
    deny all;
}
```

---

## ความปลอดภัย

### สิ่งที่โปรเจ็คทำอยู่แล้ว

- **Prepared statements** — ใช้ PDO prepared statements สำหรับ DML (INSERT/UPDATE/DELETE)
- **Identifier validation** — ตรวจสอบชื่อ schema/table/column ก่อนใช้ใน DDL
- **Output escaping** — ใช้ `htmlspecialchars()` ผ่าน helper `e()` ใน view
- **Session-based auth** — credential เก็บใน PHP session ไม่ hardcode ในไฟล์ (ใช้ PostgreSQL login โดยตรง)
- **SQL import rollback** — import ใช้ transaction และ rollback เมื่อมี error
- **CSRF protection** — ทุกฟอร์ม POST มี token verification
- **Secure session** — `httponly`, `secure` (เมื่อ HTTPS), `samesite=Strict`, regenerate session ID หลัง login
- **Session timeout** — ล้าง session อัตโนมัติเมื่อ idle (config `session_timeout`)
- **Rate limiting** — จำกัดความถี่ login ต่อ IP
- **Read-only mode** — config `read_only` ปิด DDL/DML และจำกัด SQL เป็น SELECT/EXPLAIN/SHOW

---

## ข้อจำกัดที่ควรทราบ

- **ไม่มี user ของแอปเอง** — ใช้ PostgreSQL credential โดยตรง (by design)
- **SQL Query รันได้ทุกคำสั่ง** — ตามสิทธิ์ของ DB user ที่ login (ยกเว้นโหมด read-only)
- **ชื่อ identifier** — รองรับเฉพาะรูปแบบ `[a-zA-Z_][a-zA-Z0-9_]*` ไม่รองรับชื่อที่มี quote หรืออักขระพิเศษ
- **Database backup** — export เป็น SQL ในแอป (ไม่เทียบเท่า `pg_dump` สำหรับ DB ขนาดใหญ่)
- **ไม่มี Composer / dependency manager** — เป็น pure PHP ไม่มี package ภายนอก

---


## License

MIT — ใช้งาน แก้ไข และแจกจ่ายได้อย่างอิสระ
