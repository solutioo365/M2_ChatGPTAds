# Konfiguration

Pfad: **Solutioo → ChatGPT Produktsuche → Einstellungen**  
oder Stores → Configuration → Solutioo → ChatGPT Produktsuche

Scope: Default / Website / Store View. Für Multi-Store die Store View oben wählen.

## Allgemein

Modul je Scope ein- oder ausschalten. Aus = kein Cron, kein HTTPS-Feed, keine Erzeugung.

## Händler & Richtlinien

Pflicht für eine saubere Darstellung in ChatGPT:

- Händlername (sonst Shopname)
- Ziel-Land ISO-3166 (`DE`, `AT`, `US`, …)
- Datenschutz, AGB, Rückgabe (HTTPS)
- optional Versand, FAQ, Rückgabefrist

Checkout bleibt im Shop.

## Feed-Inhalt

- **Format:** JSONL empfohlen (Varianten verschachtelt)
- **Varianten-Export:** alle kaufbaren SKUs oder nur Hauptprodukte
- **Unvollständige ausschließen:** ohne Bild/Preis/Text/URL nicht exportieren
- **Medien-CDN:** nur setzen, wenn Magento-Media-URL nicht schon auf S3/CDN zeigt
- **Sample-Limit:** für die OpenAI-Erstvalidierung z. B. `50`
- **UTM:** Standard `chatgpt` / `feed` / `product_search`

## Mapping

Attributcodes für Marke, GTIN, MPN, Farbe, Größe, Material, Geschlecht, Altersgruppe, Google-Kategorie.

## HTTPS / SFTP / API

1. Token erzeugen, Feed-URL ins Merchant-Portal kopieren (falls URL-Lieferung gewünscht)
2. Nach Freigabe SFTP-Host von OpenAI eintragen, Dateiname stabil halten
3. API: Modus **Ads** (`https://api.ads.openai.com/v1`) + Feed-ID `fd_…` aus dem Ads Manager, oder Modus **Commerce** (`https://api.openai.com/v1`) und Feed in der Übersicht anlegen. Einzelartikel: `bin/magento solutioo:chatgpt:feed:sync-api --sku=SKU`

## Zeitplan

Standard `*/15 * * * *`. Magento-Cron muss laufen. Optional automatisch SFTP und/oder API nach jeder Erzeugung.

## Measurement Pixel

Optional, unabhängig vom Feed.

1. Pixel-ID im OpenAI Ads Manager unter Conversions anlegen
2. Gruppe **Measurement Pixel** aktivieren und ID eintragen
3. Events nach Bedarf an- oder ausschalten
4. Cookiebot: Einwilligung beachten (Standard ja, Kategorie `marketing`)

Events: `page_viewed`, `contents_viewed` (PDP), `items_added`, `checkout_started`, `order_created`.
