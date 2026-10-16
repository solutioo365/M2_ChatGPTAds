# Changelog

## 1.0.7 - 2026-10-16

- JSONL liefert `additional_image_urls` als Array (bis zu 10 weitere Originalbilder, ausgeblendete Galeriebilder entfallen, Basisbild zuerst) für die visuellen Anzeigenformate
- `ads_metadata` mit `custom_label_0` bis `custom_label_4` (Hauptkategorie, sale/regular, Marke, Verfügbarkeit, Unterkategorie) für Produktsets; dieselben Spalten im Google-Feed
- Google-Feed setzt `sale_price_effective_date`, JSONL setzt `target_countries` als Länderliste
- Measurement-Pixel übergibt auf der Bestellbestätigung gehashte Kundendaten an `oaiq('init')`, damit Conversions besser zugeordnet werden

## 1.0.6 - 2026-09-02

- README auf Deutsch und Englisch
- Admin-Screenshots mit Erklärungen (Übersicht, Einstellungen)
- Repository: [solutioo365/M2_ChatGPTAds](https://github.com/solutioo365/M2_ChatGPTAds)
- Composer: `www.solutioo.de/packages/`

## 1.0.5 - 2026-09-02

- Admin-Übersicht mit Store-Status, Feed-Aktionen und Protokoll
- Feeds als JSONL/CSV/TSV, optional gzip
- HTTPS-Feed, SFTP-Upload, OpenAI Ads- und Commerce-API
- Measurement Pixel (optional, Cookiebot möglich)
- CLI, REST und Cron

## 1.0.2 - 2026-08-14

- Config-Alias `includeDisabled()` für die Feed-Erzeugung

## 1.0.1 - 2026-08-14

- Admin-Menüpunkt in Übersicht umbenannt

## 1.0.0 - 2026-08-14

- Erstes Release
