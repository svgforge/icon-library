=== WP Iconizer ===
Contributors: svgforge
Tags: svg, icons, sprite, gutenberg
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SVG Icon Block: Fügt Icons aus einer SVG-Sprite-Datei (ico.svg) per <use> ein und kann sie verlinken. Die Sprite-Datei lässt sich direkt als SVG-Fragment-Library hochladen.

== Description ==

Der WP Iconizer Block lädt eine zentrale SVG-Sprite-Datei (`ico.svg`), stellt alle darin enthaltenen `symbol`-Elemente in einem komfortablen Picker dar und fügt das gewählte Icon als `<svg><use href="/ico.svg#symbol-id">` in deine Inhalte ein.

= Eigenschaften =

* Einstellungsseite (Einstellungen → WP Iconizer) zum Hochladen der SVG-Sprite-Datei inkl. Bereinigung von Skripten und Event-Handlern.
* Theme-Datei über `WP_ICONIZER_SPRITE_FILE` als versionierbare Quelle – hat Vorrang, solange die Datei existiert; sonst greift der Backend-Upload.
* Symbol-Picker im Editor mit Live-Vorschau aller Icons aus der Sprite.
* Icons verlinkbar (neuer Tab + rel-Attribute inkl. noopener/noreferrer).
* Aria-Label für Screenreader, verlinkte Icons automatisch per Link beschriftet.
* Füll- und Linienfarbe als auch Breite/Höhe pro Block konfigurierbar (px, em, rem, %).
* Vollständig dynamisch gerendert (render.php) mit `get_block_wrapper_attributes()`.
* Block-Supports: Ausrichtung, Anker, zusätzliche CSS-Klassen.

= Sprite-Datei konfigurieren =

Die SVG-Sprite-Datei wird in dieser Reihenfolge aufgelöst (erste vorhandene Quelle gewinnt):

1. Theme-Datei `WP_ICONIZER_SPRITE_FILE` – wenn gesetzt und die Datei existiert (hat Vorrang).
2. Hochgeladene Datei aus Einstellungen → WP Iconizer (Backend-Upload).
3. Konstante `WP_ICONIZER_SPRITE_URL`.
4. Filter `wp_iconizer_sprite_url`.
5. Fallback `/ico.svg` im Webserver-Root.

Theme-Datei (empfohlen, versionierbar mit dem Theme) in der `functions.php`:

    define( 'WP_ICONIZER_SPRITE_FILE', get_stylesheet_directory() . '/assets/ico.svg' );

Die Datei muss lesbar sein und innerhalb von `WP_CONTENT_DIR` oder `ABSPATH` liegen. Ist sie nicht vorhanden, greift automatisch die nächste Quelle.

Weitere Overrides:

    define( 'WP_ICONIZER_SPRITE_URL', 'https://cdn.example.com/icons/ico.svg' );

oders

    add_filter( 'wp_iconizer_sprite_url', function () {
        return '/wp-content/themes/mein-theme/assets/ico.svg';
    } );

== Installation ==

1. Lade den Plugin-Ordner in `/wp-content/plugins/` hoch (oder installiere die ZIP über Plugins → Installieren).
2. Aktiviere das Plugin unter „Plugins“.
3. Lade die Sprite-Datei (`ico.svg` mit `<symbol id="...">`-Elementen) über **Einstellungen → WP Iconizer** hoch oder lege sie als Theme-Datei (`WP_ICONIZER_SPRITE_FILE`) bzw. an der konfigurierten URL ab.
4. Füge im Editor den Block „SVG Fragment“ hinzu und wähle ein Icon.

== Frequently Asked Questions ==

= Woher kommen die Icons? =

Aus der zentralen Sprite-Datei `ico.svg`. Jedes Icon ist ein `<symbol id="mein-icon" viewBox="0 0 24 24">…</symbol>-Element. Die Datei wird serverseitig gerendert und im Editor per `fetch` geladen.

= Funktioniert das auch ohne JS im Frontend? =

Ja. Das Frontend-Markup wird serverseitig in `render.php` erzeugt; die Built-JS wird nur im Editor gebraucht.

== Changelog ==

= 0.1.0 =
* Initiale Veröffentlichung.