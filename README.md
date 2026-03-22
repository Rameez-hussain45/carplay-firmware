# BimmerTech CarPlay Firmware Update Portal

A Symfony 6.4 application with:

- **Customer page** — customers enter their device details and receive the correct firmware download link
- **Admin panel** — non-technical staff can add and manage firmware versions without touching any code
- **REST API** — `POST /api/carplay/software/version` (identical behaviour to the original `ConnectedSiteController`)

---

## System Requirements

| Requirement | Version |
|---|---|
| PHP | **8.1 or higher** |
| PHP extensions | `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip` |
| Composer | 2.x |
| sqlite3 CLI | Any (used to load seed data) |

### Install on Ubuntu / Debian

```bash
sudo apt update
sudo apt install php php-sqlite3 php-xml php-mbstring php-curl php-zip sqlite3 composer
```

---

## Installation & Running (3 steps)

### Step 1 — Extract the project

```bash
unzip carplay-firmware.zip
cd carplay-firmware
```

### Step 2 — Run the setup script

```bash
bash setup.sh
```

The script will:
1. Check all requirements
2. Install PHP packages via Composer
3. Ask you to set an admin password
4. Create the SQLite database
5. Load all firmware version data
6. Warm up the cache

### Step 3 — Start the server

```bash
php -S localhost:8000 -t public/
```

Open your browser:

| URL | What it is |
|---|---|
| http://localhost:8000/carplay/software-download | Customer firmware download page |
| http://localhost:8000/admin | Admin panel (username: `admin`) |

---

## Manual Setup (if setup.sh fails)

```bash
# 1. Install packages
composer install --no-dev --optimize-autoloader

# 2. Generate and copy admin password hash
php bin/console security:hash-password
# Output looks like: $2y$13$...
# Open .env and replace CHANGE_ME_RUN_SETUP_SCRIPT with the hash

# 3. Create database
mkdir -p var
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Load data
sqlite3 var/data.db < seed.sql

# 5. Clear cache
php bin/console cache:clear

# 6. Start
php -S localhost:8000 -t public/
```

---

## Admin Panel Guide

Go to **http://localhost:8000/admin** and log in with username `admin`.

### Fields explained

| Field | What it means |
|---|---|
| **Product Name** | Which hardware this firmware is for (e.g. "MMI Prime CIC") |
| **Software Version** | The version string the customer sees in their MMI — **no leading "v"** |
| **Is Latest** | ✅ ON = newest version. Customer sees "Your system is up to date!" |
| **ST Download Link** | Google Drive link for **ST (SanDisk) flash** devices — CIC hardware |
| **GD Download Link** | Google Drive link for **GD (GigaDevice) flash** devices — NBT / EVO hardware |
| **Legacy Link** | Old combined folder link. Leave blank for new entries. |

**Hardware types:**
- **CIC** = older BMW iDrive with CD drive. Uses ST link only.
- **NBT** = mid-generation iDrive. Uses both ST and GD links.
- **EVO** = latest iDrive. Uses both ST and GD links.
- **LCI** = post-2017 facelift models — separate entries from standard.

---

### Adding a new firmware release (step by step)

When a new firmware version is released (for example v3.3.8):

**1.** Go to the admin panel → **All Versions**.

**2.** Filter by product name. Find the current entry with **Is Latest = ✅**. Edit it:
   - Paste in the download links for customers upgrading *from* this version
   - **Untick Is Latest**
   - Save

**3.** Click **Add New Version**:
   - Select the correct **Product Name**
   - Enter the **Software Version** without a "v" prefix (e.g. `3.3.8.mmipri.c`)
   - Paste the Google Drive links
   - Leave **Is Latest** unticked for now
   - Save

**4.** Edit the new entry you just created → **tick Is Latest** → Save.

**5.** Test on the customer page: enter an old version number and verify the correct links appear.

> ⚠️ **Wrong firmware links destroy customer hardware. Always double-check before saving.**

---

### Changing the admin password

```bash
# Generate a new hash
php bin/console security:hash-password

# Edit .env and replace the ADMIN_PASSWORD_HASH value
nano .env

# Restart the server
```

---

## Project Structure

```
carplay-firmware/
├── public/
│   └── index.php                          ← web entry point
├── src/
│   ├── Kernel.php
│   ├── Entity/
│   │   └── SoftwareVersion.php            ← database model
│   ├── Repository/
│   │   └── SoftwareVersionRepository.php  ← DB queries
│   └── Controller/
│       ├── SoftwareDownloadController.php ← customer page
│       ├── SoftwareApiController.php      ← POST /api/carplay/software/version
│       ├── SecurityController.php         ← admin login/logout
│       └── Admin/
│           ├── DashboardController.php    ← admin home
│           └── SoftwareVersionCrudController.php ← admin CRUD
├── templates/
│   ├── carplay/
│   │   └── software_download.html.twig   ← customer page HTML
│   └── admin/
│       ├── login.html.twig
│       └── dashboard.html.twig
├── migrations/
│   └── Version20240101000001.php          ← creates software_version table
├── config/
│   ├── bundles.php
│   ├── routes.yaml
│   └── packages/
│       ├── doctrine.yaml
│       ├── framework.yaml
│       ├── security.yaml
│       └── twig.yaml
├── var/
│   └── data.db                            ← SQLite database (created by setup.sh)
├── seed.sql                               ← all firmware entries as SQL
├── setup.sh                               ← run once to set everything up
├── .env                                   ← app config
└── composer.json                          ← PHP dependency definitions
```

---

## How the Version Matching Works

When a customer submits the form, the API (`SoftwareApiController`) does this:

1. **Validates** that `version` and `hwVersion` are not empty.

2. **Detects hardware family** by matching `hwVersion` against regex patterns:

   | HW Version Pattern | Hardware | Flash chip | Products |
   |---|---|---|---|
   | `CPAA_YYYY.MM.DD` | Standard | ST | CIC |
   | `CPAA_G_YYYY.MM.DD` | Standard | GD | NBT, EVO |
   | `B_C_YYYY.MM.DD` | LCI | ST | LCI CIC |
   | `B_N_G_YYYY.MM.DD` | LCI | GD | LCI NBT |
   | `B_E_G_YYYY.MM.DD` | LCI | GD | LCI EVO |

3. **Strips a leading `v`** from the software version string.

4. **Queries the database** for a case-insensitive match on `system_version`.

5. **Cross-checks** that the matched entry belongs to the correct hardware family (LCI vs standard) and hardware type (CIC / NBT / EVO).

6. **Returns JSON** with the correct download link(s), or an error message.

This logic is a faithful port of the original `ConnectedSiteController::softwareDownload()` — all response keys, error messages, and edge cases are identical.

---

## Troubleshooting

**Autoload errors after install**
```bash
composer dump-autoload -o
```

**Cache errors**
```bash
php bin/console cache:clear
```

**Database is empty**
```bash
sqlite3 var/data.db < seed.sql
```

**Permission errors on var/**
```bash
chmod -R 775 var/
```

**Port 8000 already in use**
```bash
php -S localhost:8080 -t public/
```
