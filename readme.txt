=== Icon Library ===
Contributors: svgforge
Tags: svg, icons, sprite, gutenberg
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SVG Fragment Block: fügt Icons aus einer eigenen SVG-Sprite-Datei (ico.svg) per <use> ein und verlinkt sie. Für Anwender, die die Sprite-Datei mit CLI-Tools wie svgforge selbst pflegen und volle Kontrolle über das gerenderte SVG wollen.

== Description ==

Der Icon Library Block lädt eine zentrale SVG-Sprite-Datei (im Standard das gebündelte `sprite.svg`), stellt alle darin enthaltenen `symbol`-Elemente in einem komfortablen Picker dar und fügt das gewählte Icon als `<svg><use href="/wp-content/plugins/icon-library/sprite.svg#symbol-id">` in deine Inhalte ein.

= Für wen ist dieses Plugin? =

Dieses Plugin richtet sich in erster Linie an fortgeschrittene Theme- und Plugin-Entwickler. Es verschafft dir keinen codefreien Icon-Manager: Jedes Icon muss zuerst als `<symbol>` in einer Sprite-Datei existieren, die du besitzt, baust und versionierst.

Seit WordPress 7.1 bringt Core ein eigenes Icon-System mit (`wp_register_icon_collection()`, `wp_register_icon()`, `wp_get_icon()`): Icons erscheinen damit automatisch im Picker des nativen Icon-Blocks und in der REST-API und lassen sich in PHP direkt ausgeben. Wenn du nur wenige Icons brauchst und sie per Code registrieren kannst, nutze besser Core — dieses Plugin ist dann unnötig.

Der Fragment-Ansatz hat Vorteile, wenn du eine echte Sprite-Pipeline betreibst:

* Volles SVG über `<use>`: Stroke-Icons, Farbverläufe, `currentColor`, Inline-Styles und individuelle `viewBox`-Werte bleiben erhalten. Der Sanitizer von WordPress 7.1 erlaubt nur `<svg>`, `<path>` und `<polygon>` (ohne `stroke` und Inline-Styles) und zerstört dadurch viele stroke-basierte Icon-Sets.
* Bestehende Sprites wiederverwenden: Sprite hochladen (oder mit einem CLI-Tool wie svgforge erzeugen) — kein PHP-Code pro Icon nötig.
* Eine Datei: Die Sprite ist eine einzelne, cachebare Datei, die im Theme-Repo liegt und per Git versioniert wird.
* Kontrolle pro Block: Füll- und Linienfarbe, Breite/Höhe mit Einheiten, Links samt `rel`-Handling und Aria-Labels — pro Icon-Instanz, ohne Stylesheet.
* Läuft auch auf WordPress vor 7.1 (ab 6.6).

Nachteile, die du kennen solltest:

* Zum Hinzufügen oder Ändern von Icons musst du die Sprite-Datei neu erzeugen — typischerweise mit einem CLI-Tool wie svgforge. Einen Icon-Editor im Browser oder eine Verwaltungsoberfläche gibt es nicht.
* Die Icons liegen als Block im Inhalt. Für ein simples `wp_get_icon()`-Helferchen in der Theme-PHP gibt es keinen Ersatz — dafür ist der native 7.1-Ansatz gedacht.

= Eigenschaften =

* Einstellungsseite (Einstellungen → Icon Library) zum Hochladen der SVG-Sprite-Datei inkl. Bereinigung von Skripten und Event-Handlern.
* Theme-Datei über `ICON_LIBRARY_SPRITE_FILE` als versionierbare Quelle – hat Vorrang, solange die Datei existiert; sonst greift der Backend-Upload.
* Symbol-Picker im Editor mit Live-Vorschau aller Icons aus der Sprite.
* Icons verlinkbar (neuer Tab + rel-Attribute inkl. noopener/noreferrer).
* Aria-Label für Screenreader, verlinkte Icons automatisch per Link beschriftet.
* Füll- und Linienfarbe als auch Breite/Höhe pro Block konfigurierbar (px, em, rem, %).
* Vollständig dynamisch gerendert (render.php) mit `get_block_wrapper_attributes()`.
* Block-Supports: Ausrichtung, Anker, zusätzliche CSS-Klassen.

= Sprite-Datei konfigurieren =

Die SVG-Sprite-Datei wird in dieser Reihenfolge aufgelöst (erste vorhandene Quelle gewinnt):

1. Theme-Datei `ICON_LIBRARY_SPRITE_FILE` – wenn gesetzt und die Datei existiert (hat Vorrang).
2. Hochgeladene Datei aus Einstellungen → Icon Library (Backend-Upload).
3. Konstante `ICON_LIBRARY_SPRITE_URL`.
4. Filter `icon_library_sprite_url`.
5. Fallback: `sprite.svg` im Plugin-Verzeichnis.

Theme-Datei (empfohlen, versionierbar mit dem Theme) in der `functions.php`:

    define( 'ICON_LIBRARY_SPRITE_FILE', get_stylesheet_directory() . '/assets/ico.svg' );

Die Datei muss lesbar sein und innerhalb von `WP_CONTENT_DIR` oder `ABSPATH` liegen. Ist sie nicht vorhanden, greift automatisch die nächste Quelle.

Weitere Overrides:

    define( 'ICON_LIBRARY_SPRITE_URL', 'https://cdn.example.com/icons/ico.svg' );

oders

    add_filter( 'icon_library_sprite_url', function () {
        return '/wp-content/themes/mein-theme/assets/ico.svg';
    } );

== Installation ==

1. Lade den Plugin-Ordner in `/wp-content/plugins/` hoch (oder installiere die ZIP über Plugins → Installieren).
2. Aktiviere das Plugin unter „Plugins“.
3. Lade die Sprite-Datei (`ico.svg` mit `<symbol id="...">`-Elementen) über **Einstellungen → Icon Library** hoch oder lege sie als Theme-Datei (`ICON_LIBRARY_SPRITE_FILE`) bzw. an der konfigurierten URL ab.
4. Füge im Editor den Block „SVG Fragment“ hinzu und wähle ein Icon.

== Frequently Asked Questions ==

= Woher kommen die Icons? =

Aus der zentralen Sprite-Datei `ico.svg`. Jedes Icon ist ein `<symbol id="mein-icon" viewBox="0 0 24 24">…</symbol>-Element. Die Datei wird serverseitig gerendert und im Editor per `fetch` geladen.

= Warum nicht einfach die nativen SVG-Icons von WordPress 7.1 nutzen? =

WordPress 7.1 bietet mit `wp_register_icon_collection()` / `wp_register_icon()` / `wp_get_icon()` ein natives Icon-System — das reicht, wenn du wenige Icons direkt in Code registrierst. Dieses Plugin ergänzt das dort, wo eine zentrale SVG-Sprite zum Einsatz kommt: volle SVG-Freiheit (auch Stroke-Icons), bestehende Sprites ohne PHP-Code pro Icon, eine cachebare Datei und Block-Styling pro Instanz. Eine Integration in den nativen 7.1-Ansatz ist geplant, sodass dieselbe Sprite künftig auch den nativen Icon-Block speisen kann (siehe Changelog).

= Funktioniert das auch ohne JS im Frontend? =

Ja. Das Frontend-Markup wird serverseitig in `render.php` erzeugt; die Built-JS wird nur im Editor gebraucht.

== Screenshots ==

1. Symbol-Picker im Gutenberg-Editor mit Live-Vorschau aller Icons aus der Sprite-Datei.

== Changelog ==

= 0.1.0 =
* Initiale Veröffentlichung.