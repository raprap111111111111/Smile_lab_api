cat > README.md << 'EOF'
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Smile Concept API

Dental Practice Management System (DPMS) — Backend  
**Smile Concept – Multi-Branch Dental Clinic System**  
Version: 1.0 (July 2026)

## 🚀 Tech Stack
- **Framework:** Laravel 12
- **Auth:** Laravel Sanctum
- **RBAC:** Spatie Laravel Permission
- **Logging:** Spatie Activity Log
- **Database:** MySQL 8

## 📋 Overview

A full-featured dental clinic management system supporting multiple branches, role-based access, electronic health records, scheduling, billing, inventory, and patient self-service.

### Core Objectives:
- Eliminate double-bookings across branches
- Digitize patient records and dental charts
- Automate reminders and recalls to reduce no-shows
- Streamline billing and payments (including installments)
- Ensure legal compliance (clinical notes, consents, audit trail)

## 👥 User Roles & Permissions

| Role | Key Permissions |
|------|-----------------|
| **Patient** | View own appointments, treatment plans, invoices |
| **Receptionist/Staff** | Manage appointments, check-in, process payments |
| **Dentist** | Access patient charts, write clinical notes, prescribe |
| **Owner/Admin** | Full access, analytics, staff management, reports |

## 🛠️ Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# 🦷 smilelab AI - Roboflow Dental X-Ray Integration

Complete setup guide for AI-powered dental X-ray analysis using Roboflow Computer Vision.

---

## 📋 Table of Contents

- [Overview](#-overview)
- [System Requirements](#-system-requirements)
- [Architecture](#-architecture)
- [Prerequisites](#-prerequisites)
- [Backend Setup (Laravel)](#-backend-setup-laravel)
- [Frontend Setup (Flutter)](#-frontend-setup-flutter)
- [Roboflow Setup](#-roboflow-setup)
- [Docker Setup](#-docker-setup)
- [Running the Queue Worker](#-running-the-queue-worker)
- [Environment Variables](#-environment-variables)
- [Testing the Integration](#-testing-the-integration)
- [Troubleshooting](#-troubleshooting)
- [Production Deployment](#-production-deployment)

---

## 🎯 Overview

**smilelab** uses **Roboflow's dentalXray Computer Vision Model** to automatically detect and analyze:

- 🦷 Individual teeth (molars, canines, incisors, premolars)
- 🩻 Anatomical regions (maxillary/mandibular)
- ⚠️ Dental conditions with confidence scores
- 📍 Precise tooth locations with bounding boxes

### Flow Diagram

```
┌─────────────┐       ┌──────────────┐       ┌─────────────────┐       ┌──────────────┐
│   Flutter   │──────▶│   Laravel    │──────▶│  Queue Worker   │──────▶│   Roboflow   │
│  Frontend   │Upload │   Backend    │ Job   │ (php artisan    │ HTTP  │  Inference   │
│             │       │              │       │  queue:work)    │       │    Server    │
└─────────────┘       └──────┬───────┘       └────────┬────────┘       └──────┬───────┘
                             │                        │                       │
                             │                        │◀──────────────────────┘
                             │                        │  Detected Conditions
                             ▼                        ▼
                      ┌──────────────────────────────────────┐
                      │   Database (patient_attachments)     │
                      │   - detected_conditions (JSON)       │
                      │   - scan_status: completed           │
                      │   - scan_confidence                  │
                      └──────────────────────────────────────┘s
```

---

## 💻 System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **OS** | macOS/Linux/Windows | macOS 12+ / Ubuntu 22.04 |
| **RAM** | 8 GB | 16 GB |
| **CPU** | 4 cores | 8 cores |
| **Storage** | 20 GB free | 50 GB free |
| **Docker** | 20.10+ | Latest |
| **PHP** | 8.2+ | 8.3 |
| **Node.js** | 18+ | 20 LTS |
| **Flutter** | 3.24+ | Latest stable |

---

## 🏗️ Architecture

```
smile_concept_project/
├── smile_lab_api/           ← Laravel Backend
│   ├── app/
│   │   ├── Domain/PatientAttachments/
│   │   ├── Jobs/ProcessDentalXrayJob.php
│   │   └── Services/RoboflowService.php
│   └── .env
│
└── smile_concept_web/            ← Flutter Frontend
    ├── lib/
    │   ├── core/config/api_config.dart
    │   └── presentation/pages/patient_attachments/
    └── .env
```

---

## ✅ Prerequisites

### 1. Install Docker Desktop

**macOS:**
```bash
brew install --cask docker
```

**Ubuntu:**
```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
```

**Windows:** Download from [docker.com](https://www.docker.com/products/docker-desktop)

Verify installation:
```bash
docker --version
# Docker version 24.x.x
```

### 2. Install PHP & Composer

**macOS:**
```bash
brew install php@8.3 composer
```

**Ubuntu:**
```bash
sudo apt install php8.3 php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl composer
```

### 3. Install Flutter

Follow the official guide: [flutter.dev/docs/get-started/install](https://flutter.dev/docs/get-started/install)

```bash
flutter doctor
```

### 4. Install Node.js (for Vite)

```bash
# Using nvm (recommended)
nvm install 20
nvm use 20
```

---

## 🐳 Docker Setup — Roboflow Inference Server

The Roboflow inference server runs locally in a Docker container.

### Pull and Run the Container

```bash
docker run -d \
  --name roboflow-inference \
  -p 9001:9001 \
  --restart unless-stopped \
  roboflow/roboflow-inference-server-cpu
```

### Verify It's Running

```bash
docker ps
```

You should see:
```
CONTAINER ID   IMAGE                                       PORTS                    NAMES
abc123def456   roboflow/roboflow-inference-server-cpu      0.0.0.0:9001->9001/tcp   roboflow-inference
```

### Test the Server

```bash
curl http://localhost:9001/
```

Expected response:
```json
{"name":"Roboflow Inference Server","version":"..."}
```

### Docker Commands Cheat Sheet

```bash
# View logs
docker logs -f roboflow-inference

# Stop the container
docker stop roboflow-inference

# Start it again
docker start roboflow-inference

# Remove the container
docker rm -f roboflow-inference

# Update to latest version
docker pull roboflow/roboflow-inference-server-cpu
docker rm -f roboflow-inference
# Then re-run the docker run command above
```

### GPU Version (Optional — Much Faster)

If you have an NVIDIA GPU:

```bash
docker run -d \
  --name roboflow-inference \
  --gpus all \
  -p 9001:9001 \
  --restart unless-stopped \
  roboflow/roboflow-inference-server-gpu
```

---

## 🤖 Roboflow Setup

### 1. Create Roboflow Account

Sign up at [roboflow.com](https://roboflow.com)

### 2. Get API Key

1. Navigate to **Settings → API**
2. Copy your **Private API Key**
3. Save it — you'll add it to `.env`

### 3. Model Information

**Model:** `dentalxray-s3wqb/2`
- **Type:** Computer Vision (Object Detection)
- **Purpose:** Dental X-ray tooth and condition detection
- **Endpoint:** `http://localhost:9001/dentalxray-s3wqb/2`

### 4. Test the Model

```bash
curl -X POST "http://localhost:9001/dentalxray-s3wqb/2?api_key=YOUR_API_KEY" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "image=BASE64_ENCODED_IMAGE"
```

---

## 🔧 Backend Setup (Laravel)

### 1. Clone & Install

```bash
git clone https://github.com/YOUR_ORG/smile_lab_api.git
cd smile_lab_api

composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database

```bash
php artisan migrate --seed
```

### 3. Install Laravel Passport

```bash
php artisan passport:install
```

### 4. Set Up Storage

```bash
php artisan storage:link
chmod -R 755 storage/
```

### 5. Configure Environment Variables

Edit `.env`:

```env
# ═══════════════════════════════════════════
# APPLICATION
# ═══════════════════════════════════════════
APP_NAME=smilelab
APP_ENV=local
APP_URL=http://localhost
APP_KEY=base64:...

# ═══════════════════════════════════════════
# DATABASE
# ═══════════════════════════════════════════
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smile_concept
DB_USERNAME=root
DB_PASSWORD=

# ═══════════════════════════════════════════
# QUEUE (IMPORTANT for AI processing)
# ═══════════════════════════════════════════
QUEUE_CONNECTION=database

# ═══════════════════════════════════════════
# ROBOFLOW AI INTEGRATION
# ═══════════════════════════════════════════
ROBOFLOW_API_URL=http://localhost:9001
ROBOFLOW_MODEL=dentalxray-s3wqb/2
ROBOFLOW_API_KEY=your_private_api_key_here
ROBOFLOW_CONFIDENCE_THRESHOLD=40
ROBOFLOW_OVERLAP_THRESHOLD=30

# ═══════════════════════════════════════════
# FILE STORAGE
# ═══════════════════════════════════════════
FILESYSTEM_DISK=public
```

### 6. Create Queue Table

```bash
php artisan queue:table
php artisan migrate
```

### 7. Start Laravel Server

```bash
php artisan serve
# Server running at http://127.0.0.1:8000
```

---

## 🎨 Frontend Setup (Flutter)

### 1. Clone & Install

```bash
git clone https://github.com/YOUR_ORG/smile_concept_web.git
cd smile_concept_web

flutter pub get
```

### 2. Configure Environment

Create `.env`:

```env
# ═══════════════════════════════════════════
# ENVIRONMENT
# ═══════════════════════════════════════════
ENVIRONMENT=development

# ═══════════════════════════════════════════
# API BASE URL
# ═══════════════════════════════════════════
API_BASE_URL=http://localhost/api/v1
```

For **production**, create `.env.production`:

```env
ENVIRONMENT=production
API_BASE_URL=https://api.smilelab.com/api/v1
```

### 3. Run the App

```bash
# Development
flutter run -d chrome

# Production build
flutter build web --release --dart-define=ENV_FILE=.env.production
```

---

## ⚙️ Running the Queue Worker

The queue worker processes X-ray uploads asynchronously.

### Development

**Option 1: Basic queue worker**
```bash
php artisan queue:work
```

**Option 2: Queue listener with retry (recommended for dev)**
```bash
php artisan queue:listen --tries=3
```

**Option 3: With Laravel Sail**
```bash
sail artisan queue:listen --tries=3
```

### Production (Supervisor)

Create `/etc/supervisor/conf.d/smilelab-worker.conf`:

```ini
[program:smilelab-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smile_lab_api/artisan queue:work --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/smile_lab_api/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start smilelab-worker:*
```

### Verify Queue is Running

```bash
# Check pending jobs
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## 🌍 Environment Variables Reference

### Backend `.env`

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_URL` | Laravel base URL | `http://localhost` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `ROBOFLOW_API_URL` | Roboflow server URL | `http://localhost:9001` |
| `ROBOFLOW_MODEL` | Model identifier | `dentalxray-s3wqb/2` |
| `ROBOFLOW_API_KEY` | Your Roboflow API key | `rf_XXXXXXXXXXXX` |
| `ROBOFLOW_CONFIDENCE_THRESHOLD` | Min confidence % | `40` |
| `ROBOFLOW_OVERLAP_THRESHOLD` | NMS overlap % | `30` |
| `FILESYSTEM_DISK` | File storage disk | `public` |

### Frontend `.env`

| Variable | Description | Example |
|----------|-------------|---------|
| `ENVIRONMENT` | Current environment | `development` |
| `API_BASE_URL` | Backend API URL | `http://localhost/api/v1` |

---

## 🧪 Testing the Integration

### 1. Start All Services

Open 4 terminal windows:

**Terminal 1 — Docker (Roboflow):**
```bash
docker start roboflow-inference
docker logs -f roboflow-inference
```

**Terminal 2 — Laravel Backend:**
```bash
cd smile_lab_api
php artisan serve
```

**Terminal 3 — Queue Worker:**
```bash
cd smile_lab_api
php artisan queue:listen --tries=3
```

**Terminal 4 — Flutter Frontend:**
```bash
cd smile_concept_web
flutter run -d chrome
```

### 2. Upload Test Flow

1. Log in to the app
2. Navigate to **Patient Attachments** → **Upload**
3. Select a patient (e.g., Ryan)
4. Choose category: **X-Ray**
5. Toggle **AI X-Ray Analysis** ON
6. Upload a dental X-ray image
7. Watch the queue worker terminal — you'll see:

```
[2025-01-20 12:00:00] Processing: App\Jobs\ProcessDentalXrayJob
[2025-01-20 12:00:02] Sending to Roboflow: http://localhost:9001/dentalxray-s3wqb/2
[2025-01-20 12:00:05] ✅ Detected 32 conditions
[2025-01-20 12:00:05] Processed: App\Jobs\ProcessDentalXrayJob
```

### 3. Verify Results

The attachment should now show:
- **scan_status:** `completed`
- **scan_confidence:** `71.1`
- **detected_conditions:** Array of detected teeth/regions

---

## 🐛 Troubleshooting

### Docker Container Won't Start

```bash
# Check if port 9001 is in use
lsof -i :9001

# Kill process using port
kill -9 <PID>

# Restart container
docker restart roboflow-inference
```

### Queue Worker Not Processing

```bash
# Restart queue
php artisan queue:restart

# Clear cache
php artisan cache:clear
php artisan config:clear

# Check failed jobs
php artisan queue:failed
```

### Roboflow API Errors

**403 Forbidden:**
- Verify `ROBOFLOW_API_KEY` in `.env`
- Check your Roboflow account quota

**Connection Refused:**
```bash
# Check container is running
docker ps

# Restart it
docker restart roboflow-inference
```

**Timeout Errors:**
- Increase queue timeout: `php artisan queue:work --timeout=300`
- Consider GPU version for faster inference

### Images Not Loading (403)

Make sure `storage:link` is created:
```bash
php artisan storage:link
```

Check file permissions:
```bash
chmod -R 755 storage/app/public
```

### Flutter Build Errors

```bash
flutter clean
flutter pub get
flutter pub upgrade
flutter run
```

---

## 🚀 Production Deployment

### Backend (Laravel)

1. **Set production environment:**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.smilelab.com
   ```

2. **Optimize:**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Set up Supervisor** (see Queue Worker section)

4. **Set up SSL** with Let's Encrypt or Cloudflare

