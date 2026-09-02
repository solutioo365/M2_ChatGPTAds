# Magento 2 ChatGPT Produktsuche

Modul für Magento Open Source und Adobe Commerce. Der Katalog geht als Produkt-Feed an die [ChatGPT-Produktsuche](https://chatgpt.com/de-DE/merchants/). Checkout und Zahlung bleiben im Shop.

Benötigt [`solutioo/module-base`](https://github.com/solutioo365/M2_SolutiooBase).

## Funktionen

- eigener Feed je Store View
- JSONL, CSV/TSV, optional Gzip
- HTTPS (Token), SFTP, OpenAI Ads-API oder Commerce-API
- Validierung, Varianten, Promotions, Reviews
- Übersicht, CLI, REST, Cron
- optionales Measurement Pixel

## Voraussetzungen

- Magento 2.4 / Adobe Commerce 2.4
- PHP 8.1 oder neuer
- `solutioo/module-base` ^1.0
- laufender Magento-Cron
- Händlerzugang unter [chatgpt.com/merchants](https://chatgpt.com/de-DE/merchants/)

## Installation

### Composer

```bash
composer require solutioo/module-chatgpt-product-search
bin/magento module:enable Solutioo_Base Solutioo_ChatGptProductSearch
bin/magento setup:upgrade
bin/magento cache:flush
```

### app/code

1. [Solutioo Base](https://github.com/solutioo365/M2_SolutiooBase) nach `app/code/Solutioo/Base`
2. dieses Modul nach `app/code/Solutioo/ChatGptProductSearch`
3. dieselben Magento-Befehle wie oben

## Einrichtung

1. Solutioo → ChatGPT Produktsuche → Einstellungen
2. Modul aktivieren, Händlername, Land, Datenschutz-/AGB-/Rückgabe-URL
3. Feed-Token erzeugen, Mapping prüfen
4. in der Übersicht Feed erzeugen und prüfen
5. nach der Freigabe durch OpenAI SFTP oder API eintragen
6. bei Bedarf Measurement Pixel unter Einstellungen aktivieren

## CLI

```bash
bin/magento solutioo:chatgpt:feed:generate [--store=1] [--sftp] [--api]
bin/magento solutioo:chatgpt:feed:validate --store=1
bin/magento solutioo:chatgpt:feed:upload --store=1
bin/magento solutioo:chatgpt:feed:sync-api --store=1
bin/magento solutioo:chatgpt:feed:sync-api --store=1 --sku=ARTIKELNUMMER
bin/magento solutioo:chatgpt:feed:status
```

## REST

Recht `Solutioo_ChatGptProductSearch::api`:

- `POST /V1/solutioo/chatgpt/feed/generate/:storeId`
- `GET /V1/solutioo/chatgpt/feed/validate/:storeId`
- `GET /V1/solutioo/chatgpt/feed/status/:storeId`
- `POST /V1/solutioo/chatgpt/feed/sync-api/:storeId`
- `POST /V1/solutioo/chatgpt/feed/sftp/:storeId`

## Produktattribute

Gruppe **ChatGPT Produktsuche**:

| Attribut | Zweck |
|----------|--------|
| `chatgpt_search` | im Katalog anzeigen |
| `chatgpt_checkout` | Checkout über ChatGPT |
| `chatgpt_exclude` | aus dem Feed nehmen |
| `chatgpt_gtin` | GTIN/EAN-Fallback |
| `chatgpt_mpn` | MPN-Fallback |

## Dateien

Feeds liegen unter `var/chatgpt_feed/store_{id}/`.

Der HTTPS-Endpunkt `/chatgpt/feed?token=…&store=…` ist für Vorschau gedacht. Für den Live-Betrieb ist SFTP üblich.

## Events

- `solutioo_chatgpt_product_map_after`
- `solutioo_chatgpt_feed_generate_after`

## Support

- [www.solutioo.de](https://www.solutioo.de)
- info@solutioo.de

## Lizenz

OSL-3.0 / AFL-3.0
