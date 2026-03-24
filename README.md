# SEO Spider

Herramienta de escritorio open source para auditoría técnica SEO de sitios web. Alternativa a Screaming Frog, desarrollada con PHP 8.5, Laravel 12, Livewire 4 y NativePHP/Electron.

## Stack

| Tecnología | Uso |
|---|---|
| **PHP 8.5** | Runtime, Uri\Rfc3986\Uri, pipe operator, clone with properties |
| **Laravel 12** | Framework base, migraciones, colas, service container |
| **Livewire 4** | UI reactiva en tiempo real (polling 1s durante crawl) |
| **NativePHP/Electron** | Empaquetado como app de escritorio nativa |
| **SQLite** | Base de datos embebida (gestionada por NativePHP) |
| **Symfony HttpClient** | Motor HTTP para crawling (cURL bajo el capó) |
| **DomCrawler** | Parsing HTML (extracción de metadatos, links, directivas) |

## Arquitectura

DDD Hexagonal con un único Bounded Context `Audit` para el MVP.

```
src/
├── Audit/
│   ├── Application/     ← Use Cases (9 handlers)
│   ├── Domain/Model/    ← Aggregates, VOs, Events, Analyzers (10)
│   └── Infrastructure/  ← SQLite repos, HTTP, Parser, NativePHP delivery
└── Shared/Domain/       ← AggregateRoot, Identity, DomainEvent, Bus interfaces
```

Documentación detallada en `docs/`:
- `ARCHITECTURE.md` — Estructura, reglas, ADRs
- `DOMAIN_KNOWLEDGE.md` — Conceptos SEO, features, datos capturados
- `STRATEGIC_DDD.md` — Bounded Contexts, Context Map
- `TACTICAL_DDD.md` — Aggregates, invariantes, modelado
- `PHP85_IMPACT.md` — Features de PHP 8.5 usadas

## Features implementadas

### Analyzers (10)

| Analyzer | Detecciones |
|---|---|
| **BrokenLinkAnalyzer** | HTTP 4xx/5xx, redirect chains/loops, mixed protocols, nofollow internos |
| **MetaDataAnalyzer** | Title missing/too long/too short, meta desc, H1, viewport |
| **DirectiveAnalyzer** | noindex, nofollow, canonical missing/non-self, conflictos |
| **HeadingAnalyzer** | H2 missing/excesivos, H1 no primero, saltos de jerarquía |
| **ContentAnalyzer** | Thin content (<200 palabras), páginas vacías (<50) |
| **ImageAnalyzer** | Alt missing, alt demasiado largo (>125 chars) |
| **PerformanceAnalyzer** | Response time >1s/3s, páginas >512KB |
| **SecurityHeaderAnalyzer** | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| **HreflangAnalyzer** | Idioma/región inválidos, falta auto-referencia, duplicados |
| **DuplicateAnalyzer** | Duplicados exactos (SHA-256) y near-duplicates (SimHash) |

### UI

- **Overview** — Gráficas de status codes, response times, profundidad, issues por categoría, verificación SEO
- **Tabla principal** — 14 columnas, scroll horizontal, ordenable
- **9 tabs** — Overview, All, Internal, External, HTML, 3xx, 4xx/5xx, Issues, Noindex
- **Panel detalle** — Tabs SEO (con SERP preview), Técnico, Enlaces (internos/salientes/imágenes), Problemas
- **Enlaces externos** — Verificación HEAD request, tabla dedicada
- **Exportar CSV** — Por tab, contextual
- **Carpetas** — CRUD para organizar audits
- **Light/Dark mode** — CSS custom properties + Alpine.js
- **Pause/Resume** — Control del crawl en curso

### Crawling

- Follow redirects con tracking de cadena completa
- Respeto de robots.txt (configurable)
- Extracción de recursos (img, script, css, iframe) además de anchors
- Crawl limit configurable (max páginas + max profundidad)
- Verificación de enlaces externos via HEAD requests
- Deduplicación de URLs via frontier

## Desarrollo

```bash
# Requisitos
php >= 8.5
node >= 18
composer

# Instalación
git clone <repo>
cd web-scrapper
composer install
npm install

# Base de datos
php artisan native:migrate

# Arrancar
composer native:dev
```

### Comandos NativePHP

```bash
php artisan native:migrate         # Crear/actualizar tablas
php artisan native:migrate:fresh   # Reset completo (destructivo)
php artisan native:seed            # Ejecutar seeders
```

## Comparación con Screaming Frog

| Feature | SEO Spider | SF |
|---|---|---|
| Broken links / Errors / Redirects | ✅ | ✅ |
| Page Titles & Meta Data | ✅ | ✅ |
| Meta Robots & Directives | ✅ | ✅ |
| Hreflang Audit | ✅ | ✅ |
| Duplicate Pages (exact + near) | ✅ | ✅ |
| Crawl Limit | ✅ | ✅ |
| Security Headers | ✅ | ✅ |
| Performance Analysis | ✅ | ✅ |
| Image Alt Audit | ✅ | ✅ |
| Internal Nofollow Detection | ✅ | ✅ |
| External Link Verification | ✅ | ✅ |
| Overview Dashboard | ✅ | ✅ |
| SERP Snippet Preview | ✅ | ✅ |
| CSV Export | ✅ | ✅ |
| Pause/Resume | ✅ | ✅ |
| Save & Organize Crawls | ✅ | ✅ |
| XML Sitemap Generation | ❌ | ✅ |
| Site Visualisations (force graph) | ❌ | ✅ |
| JavaScript Rendering | ❌ | ✅ |
| Scheduling | ❌ | ✅ |
| Google Analytics Integration | ❌ | ✅ |
| Search Console Integration | ❌ | ✅ |

## Licencia

Open source. Licencia por definir.
