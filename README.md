# Pulsar

Eina interna per fer auditories **SEO**, anàlisis **GEO** (visibilitat als cercadors amb IA) i generar **textos legals** (privadesa, cookies i, per a botigues en línia, cancel·lació i devolucions).

## Requisits

- PHP 8.1 o superior amb `curl`, `mbstring` i `iconv` (no necessita `ext-dom` ni `gd`).
- Composer (només per instal·lar FPDF).
- Apache (inclou `.htaccess`) o Nginx (vegeu més avall).

## Instal·lació

```bash
composer install
php bin/hash.php "la-teva-contrasenya-segura"   # copia el hash a config.php
```

Edita `config.php`:

- `users`: usuaris i contrasenyes (hash recomanat; també admet text pla).
- `company` / `company_url`: apareixen a la portada i al peu dels PDF.
- Límits d’URL, temps d’espera, retenció d’informes, etc.

Usuari per defecte: **admin / pulsar2026** (canvia’l abans de publicar).

La carpeta `storage/` ha de tenir permisos d’escriptura per a PHP.

Per provar-ho en local:

```bash
php -S 127.0.0.1:8000
```

## Mòduls

| Mòdul | Què fa |
|---|---|
| **Anàlisi SEO** (`seo.php`) | Analitza fins a 20 URL: estat HTTP, redireccions, HTTPS, TTFB, compressió, scripts bloquejants, noindex, canonical, robots.txt, sitemap, URL, hreflang, title, description, H1-H6, contingut, ràtio text/HTML, lang, imatges (alt, dimensions, lazy), enllaços, Open Graph, Twitter Card, schema, viewport, favicon, contingut mixt i capçaleres de seguretat. Genera un PDF amb portada, resum executiu, prioritats i solucions. |
| **GEO · IA** (`geo.php`) | Sense API ni tokens. Revisa l’accés de 14 rastrejadors d’IA al robots.txt, si el tallafoc/CDN els bloqueja (prova amb el seu user-agent), nosnippet, dependència de JavaScript, llms.txt, encapçalaments en forma de pregunta, resposta directa, llistes/taules, dades i xifres, autoria, actualitat, fonts, entitat de marca (Organization + sameAs) i schema. Proposa un esborrany de `llms.txt`. També s’exporta a PDF. |
| **Textos legals** (`legal.php`) | Genera la Política de privadesa, la Política de cookies i, si el web és una botiga en línia, la Política de cancel·lació, desistiment i devolucions (termini, costos de devolució, reemborsament, excepcions, garantia legal i model de formulari de desistiment) en **català, castellà, anglès o francès**, segons la legislació espanyola (RGPD + LOPDGDD + LSSI-CE) o el RGPD genèric de la UE, a partir de les dades del titular, les finalitats, els proveïdors i els serveis amb cookies. Es poden copiar en HTML o text, o descarregar en `.html`. |

Els textos de cada idioma són a `inc/legal/{ca,es,en,fr}.php`.

Els informes es desen com a JSON a `storage/reports/` i s’esborren automàticament passats `report_retention_days` dies.

## Seguretat

- Inici de sessió amb hash bcrypt, regeneració de la sessió, CSRF a tots els formularis i bloqueig per IP després de 5 intents fallits.
- L’analitzador bloqueja IP privades o reservades (protecció SSRF), valida cada redirecció i fixa la IP resolta.
- `.htaccess` denega l’accés a `config.php`, `inc/`, `storage/`, `vendor/` i `bin/`.

### Nginx

```nginx
location ~ ^/(inc|storage|vendor|bin)/ { deny all; }
location ~ ^/(config\.php|composer\.(json|lock)|README\.md)$ { deny all; }
```

## Avís

Els textos legals són plantilles orientatives: cal revisar-los amb un professional i ajustar-los a l’activitat real del lloc. Les puntuacions SEO/GEO són heurístiques i s’han de complementar amb Google Search Console, PageSpeed Insights i l’analítica.
