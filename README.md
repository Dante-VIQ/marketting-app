# 🧩 Vumbi API – Marketing Operations Platform

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20)](https://laravel.com)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777bb4)](https://php.net)
[![Hackathon](https://img.shields.io/badge/Agents%20for%20Humans-2026-ff6b6b)](https://agentsforhumans.devpost.com)

> **The business operating system and API backend for Vumbi AI – a complete marketing intelligence platform.**

This Laravel application provides the data models, business services, and API endpoints that power the [Vumbi AI Agent](https://github.com/Dante-VIQ/strands-agent). It handles brands, analytics, SEO, leads, campaigns, content, affiliate data, and governance.

---

## 🎯 Purpose

- **Central data store** – All business data in one place
- **Business services** – AI content generation, lead management, SEO analysis
- **API gateway** – Secure endpoints for the autonomous agent
- **Governance** – Guardian audit logging, policies, and incident management
- **Human control plane** – UI for monitoring and approving agent actions


---

## 🏗️ Architecture Diagram (Mermaid)

```mermaid
graph TB
    subgraph "Presentation Layer"
        UI["Livewire UI - Briefs, Actions, SEO, Guardian"]
        API_Routes["/api/agent/*"]
        Web_Routes["/briefs, /actions, /seo"]
    end

    subgraph "Service Layer"
        subgraph "AI Services"
            AI_Gateway["AiGatewayService - Gemini Integration"]
            Brief_Gen["BriefGeneratorService"]
            Content_Gen["ContentGeneratorService - Content & SEO Meta"]
        end

        subgraph "Business Services"
            Lead_Mgr["LeadManagerService - Scoring & Follow-up"]
            SEO_Asst["SeoAssistantService - Analysis & Recommendations"]
            Scanner["PageScannerService - Page Capture"]
            Analytics["AnalyticsService"]
            Campaign["CampaignService"]
        end

        subgraph "Governance"
            Guardian["GuardianService - Policies, Audit, Incidents"]
            Verify["VerificationService"]
            Learn["LearningService"]
        end
    end

    subgraph "Data Layer"
        Models["Models - Brand, SeoIssue, Lead, Campaign, ContentDraft, AiAction, AgentExperience, ActionVerification"]
    end

    subgraph "External"
        Gemini[(Google Gemini)]
        Ahrefs[(Ahrefs API)]
        Agent["Strands Agent - TypeScript"]
    end

    subgraph "Database"
        DB[(MySQL - 30+ Tables)]
    end

    UI --> Models
    API_Routes --> AI_Gateway
    API_Routes --> Lead_Mgr
    API_Routes --> SEO_Asst
    API_Routes --> Guardian
    API_Routes --> Verify
    API_Routes --> Learn

    AI_Gateway --> Gemini
    SEO_Asst --> Ahrefs
    Agent --> API_Routes

    AI_Gateway --> Models
    Lead_Mgr --> Models
    SEO_Asst --> Models
    Guardian --> Models
    Verify --> Models
    Learn --> Models

    Models --> DB
```

---

## 🔄 Integration with Strands Agent

```mermaid

graph TB
    subgraph Agent["🤖 Strands Agent (TypeScript)"]
        direction TB
        Supervisor["🧠 Supervisor\nOrchestrates workflow"]
        Specialists["🎯 Specialists\nSEO, Lead, Content"]
        Intelligence["🔮 Intelligence\nDecision Engine"]
        Memory["💾 Memory\nExperience Store"]
        Policies["🛡️ Policies\nSafety & Governance"]

        Supervisor --> Specialists
        Specialists --> Intelligence
        Intelligence --> Memory
        Memory --> Policies
        Policies -->|"API Calls"| Gateway
    end

    subgraph Laravel["📦 Laravel API Gateway"]
        direction TB
        Gateway["🚪 /api/agent/*"]

        Gateway --> Opportunities["🔍 /opportunities\n→ OpportunityService"]
        Gateway --> Analytics["📊 /analytics\n→ AnalyticsService"]
        Gateway --> SEO["🔎 /seo/issues\n→ SeoAssistantService"]
        Gateway --> Leads["👤 /leads/pending\n→ LeadManagerService"]
        Gateway --> Campaigns["📢 /campaigns\n→ CampaignService"]
        Gateway --> Actions["⚡ /actions/pending\n→ ActionApprovalService"]
        Gateway --> Content["✍️ /content/generate\n→ ContentGeneratorService"]
        Gateway --> Learn["📈 /learn\n→ LearningService"]
        Gateway --> Verify["✅ /verification\n→ VerificationService"]
        Gateway --> Health["🏥 /health\n→ GuardianService"]
    end

    subgraph Services["🛠️ Service Layer"]
        AI["🧠 AI Services\n• AiGateway\n• BriefGenerator\n• ContentGenerator"]
        Lead["👤 Lead Services\n• LeadManager\n• Scoring & Follow-up"]
        SEO_Svc["🔎 SEO Services\n• SeoAssistant\n• PageScanner"]
        Guardian["🛡️ Guardian\n• Policies\n• Audit\n• Incidents"]
        Verification["✅ Verification\n• Action Verification\n• Metrics Comparison"]
        Learning["📈 Learning\n• Experience Memory\n• Pattern Analysis"]
    end

    subgraph Data["🗄️ Data Layer"]
        Models["📋 Models\nBrand, SeoIssue, Lead,\nCampaign, ContentDraft,\nAiAction, AgentExperience,\nActionVerification, GuardianAuditLog"]
        DB(("💾 MySQL Database"))
    end

    Opportunities --> AI
    Opportunities --> Guardian
    Analytics --> Data
    SEO --> SEO_Svc
    Leads --> Lead
    Campaigns --> Data
    Actions --> Guardian
    Content --> AI
    Learn --> Learning
    Verify --> Verification
    Health --> Guardian

    AI --> Models
    Lead --> Models
    SEO_Svc --> Models
    Guardian --> Models
    Verification --> Models
    Learning --> Models
    Models --> DB

    style Agent fill:#4CAF50,color:#fff,stroke:#2E7D32,stroke-width:2px
    style Laravel fill:#2196F3,color:#fff,stroke:#0D47A1,stroke-width:2px
    style Services fill:#FF9800,color:#fff,stroke:#E65100,stroke-width:2px
    style Data fill:#9C27B0,color:#fff,stroke:#4A148C,stroke-width:2px
```

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+
- Composer
- MySQL 8.0+
- Node.js (for asset compilation)
- Google Gemini API key

### 1. Clone the Repository

```bash
git clone https://github.com/Dante-VIQ/marketting-app.git
cd marketting-app
``

### 2. Install Dependencies

```bash
composer install
npm install && npm run build
``

### 3. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
``

## Edit .env:

```env
APP_NAME="Vumbi Marketing Platform"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vumbi
DB_USERNAME=root
DB_PASSWORD=

# Agent Authentication
AGENT_API_KEY=your_super_secret_key_here

# AI Services
GEMINI_API_KEY=your_gemini_key_here

# Ahrefs (optional)
AHREFS_API_KEY=your_ahrefs_key

4. Run Migrations & Seeders
bash

php artisan migrate
php artisan db:seed --class=BrandSeeder

5. Start the Server
bash

php artisan serve
``

### 📁 Directory Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── AgentController.php      # Agent API endpoints
│   │   ├── BriefController.php      # AI Brief UI
│   │   ├── ActionController.php     # Action management
│   │   ├── SeoController.php        # SEO management
│   │   └── GuardianController.php   # Governance UI
│   └── Middleware/
│       └── VerifyApiKey.php         # Agent authentication
├── Models/
│   ├── Brand.php                    # Tenant/brand management
│   ├── AnalyticsSnapshot.php        # Analytics data
│   ├── SeoIssue.php                 # SEO issues
│   ├── Lead.php                     # Lead management
│   ├── Campaign.php                 # Campaign tracking
│   ├── ContentDraft.php             # Generated content
│   ├── AiAction.php                 # AI action queue
│   ├── AgentExperience.php          # Agent learning memory
│   ├── ActionVerification.php       # Action verification
│   ├── GuardianAuditLog.php         # Audit trail
│   ├── GuardianPolicy.php           # Governance policies
│   └── KnowledgeBase.php            # Business knowledge
├── Services/
│   ├── AI/
│   │   ├── AiGatewayService.php     # Gemini AI integration
│   │   ├── BriefGeneratorService.php # AI brief generation
│   │   └── ContentGeneratorService.php # Content generation
│   ├── Lead/
│   │   └── LeadManagerService.php   # Lead management
│   ├── SEO/
│   │   ├── SeoAssistantService.php  # SEO analysis
│   │   └── PageScannerService.php   # Page scanning
│   ├── Guardian/
│   │   └── GuardianService.php      # Governance
│   └── Analytics/
│       └── AnalyticsService.php     # Analytics
├── routes/
│   ├── api.php                      # API routes
│   └── web.php                      # UI routes
└── database/
    └── migrations/                  # Database migrations
``
### 🔌 API Endpoints

All endpoints require the X-API-Key header.
Method	Endpoint	Purpose
Opportunities
GET	/api/agent/opportunities/{brandId}	Fetch all opportunities
Analytics
GET	/api/agent/analytics/{brandId}	Fetch analytics data
SEO
GET	/api/agent/seo/issues/{brandId}	Fetch SEO issues
GET	/api/agent/seo/issue/{brandId}/{issueId}	Get specific issue
# 🧩 Vumbi API — Marketing Operations Platform

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT) [![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20)](https://laravel.com) [![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777bb4)](https://php.net)

A concise, production-ready API backend that powers the Vumbi AI marketing agent. Provides tenant (brand) data, analytics, SEO tooling, lead management, content generation, and governance.

## Table of Contents

- Purpose
- Quick Start
- Configuration
- API Endpoints (summary)
- Key Models
- Project Layout
- Architecture (Mermaid)
- Testing
- License & Acknowledgments

## Purpose

- Central data store for brands and marketing artifacts
- Business services: AI brief & content generation, lead workflows, SEO analysis
- API gateway for the Strands agent and UI clients
- Governance: audit logs, verification, incident reporting

## Quick Start

Prerequisites:

- PHP 8.3+, Composer
- MySQL 8.0+
- Node.js (for building assets)
- Optional: Google Gemini & Ahrefs API keys

Clone and install:

```bash
git clone https://github.com/Dante-VIQ/marketting-app.git
cd marketting-app
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=BrandSeeder
php artisan serve --host=127.0.0.1 --port=8000
````

Configuration notes:

Create or edit `.env` with the values below (example):

```env
APP_NAME="Vumbi Marketing Platform"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vumbi
DB_USERNAME=root
DB_PASSWORD=

# Agent Authentication
AGENT_API_KEY=your_agent_api_key_here

# AI Services
GEMINI_API_KEY=your_gemini_key_here

# Ahrefs (optional)
AHREFS_API_KEY=your_ahrefs_key
```

## API Endpoints (summary)

All agent endpoints require the `X-API-Key` header for authentication.

| Area          | Method | Endpoint                                   | Purpose                       |
| ------------- | -----: | ------------------------------------------ | ----------------------------- |
| Opportunities |    GET | /api/agent/opportunities/{brandId}         | Fetch opportunities for brand |
| Analytics     |    GET | /api/agent/analytics/{brandId}             | Fetch analytics snapshot      |
| SEO           |    GET | /api/agent/seo/issues/{brandId}            | List SEO issues               |
| SEO           |    GET | /api/agent/seo/issue/{brandId}/{issueId}   | Get specific SEO issue        |
| SEO           |   POST | /api/agent/seo/analyze/{brandId}/{issueId} | Run analysis on issue         |
| Leads         |    GET | /api/agent/leads/pending/{brandId}         | Pending leads for brand       |
| Leads         |   POST | /api/agent/lead/follow-up/{brandId}        | Generate follow-up content    |
| Content       |   POST | /api/agent/content/generate                | Generate content draft        |
| Actions       |   POST | /api/agent/actions/pending                 | Create pending action         |
| Scan          |   POST | /api/agent/scan/{brandId}                  | Trigger page scan             |
| Verification  |   POST | /api/agent/verification/start/{brandId}    | Start verification flow       |
| Learning      |   POST | /api/agent/learn/{brandId}                 | Record learning/example       |
| Health        |    GET | /api/agent/ai/ping                         | AI service health check       |

For a full list, see the route definitions in `routes/api.php`.

## Key Models (overview)

| Model              | Purpose                                  |
| ------------------ | ---------------------------------------- |
| Brand              | Tenant / brand configuration             |
| AnalyticsSnapshot  | Daily/periodic analytics metrics         |
| SeoIssue           | Detected SEO issues and metadata         |
| Lead               | Lead records and status                  |
| Campaign           | Campaign tracking and attribution        |
| ContentDraft       | Generated content drafts and metadata    |
| AiAction           | Queued AI actions initiated by the agent |
| AgentExperience    | Agent learning memory and examples       |
| ActionVerification | Verification results for actions         |
| GuardianAuditLog   | Audit trail for governance events        |

## Project Layout

Top-level layout (important folders):

```
app/
├── Http/
│   ├── Controllers/
│   └── Middleware/
├── Models/
├── Services/
├── Jobs/
routes/
config/
database/
public/
resources/
tests/
```

See `app/Services` for the core business logic and AI integrations.

## Architecture

Use the following Mermaid diagram when you want a visual overview (rendered by compatible viewers):

```mermaid
graph TB
  subgraph Presentation
    UI[Livewire UI]
    API[/api/agent/*]
  end
  subgraph Services
    AI[AI Gateway / Gemini]
    SEO[SeoAssistant]
    Lead[LeadManager]
    Content[ContentGenerator]
    Guardian[GuardianService]
  end
  subgraph Data
    Models[Models / DB]
  end
  API --> AI
  API --> Lead
  API --> SEO
  AI --> Models
  Lead --> Models
  SEO --> Models
```

## Testing

Run unit and feature tests:

```bash
php artisan test
```

Quick API test:

```bash
curl -H "X-API-Key: ${AGENT_API_KEY}" http://localhost:8000/api/agent/analytics/1
```

## License & Acknowledgments

This project is licensed under the MIT License.

Thanks to Laravel, Google Gemini, and the Strands Agents SDK for the integrations and inspiration.
