# MAP_OPTIMIZATION.md

## Warlogger — 3D Globe Map Layer Strategy

Dieses Dokument beschreibt den empfohlenen Stack für Tile-Layer, LOD-Steuerung und Styling auf dem bestehenden Three.js-Globus.

---

## Rendering-Grundlage

### Deck.gl mit GlobeView

Der bestehende Three.js-Globus wird um **Deck.gl** erweitert. Deck.gl übernimmt das gesamte Layer-Management — Tile-Loading, Culling (nur sichtbare Tiles werden geladen), GPU-Rendering und zoom-basiertes LOD. Der Three.js-Canvas bleibt für 3D-Szene und Atmosphäre bestehen; Deck.gl rendert als übergelagerter WebGL-Kontext.

**Verantwortlichkeiten:**
- Three.js → Globus-Geometrie, Atmosphäre, Beleuchtung, Kamera
- Deck.gl → Tile-Layer, GeoJSON-Overlays, Labels, Heatmaps

---

## Tile-Datenquelle

### Protomaps (Self-hosted)

Die gesamte Weltkarte wird als einzelne `.pmtiles`-Datei von Protomaps bezogen. Diese Datei enthält Vector Tiles aller Zoomstufen in einem kompakten, HTTP-Range-Request-fähigen Format.

**Hosting:** Cloudflare R2 Object Storage  
**Zugriff:** Direkte Range-Requests auf die `.pmtiles`-Datei ohne eigenen Tile-Server  
**Vorteile gegenüber Mapbox/OSM:**
- Keine API-Keys, kein externer Dienst
- DSGVO-konform (kein Daten-Tracking durch Drittanbieter)
- Einmaliger Download (~9 GB), danach keine laufenden Tile-Kosten
- Cloudflare R2 hat keinen Egress-Traffic-Preis

**Tile-Inhalt (OSM-basiert):**
- Zoomstufe 0–4: Kontinente, Ländergrenzen, Ozeane
- Zoomstufe 5–8: Regionen, Hauptstädte, Flüsse, Seen
- Zoomstufe 9–12: Städte, Straßennetz, Ortschaften
- Zoomstufe 13–14: Einzelne Straßen, Gebäudeumrisse, POIs

---

## Layer-Architektur

### 1. Basemap-Layer (TileLayer)

Lädt und rendert die Vector Tiles aus der `.pmtiles`-Datei. Deck.gl steuert automatisch, welche Tiles bei welchem Zoom geladen werden. Nur im aktuellen Viewport sichtbare Tiles werden angefragt.

### 2. Terrain-Layer (BitmapLayer / Höhenrelief)

Optional: Mapbox Terrain-RGB Tiles oder NASA SRTM-Daten als Displacement Map. Bei niedrigen Zoomstufen deaktiviert, ab Zoom 5+ als subtile Schattierung über Landmassen gelegt. Dient rein visueller Tiefenwirkung, kein echtes 3D-Terrain.

### 3. GeoJSON-Konfliktdaten (GeoJsonLayer)

Eigene Daten aus der Warlogger-Datenbank als GeoJSON-Overlays. Konfliktzonen als Polygone, Ereignisse als Punkte, Frontlinien als Linien. Werden unabhängig vom Tile-System gerendert und per API dynamisch nachgeladen.

### 4. Heatmap-Layer (HeatmapLayer)

Aggregierte Konflikthäufigkeit als Wärmeverteilung. Bei niedrigen Zoomstufen (Globus-Ansicht) aktiv, bei hohem Zoom zugunsten konkreter Marker deaktiviert.

### 5. Label-Layer (TextLayer)

Städtenamen und Ländernamen als Deck.gl TextLayer. Vollständig zoom-gesteuert:

| Zoomstufe | Sichtbare Labels |
|-----------|-----------------|
| 0–2 | Keine |
| 3–4 | Kontinentnamen |
| 5–6 | Hauptstädte (Population > 1M) |
| 7–9 | Großstädte (Population > 100k) |
| 10+ | Alle Ortschaften |

Labels sind GPU-gerenderte Sprites, keine DOM-Elemente — kein Performance-Einbruch bei vielen Punkten.

### 6. Graticule-Layer (LineLayer)

Optionale Breitengrad-/Längengradlinien im militärischen Gitterstil. Feste Deckkraft, unabhängig vom Zoom.

---

## LOD-Steuerung (Level of Detail)

Deck.gl steuert LOD automatisch über den `zoom`-Parameter der Kamera:

- **TileLayer** lädt nur Tiles der jeweils passenden Zoomstufe (z.B. Zoom 3 → Tile-Level 3)
- **Tile-Caching** hält zuletzt geladene Tiles im Speicher, sodass schnelles Zoomen keine visuellen Lücken erzeugt
- **Layer-Visibility** pro Layer individuell über zoom-abhängige `visible`-Kondition steuerbar
- **Datendichte** in den Vector Tiles ist bereits nach Zoomstufe vorgefiltert — ein Zoom-3-Tile enthält keine Straßendaten, ein Zoom-13-Tile enthält keine Kontinentgrenzen

---

## Styling

### Grundprinzip

Das Styling der Basemap erfolgt über die **Mapbox Style Specification** — ein offener JSON-Standard, der auch von Protomaps und MapLibre GL unterstützt wird. Damit ist das Styling unabhängig vom Tile-Anbieter.

### Dark Military Theme

**Farbschema:**

| Element | Farbe | Beschreibung |
|---------|-------|-------------|
| Ozean / Hintergrund | `#050810` | Fast-Schwarz mit leichtem Blauton |
| Landmasse | `#0d1117` | Sehr dunkles Grau |
| Nationale Grenzen | `#1e3a5f` | Gedämpftes Dunkelblau |
| Staatsgrenzen (Streit) | `#8b1a1a` | Dunkelrot |
| Straßennetz | `#1a2535` | Kaum sichtbar bei niedrigem Zoom |
| Gewässer (Flüsse, Seen) | `#0a1628` | Dunkel, leicht von Land abgesetzt |
| Stadtlabels | `#8fa8c0` | Gedämpftes Stahlblau |
| Hauptstadtlabels | `#c4d4e0` | Etwas heller |
| Konfliktzonen (aktiv) | `#cc2200` mit Alpha | Rot, halbtransparent |
| Konfliktzonen (latent) | `#cc7700` mit Alpha | Orange, halbtransparent |
| Heatmap | Rot-Orange-Gradient | Auf dunklem Untergrund |

### Protomaps Theme-Basis

Protomaps liefert ein `black`-Theme als Ausgangspunkt. Dieses wird als Basis-JSON geladen und projektspezifisch überschrieben — nur die abweichenden Layer-Styles müssen definiert werden.

### Atmosphäre (Three.js)

Die Erdatmosphäre (Glüheffekt am Globusrand) bleibt in Three.js als separater Pass:
- Dunkelblaues Glühen bei normalem Zoom
- Wechsel zu Orange/Rot bei starker Aktivität (optional, datengesteuert)

---

## Performance-Strategie

### Tile-Requests minimieren
- Viewport-Culling durch Deck.gl (keine Tiles außerhalb des sichtbaren Bereichs)
- Tile-Cache auf Client-Seite (Deck.gl intern, konfigurierbares Cache-Limit)
- Range-Requests auf `.pmtiles` laden nur den benötigten Tile-Bereich aus der Datei

### Rendering
- Alle Layer laufen als WebGL, kein DOM-Overhead
- GeoJSON-Konfliktdaten werden bei Zoom-Änderung nicht neu geladen, nur re-rendered
- Heatmap und TileLayer bei gleichzeitiger Sichtbarkeit via `opacity`-Blending kombiniert

### Netzwerk
- `.pmtiles` im storage folder
- Konfliktdaten-API (Warlogger Backend) liefert nur Daten im aktuellen Bounding-Box-Bereich

---

## Abhängigkeiten

| Paket | Zweck |
|-------|-------|
| `deck.gl` | Layer-Rendering, GlobeView, LOD |
| `@deck.gl/geo-layers` | TileLayer, MVTLayer |
| `pmtiles` | Client-seitige `.pmtiles`-Dekodierung |
| `protomaps-themes-base` | Basis-Styles für Protomaps-Tiles |
| `maplibre-gl` | Optional: Style-Rendering für komplexe Basemap-Styles |

Three.js bleibt bestehend, keine breaking changes am Globus-Setup.

---

## Offene Entscheidungen

- **Terrain-Relief:** NASA SRTM (kostenlos, niedriger Auflösung) vs. Mapbox Terrain (bessere Qualität, kostenloser Tier ausreichend) — abhängig davon, ob Höhenrelief in der ersten Version gewünscht ist
- **MapLibre Integration:** Nur nötig wenn komplexes Style-JSON mit dynamischen Expressions benötigt wird; für den Warlogger-Use-Case ist direktes Deck.gl-Styling ausreichend
- **Offline-Modus:** `.pmtiles`-Datei könnte via Service Worker gecacht werden für Offline-Nutzung (relevant falls Journalisten in schlechter Konnektivität)
