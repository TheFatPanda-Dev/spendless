# SpendLess identity kit

Open **Preview.html** in a browser to compare all eight screen references. Use the screen and appearance selectors to switch between the web overview, phone overview, activity and transaction entry. The guide is in **guide/SpendLess-Brand-Guide.pdf**.

## Logo masters

The supplied reference is archived in **identity/original-reference.png**. The lettering is traced from that raster and the coin is reconstructed as scalable geometry. Use the original supplied PNG when an exact pixel match to the original artwork is required. Use the SVG masters for new layouts. The supplied tagline reads **Money made simple**. The light-surface extension uses deep green for “Less” to keep the wordmark legible. The single-colour symbol uses an outlined coin.

**on-dark** and **on-light** describe the background on which a transparent file belongs. **mono-light** and **mono-dark** describe the ink. Files ending in **-tagline** include the descriptor. PNG lockups are supplied at 4× their SVG canvas dimensions. Core sRGB swatches are available in Adobe Swatch Exchange format.

## Web and phone

- **screens/** contains editable SVGs at 1440 × 960 and 390 × 844 logical units. PNGs preserve the intended typography on devices without the fonts installed.
- **icons/app/** contains a square opaque app master, 1024, 512, 192 and 180 px icons, a safe-zone maskable icon, favicons and a web manifest. Use the 180 px icon as the Apple touch icon. Place the manifest with its relative icon files.
- **icons/ui/** contains individual 24 × 24 SVGs with currentColor strokes. Keep a 44 × 44 logical-unit target around interactive icons.
- **design-tokens/** contains the canonical JSON, a web stylesheet and a TypeScript export. The CSS follows the system theme. Set data-theme="light" or data-theme="dark" on the root element to override it. For native applications, select spendLess.themes.light or spendLess.themes.dark and register the font files under the names expected by the app.
- **typefaces/** contains the Latin font files for Roboto Light, Regular and Medium with their licence. Use system sans-serif fallbacks for missing glyphs and load additional script subsets as your localisation requires.
- **communications/** contains editable and exported social assets, the identity overview and the editorial paper-curl image. All SVG artwork is self-contained. Individual screen and guide files keep their UI text editable.
- **guide/pages/** contains editable SVG layouts for every page. The PDF includes section bookmarks.

## Sample data

The overview represents a fictional November 2026 monthly budget of €2,000.00 and €1,265.40 in expense transactions. The five category totals sum to expenses. Remaining budget is €734.60. The ring uses the unrounded proportion and displays 63%. Recent activity is a selection of four entries. Income appears in activity and is excluded from category spending.

The examples use English copy and euros. Use locale-aware money and date formatters in production. Input amounts, signs and totals are data in the live application.
