# Dokumentasi Aplikasi PM-Cloud-DCS

## Gambaran Umum

Aplikasi **PM-Cloud-DCS** adalah Project Management System berbasis web yang dibangun dengan Laravel 12 dan Filament 4. Dirancang untuk mengelola proyek, tiket/issue, tim, dan workflow secara kolaboratif.

---

## Tech Stack

| Teknologi | Versi |
|-----------|-------|
| PHP | 8.3 |
| Laravel | 12 |
| Filament | 4 |
| MySQL | 8.0 (Docker) |
| Vite | 6 |
| Tailwind CSS | 4 |
| Pest | 3 |

**Dependencies utama:**
- Filament Shield (Permissions)
- Spatie Laravel Permissions
- Maatwebsite Excel (Import/Export)
- Google OAuth Integration
- Laravel Octane (FrankenPHP)

---

## Fitur-Fitur Utama

### 1. Manajemen Proyek

- CRUD proyek lengkap dengan color picker, tanggal mulai/selesai
- Pin proyek favorit
- Progress tracking per proyek
- Catatan proyek (Project Notes)
- Assign anggota tim ke proyek

### 2. Ticket/Issue Tracking

- Pembuatan tiket dengan UUID otomatis
- Prioritas tiket (kustomisasi level prioritas)
- Status workflow per proyek (dengan warna dan urutan)
- Pengelompokan tiket ke dalam Epic
- Assign tiket ke multiple user
- Komentar pada tiket (real-time via Livewire)
- Audit trail lengkap (riwayat perubahan status)

### 3. Kanban Board

- Tampilan board visual per proyek
- Drag & drop tiket antar status
- Filter dan pencarian tiket

### 4. Timeline & Gantt Chart

- **Project Timeline** — visualisasi jadwal proyek dalam bentuk Gantt chart
- **Ticket Timeline** — riwayat tiket dalam bentuk timeline

### 5. Epic Management

- Pengelompokan fitur/tiket ke dalam Epic
- Tanggal mulai/selesai per epic
- Halaman overview khusus epic
- Sort order untuk pengurutan

### 6. Kolaborasi Tim

- Manajemen anggota proyek (assign/remove member)
- Notifikasi email otomatis saat ditambahkan/dihapus dari proyek
- Komentar tiket dengan notifikasi
- Tracking aktivitas per user

### 7. Leaderboard & Kontribusi User

- Statistik kontribusi user (tiket diselesaikan, komentar, dll)
- Leaderboard dengan filter rentang waktu
- Halaman detail kontribusi per user

### 8. External Dashboard Access

- Buat token akses untuk pihak eksternal
- Dashboard read-only yang bisa diakses tanpa login admin
- Login khusus external via token URL

### 9. Import/Export Tiket (Excel)

- Import tiket secara bulk dari file Excel
- Export tiket ke Excel
- Download template Excel untuk kemudahan import

### 10. Notifikasi

- Notifikasi in-app (read/unread status)
- Email notification saat assignment/removal dari proyek
- Notifikasi komentar tiket

### 11. Role-Based Access Control

- Manajemen role dan permission via Filament Shield
- Policy per resource (Project, Ticket, User, dll)
- 7 policy terdefinisi untuk otorisasi granular
- Kontrol akses per fitur

### 12. Autentikasi

- Login standar Laravel
- Google OAuth integration
- Custom login page Filament

### 13. System Settings

- Konfigurasi tema (warna, style)
- Pengaturan navigasi (style navigasi)
- Setting global dan per-user

---

## Dashboard Widgets

| Widget | Fungsi |
|--------|--------|
| StatsOverview | Kartu statistik utama (total proyek, tiket, user, dll) |
| RecentActivityTable | Feed aktivitas terbaru |
| MonthlyTicketTrendChart | Grafik tren pembuatan tiket bulanan |
| UserStatisticsChart | Grafik statistik aktivitas user |
| TicketsPerProjectChart | Grafik distribusi tiket per proyek |
| ProjectTimeline | Timeline proyek di dashboard |

---

## Model & Relasi

| Model | Deskripsi | Relasi Utama |
|-------|-----------|--------------|
| User | Akun pengguna, Google OAuth, roles | hasMany: Tickets, Comments; belongsToMany: Projects |
| Project | Proyek dengan warna, tanggal, pinning | hasMany: Tickets, Statuses, Epics, Notes; belongsToMany: Users |
| Ticket | Tiket dengan UUID, prioritas, epic | belongsTo: Project, Priority, Epic, Status; belongsToMany: Users; hasMany: Comments, Histories |
| TicketStatus | Status workflow per proyek | belongsTo: Project; hasMany: Tickets |
| TicketPriority | Level prioritas tiket | hasMany: Tickets |
| Epic | Pengelompokan fitur | belongsTo: Project; hasMany: Tickets |
| TicketComment | Komentar pada tiket | belongsTo: Ticket, User |
| TicketHistory | Audit trail perubahan status | belongsTo: Ticket, User |
| ProjectNote | Catatan proyek | belongsTo: Project, User |
| Notification | Notifikasi user | belongsTo: User |
| ExternalAccess | Token akses dashboard eksternal | belongsTo: Project |

---

## Filament Resources (7 Total)

| Resource | Fungsi |
|----------|--------|
| ProjectResource | CRUD proyek dengan color picker, tanggal, pinning, progress |
| TicketResource | Manajemen tiket dengan prioritas, status, assignee, epic |
| UserResource | Manajemen user dengan role assignment, statistik |
| RoleResource | Manajemen role dan permission |
| TicketPriorityResource | Manajemen level prioritas |
| TicketCommentResource | Manajemen komentar tiket |
| NotificationResource | Manajemen notifikasi (read-only) |

---

## Filament Pages (8 Total)

| Page | Fungsi |
|------|--------|
| ProjectBoard | Kanban board untuk tiket per proyek |
| ProjectTimeline | Gantt chart timeline proyek |
| TicketTimeline | Timeline riwayat tiket |
| EpicsOverview | Overview dan manajemen epic |
| Leaderboard | Statistik kontribusi user dengan filter waktu |
| UserContributions | Detail aktivitas dan kontribusi per user |
| SystemSettings | Konfigurasi sistem (tema, warna, navigasi) |
| Login | Custom login page dengan Google OAuth |

---

## Relation Managers (7 Total)

| Relation Manager | Parent Resource | Fungsi |
|-----------------|-----------------|--------|
| TicketStatusesRelationManager | Project | Kelola status workflow dalam proyek |
| MembersRelationManager | Project | Kelola anggota proyek |
| EpicsRelationManager | Project | Kelola epic dalam proyek |
| TicketsRelationManager | Project | Kelola tiket dalam proyek |
| NotesRelationManager | Project | Kelola catatan proyek |
| ProjectsRelationManager | User | Lihat proyek milik user |
| TicketsRelationManager | User | Lihat tiket milik user |

---

## Custom Actions

| Action | Fungsi |
|--------|--------|
| ImportTicketsAction | Bulk import tiket dari file Excel |
| ExportTicketsAction | Export tiket ke file Excel |
| DownloadTicketTemplateAction | Download template Excel untuk import |

---

## Events & Listeners

| Event | Listener | Fungsi |
|-------|----------|--------|
| ProjectMemberAttached | SendProjectAssignmentNotification | Kirim email saat user ditambahkan ke proyek |
| ProjectMemberDetached | SendProjectRemovalNotification | Kirim email saat user dihapus dari proyek |

---

## Routes

| Route | Method | Fungsi |
|-------|--------|--------|
| `/` | GET | Welcome page |
| `/auth/google` | GET | Google OAuth redirect |
| `/auth/google/callback` | GET | Google OAuth callback |
| `/external/{token}` | GET | Login dashboard eksternal |
| `/external/{token}/dashboard` | GET | View dashboard eksternal |
| `/admin/*` | - | Semua operasi CRUD via Filament panel |

---

## Arsitektur Aplikasi

```
app/
├── Filament/
│   ├── Resources/           → 7 Resource (CRUD admin panel)
│   │   ├── Projects/
│   │   ├── Tickets/
│   │   ├── Users/
│   │   ├── Roles/
│   │   ├── TicketPriorities/
│   │   ├── TicketComments/
│   │   └── Notifications/
│   ├── Pages/               → 8 Custom Pages
│   ├── Widgets/             → 6 Dashboard Widgets
│   └── Actions/             → 3 Custom Actions (Import/Export)
├── Models/                  → 11 Eloquent Models
├── Policies/                → 7 Authorization Policies
├── Livewire/                → 3 Livewire Components
│   ├── TicketCommentForm
│   ├── ExternalLogin
│   └── ExternalDashboard
├── Events/                  → 2 Events
├── Listeners/               → 2 Listeners
├── Mail/                    → Email templates
└── Services/                → NotificationService
```

---

## Database Schema (Tabel Utama)

| Tabel | Deskripsi |
|-------|-----------|
| `users` | Akun pengguna dengan dukungan Google OAuth |
| `projects` | Data proyek (nama, warna, tanggal, pin, progress) |
| `tickets` | Data tiket (UUID, judul, deskripsi, prioritas, status, epic) |
| `ticket_statuses` | Status workflow per proyek (nama, warna, urutan) |
| `ticket_priorities` | Level prioritas tiket |
| `epics` | Data epic (nama, tanggal, urutan) |
| `ticket_comments` | Komentar pada tiket |
| `ticket_histories` | Riwayat perubahan status tiket |
| `project_notes` | Catatan pada proyek |
| `project_members` | Pivot table relasi proyek-user |
| `ticket_users` | Pivot table relasi tiket-user (assignee) |
| `notifications` | Notifikasi in-app |
| `external_access` | Token akses dashboard eksternal |
| `settings` | Pengaturan global dan per-user |
| `roles` | Definisi role (Filament Shield) |
| `permissions` | Definisi permission |

---

## Docker Setup

```yaml
Services:
  - app (PHP 8.3-FPM)
  - nginx (Web Server)
  - db (MySQL 8.0)
  - phpmyadmin (Database GUI)
```

**Commands:**
```bash
# Start semua service
docker compose up -d

# Rebuild container
docker compose up -d --build

# Jalankan test
docker exec laravel_app php artisan test

# Jalankan migration
docker exec laravel_app php artisan migrate
```

---

## Development Commands

```bash
# Dev server (artisan serve + queue + pail + vite)
composer dev

# Octane dev server
composer octane-dev

# Build frontend
npm run build

# Lint PHP (PSR-12)
./vendor/bin/pint

# Run tests
php artisan test

# Run specific test
./vendor/bin/pest tests/Feature/ExampleTest.php
```

---

## Authorization & Permissions

Aplikasi menggunakan **Filament Shield** dengan **Spatie Laravel Permissions** untuk kontrol akses:

- Setiap resource memiliki policy sendiri
- Permission di-generate otomatis oleh Shield (view, create, update, delete, dll)
- Role dapat dikonfigurasi dengan kombinasi permission yang berbeda
- Super admin memiliki akses penuh ke semua fitur

---

## Alur Kerja Utama

1. **Admin membuat proyek** → Set warna, tanggal, deskripsi
2. **Assign anggota** → Member mendapat email notifikasi
3. **Buat ticket status** → Definisikan workflow (To Do, In Progress, Done, dll)
4. **Buat epic** → Kelompokkan fitur-fitur besar
5. **Buat tiket** → Assign ke member, set prioritas dan epic
6. **Tracking via Kanban** → Pindahkan tiket antar status
7. **Komentar & kolaborasi** → Diskusi dalam tiket
8. **Monitor progress** → Dashboard, timeline, leaderboard
9. **Share ke eksternal** → Buat token untuk akses read-only
