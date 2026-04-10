# SEO Spider

[![Latest Release](https://img.shields.io/github/v/release/MarioDevv/web-scrapper?color=blue)](https://github.com/MarioDevv/web-scrapper/releases)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.5-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/phpstan-level%202-brightgreen)](phpstan.neon)
[![License](https://img.shields.io/badge/license-AGPL--3.0-green)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Linux-orange)]()

App de escritorio gratuita y open-source para auditorias SEO tecnicas. Rastrea cualquier web y obtiene un desglose completo de problemas — como Screaming Frog, pero gratis y sin limites de rastreo.

> Construido con [NativePHP](https://nativephp.com) + [Laravel](https://laravel.com) + [Livewire](https://livewire.laravel.com) + [Tailwind CSS](https://tailwindcss.com). Sin dependencias externas de pago.

## Por que SEO Spider?

| | **SEO Spider** | Screaming Frog |
|---|---|---|
| Precio | Gratis y open-source | 259 $/year |
| Limite de rastreo | Sin limites | 500 URLs (free) |
| Codigo | AGPL-3.0 | Propietario |
| Plataforma | Linux (Windows/macOS pronto) | Windows, macOS, Linux |
| Tecnologia | PHP 8.5 + NativePHP + Livewire | Java |

## Descarga

Descarga la ultima version desde la [pagina de releases](https://github.com/MarioDevv/web-scrapper/releases).

| Plataforma | Formato |
|----------|--------|
| Linux | `.AppImage`, `.deb` |
| Windows | Proximamente |
| macOS | Proximamente |

## 10 analizadores integrados

```
Broken Links     — Errores HTTP, cadenas de redireccion, protocolos mixtos
Meta Data        — Titles, descriptions, H1, viewport faltantes o incorrectos
Directives       — noindex, nofollow, conflictos de canonical
Headings         — Jerarquia, H2 faltantes, orden de encabezados
Content          — Paginas finas, paginas vacias
Images           — Alt text faltante, atributos alt demasiado largos
Performance      — Respuestas lentas, paginas sobredimensionadas
Security Headers — CSP, X-Frame-Options, HSTS, Referrer-Policy
Hreflang         — Codigos de idioma/region invalidos, self-references faltantes
Duplicates       — Duplicados exactos (SHA-256) y cercanos (SimHash)
```

## Dashboard y herramientas

- Vista general con graficos de codigos de estado, tiempos de respuesta, profundidad de rastreo e issues
- Tabla ordenable con 14 columnas en 9 pestanas filtrables
- Panel de detalle con info SEO, preview de SERP y analisis de enlaces
- Verificacion de enlaces externos via peticiones HEAD
- Exportacion CSV por pestana
- Carpetas para organizar tus auditorias
- Pausa y reanudacion del rastreo en cualquier momento
- Modo claro y oscuro
- Limites de rastreo configurables y politica de robots.txt

## Features

- [x] Rastreo completo de sitios web sin limites
- [x] 10 analizadores SEO integrados
- [x] Dashboard con graficos y metricas
- [x] Deteccion de duplicados exactos (SHA-256) y cercanos (SimHash)
- [x] Verificacion de enlaces externos
- [x] Preview de SERP en panel de detalle
- [x] Exportacion CSV
- [x] Modo claro y oscuro
- [x] Carpetas para organizar auditorias
- [x] Pausa / reanudacion de rastreo
- [x] Auto-actualizador integrado
- [ ] Soporte Windows
- [ ] Soporte macOS
- [ ] Rastreo de JavaScript rendering (SPA)
- [ ] Informes PDF exportables

## Desarrollo

```bash
# Requisitos: PHP 8.5+, Node 22+, Composer

git clone https://github.com/MarioDevv/web-scrapper.git
cd web-scrapper
composer install
npm install
composer run native:dev
```

## Contribuir

Encontraste un bug? [Abre un issue](https://github.com/MarioDevv/web-scrapper/issues).
Quieres aportar codigo? Los PRs son bienvenidos.

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse src
vendor/bin/pint
```

## Licencia

[AGPL-3.0](LICENSE) © Mario Perez

Licencia comercial disponible para uso propietario o integracion SaaS. Contacto: perezmario.info@gmail.com.
