# PRD: Pemisahan Module Managed Services

**Tanggal:** 27 Mei 2026  
**Versi:** 1.0  
**Status:** Draft  
**Berkaitan dengan:** PRD DewaKoding Project Management v1.0

---

## 1. Executive Summary

Saat ini sistem memperlakukan semua proyek secara seragam — baik yang berstatus `POC`, `Running`, `Completed`, maupun `Managed` semuanya masuk ke menu yang sama. Padahal **Managed Services memiliki nature yang sangat berbeda** dari project biasa: tidak ada end date, fokus pada SLA & support ticket, dan memerlukan pencatatan effort/worklog untuk keperluan billing.

Fitur ini bertujuan memisahkan **Managed Services** menjadi module navigasi tersendiri di dalam Filament, tanpa perubahan struktur database, sehingga tim bisa bekerja dengan konteks yang tepat dan UI yang relevan.

---

## 2. Problem Statement

| # | Masalah | Dampak |
|---|---------|--------|
| 1 | Project dengan status `Managed` tercampur di list Projects bersama POC/Running/Completed | Tim sulit memfilter mana yang aktif project vs ongoing service |
| 2 | Tidak ada dashboard khusus per customer untuk Managed Services | Admin harus lompat-lompat antar halaman untuk melihat status layanan satu customer |
| 3 | Fitur seperti worklog dan SLA belum ada entry point yang jelas | Fitur baru yang relevan untuk MS tidak punya "rumah" di navigasi |
| 4 | Client portal hanya bisa diakses via token, tidak ada overview MS untuk internal admin | Admin tidak punya single view untuk semua MS yang sedang berjalan |

---

## 3. Goals & Non-Goals

### Goals ✅
- Memisahkan navigasi Projects dan Managed Services secara visual dan fungsional
- Managed Services punya dashboard, board, dan timeline sendiri
- Tidak ada perubahan struktur database (zero migration)
- Fitur existing (kanban, timeline, ticket) tetap berfungsi normal untuk kedua module
- Membuka slot untuk fitur MS-specific di masa depan (worklog, contract, SLA)

### Non-Goals ❌
- Membuat tabel database baru khusus Managed Services
- Mengubah cara kerja tiket atau permission yang sudah ada
- Memindahkan data existing
- Mengerjakan fitur Worklog atau Contract bersamaan (itu PRD tersendiri)

---

## 4. User Stories

| ID | Role | Story | Acceptance Criteria |
|---|---|---|---|
| US-001 | Admin | Saya ingin melihat daftar Managed Services terpisah dari Projects, sehingga saya tidak perlu filter manual setiap kali | Sidebar punya menu "Managed Services" tersendiri yang hanya menampilkan project dengan `project_status = Managed` |
| US-002 | Admin | Saya ingin melihat dashboard ringkasan semua Managed Services per customer, sehingga saya tahu status layanan tiap klien sekilas | Halaman MS Overview menampilkan card per customer dengan jumlah akun, total tiket open, dan member aktif |
| US-003 | Member | Saya ingin board kanban khusus untuk Managed Services yang saya handle, sehingga saya tidak terganggu oleh proyek lain | Service Board hanya menampilkan tiket dari project berstatus Managed yang saya ikuti |
| US-004 | Admin | Saya ingin bisa mengubah project dari status Running ke Managed langsung dari list Projects, sehingga transisi mudah dilakukan | Ada action "Promote to Managed Service" di ProjectResource |
| US-005 | Super Admin | Saya ingin semua fitur yang sudah ada (timeline, ticket, notes) tetap bisa diakses dari dalam module Managed Services | Navigasi di dalam MS tetap bisa mengakses Ticket Timeline dan Project Notes untuk project berstatus Managed |

---

## 5. Functional Requirements

### 5.1 Perubahan Navigasi Sidebar

| ID | Requirement | Priority |
|---|---|---|
| FR-001 | Tambah `NavigationGroup` baru bernama **"Managed Services"** di Filament panel | High |
| FR-002 | Menu **"Projects"** yang existing hanya menampilkan project dengan `project_status != Managed` | High |
| FR-003 | Menu baru **"Services"** di group Managed Services menampilkan project dengan `project_status = Managed` | High |
| FR-004 | Urutan sidebar: Dashboard → Projects (group) → **Managed Services (group)** → Analytics | Medium |

**Struktur sidebar target:**

```
📁 Project Management
   ├── Customers
   ├── Projects
   ├── Project Timeline
   ├── Project Board
   └── Ticket Timeline

📁 Managed Services          ← BARU
   ├── MS Overview           ← BARU
   ├── Services (list)       ← BARU (filtered ProjectResource)
   └── Service Board         ← BARU (filtered ProjectBoard)

📁 Analytics
   ├── User Contributions
   └── Leaderboard
```

---

### 5.2 MS Overview Page (Halaman Baru)

Halaman Livewire yang menjadi "landing page" module Managed Services.

| ID | Requirement | Priority |
|---|---|---|
| FR-010 | Tampilkan summary card per **Customer** yang memiliki minimal 1 project Managed | High |
| FR-011 | Setiap card customer menampilkan: nama customer, jumlah akun MS, total tiket open, total member aktif | High |
| FR-012 | Klik card customer → expand atau navigate ke list project MS milik customer tersebut | Medium |
| FR-013 | Tampilkan total keseluruhan di header: total MS aktif, total tiket open across all MS, total customer yang punya MS | Medium |
| FR-014 | Filter by PIC/member (dropdown) untuk melihat MS yang di-handle user tertentu | Low |

**Wireframe konsep:**

```
┌─────────────────────────────────────────────────────┐
│  Managed Services Overview                          │
│  12 Services  •  3 Customers  •  47 Open Tickets   │
├─────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌──────────────────┐         │
│  │ PT Adi Sarana    │  │ PT Kaltim Prima  │         │
│  │ 3 akun MS        │  │ 4 akun MS        │         │
│  │ 12 tiket open    │  │ 8 tiket open     │         │
│  │ 3 member         │  │ 2 member         │         │
│  └──────────────────┘  └──────────────────┘         │
└─────────────────────────────────────────────────────┘
```

---

### 5.3 Services List (Filtered ProjectResource)

| ID | Requirement | Priority |
|---|---|---|
| FR-020 | Buat `ManagedServiceResource` yang extend atau wrap `ProjectResource` dengan scope `project_status = Managed` | High |
| FR-021 | Kolom tabel: Customer, Nama Akun, Ticket Prefix, Member Count, Open Tickets, Last Activity | High |
| FR-022 | Tidak ada tombol "New" untuk membuat MS langsung — MS dibuat via promosi dari Projects | Medium |
| FR-023 | Tetap bisa Edit dan View dari list ini | High |
| FR-024 | Tampilkan badge "Managed" berwarna ungu/berbeda di kolom status | Low |

---

### 5.4 Service Board (Filtered Project Board)

| ID | Requirement | Priority |
|---|---|---|
| FR-030 | Buat halaman `ServiceBoard` yang extend `ProjectBoard` dengan default filter `project_status = Managed` | High |
| FR-031 | Dropdown pilih project hanya menampilkan project berstatus Managed | High |
| FR-032 | Fungsi drag-and-drop, filter user, sort, new ticket, dan export tetap berfungsi seperti `ProjectBoard` | High |
| FR-033 | Warna header board dibedakan (misal: ungu/teal) agar user sadar sedang di konteks Managed Services | Low |

---

### 5.5 Promote to Managed Service Action

| ID | Requirement | Priority |
|---|---|---|
| FR-040 | Tambah action **"Promote to Managed Service"** di `ProjectResource` table actions | High |
| FR-041 | Action hanya muncul untuk project dengan status `Completed` atau `Running` | High |
| FR-042 | Saat dikonfirmasi, ubah `project_status` menjadi `Managed` | High |
| FR-043 | Setelah promote, project otomatis hilang dari list Projects dan muncul di Services | High |
| FR-044 | Tambah action **"Move back to Projects"** di `ManagedServiceResource` untuk reversal | Medium |

---

### 5.6 Scope & Permission

| ID | Requirement | Priority |
|---|---|---|
| FR-050 | `ManagedServiceResource::getEloquentQuery()` selalu menambahkan `where('project_status', 'Managed')` | High |
| FR-051 | Permission `view_managed_service` dan `manage_managed_service` ditambahkan via Filament Shield | High |
| FR-052 | Role `member` hanya bisa view MS yang dia ikuti (ikuti pola scope existing di `ProjectResource`) | High |
| FR-053 | Role `admin` dan `super_admin` bisa melihat semua MS | High |

---

## 6. Non-Functional Requirements

| Aspek | Requirement |
|---|---|
| **Performance** | MS Overview harus load < 2 detik meski ada 50+ project Managed (gunakan eager loading customer + count tickets) |
| **Zero Downtime** | Tidak ada migrasi DB, deployment bisa dilakukan tanpa downtime |
| **Backward Compatibility** | Semua URL existing (`/admin/projects`, `/admin/project-board`) tetap berfungsi normal |
| **Konsistensi UI** | Ikuti design system Filament yang sudah ada, tidak perlu custom CSS besar-besaran |

---

## 7. Technical Considerations

### Pendekatan Implementasi (Zero Migration)

```
Existing:
  projects.project_status (enum: POC, Running, Completed, Managed) ✅ sudah ada

Yang perlu dibuat:
  app/Filament/Resources/ManagedServiceResource.php
    └── getEloquentQuery(): tambah ->where('project_status', 'Managed')
    └── Sembunyikan tombol Create
    └── Tambah action Promote/Demote

  app/Filament/Pages/MsOverview.php (Livewire)
    └── Query: Project::where('project_status','Managed')->with('customer','members','tickets')
    └── Group by customer_id

  app/Filament/Pages/ServiceBoard.php
    └── Extend atau copy ProjectBoard.php
    └── Override getProjects() untuk filter status Managed

  ProjectResource.php
    └── getEloquentQuery(): tambah ->where('project_status', '!=', 'Managed')
    └── Tambah PromoteToManagedAction
```

### Potensi Breaking Change

- **`ProjectResource` scope berubah** — project Managed tidak akan muncul di `/admin/projects` lagi. Pastikan tidak ada kode lain yang bergantung pada list projects tanpa filter.
- **`ProjectBoard`** — dropdown project akan kehilangan project Managed. Perlu verifikasi tidak ada user yang bergantung pada ini untuk MS mereka.

### File yang Diubah

| File | Jenis Perubahan |
|---|---|
| `ProjectResource.php` | Tambah scope filter + PromoteAction |
| `ProjectBoard.php` | Tidak diubah (ServiceBoard adalah file baru) |
| `AdminPanelProvider.php` | Registrasi resource dan page baru + NavigationGroup |

### File Baru

| File | Deskripsi |
|---|---|
| `ManagedServiceResource.php` | Resource untuk list & edit MS |
| `MsOverview.php` | Dashboard overview MS |
| `ServiceBoard.php` | Kanban board khusus MS |
| `PromoteToManagedAction.php` | Filament action untuk promote project |
| `DemoteFromManagedAction.php` | Filament action untuk reverse |

---

## 8. Out of Scope

Hal-hal berikut **tidak** dikerjakan dalam PRD ini dan akan menjadi PRD tersendiri:

- **Time Tracking / Worklog** per ticket (PRD 5.1)
- **Customer Contract & Billing** (PRD 5.3)
- **SLA Tracking** — belum ada di roadmap, tapi akan lebih mudah dibangun setelah module MS ini ada
- **Notifikasi khusus MS** (misal: alert tiket open > X hari)
- **Perubahan Client Portal** (`/external/{token}`)

---

## 9. Rencana Implementasi

| Phase | Scope | Estimasi |
|---|---|---|
| **Phase 1** | Sidebar split + scope filter ProjectResource + ManagedServiceResource basic | 1–2 hari |
| **Phase 2** | ServiceBoard (clone + filter ProjectBoard) + PromoteAction | 1 hari |
| **Phase 3** | MS Overview Page (dashboard per customer) | 2–3 hari |
| **Phase 4** | Permission (Filament Shield) + testing + QA | 1 hari |

> Total estimasi: **5–7 hari** kerja.  
> Phase 1 dan 2 bisa di-deploy duluan sebagai incremental release.

---

## 10. Open Questions

1. **Apakah project yang sudah `Managed` boleh diubah statusnya kembali ke `Running`?** Jika ya, perlu action "Demote" + konfirmasi agar tidak tidak sengaja.

2. **Apakah `MS Overview` perlu menampilkan data tiket real-time** (live count) atau cukup di-refresh saat halaman dibuka? Implikasinya ke perlu/tidaknya Livewire polling.

3. **Permission granularity:** Apakah semua admin bisa promote project ke Managed, atau hanya super_admin? Ini menentukan di mana gate permission diletakkan.

4. **Bagaimana dengan `Ticket Timeline` dan `Project Notes`?** Apakah perlu versi filtered khusus MS juga, atau cukup diakses dari dalam halaman detail service?

5. **Nama menu:** "Managed Services" atau "Services" saja? Apakah ada istilah internal yang lebih familiar untuk tim?
