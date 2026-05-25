# -*- coding: utf-8 -*-
"""Patch category + trending PHP listing cards with preview dataset attrs."""
from pathlib import Path

ROOT = Path(r"c:\Users\Joel\Desktop\JoMu Website\php")

INSERT_AFTER_HELPERS = (
    "require __DIR__ . '/partials/helpers.php';\n\nheader('Content-Type: text/html; charset=UTF-8');",
    "require __DIR__ . '/partials/helpers.php';\nrequire_once __DIR__ . '/partials/listing_preview_dataset.php';\n\nheader('Content-Type: text/html; charset=UTF-8');",
)

TRENDING_INSERT = (
    "require __DIR__ . '/partials/helpers.php';\nrequire __DIR__ . '/partials/admin_helpers.php';",
    "require __DIR__ . '/partials/helpers.php';\nrequire_once __DIR__ . '/partials/listing_preview_dataset.php';\nrequire __DIR__ . '/partials/admin_helpers.php';",
)

PV_BLOCK = (
    "    if ($title === '') {\n        $title = 'Listing';\n    }\n\n    $priceMarkup = '';",
    "    $pv = jomu_listing_preview_dataset_attrs($listing);\n\n    if ($title === '') {\n        $title = 'Listing';\n    }\n\n    $priceMarkup = '';",
)

OLD_IMG = """' . h($priceLabel) . '" data-preview-listing-id="' . (int) ($listing['listing_id'] ?? 0) . '">"""
NEW_IMG = """' . h($priceLabel) . '" data-preview-listing-id="' . (int) ($listing['listing_id'] ?? 0) . '" data-preview-business="' . h($pv['business']) . '" data-preview-posted="' . h($pv['posted']) . '">"""


def patch_file(path: Path) -> bool:
    t = path.read_text(encoding="utf-8")
    if "listing_preview_dataset.php" in t:
        print("skip (already)", path.name)
        return False
    if path.name == "trendingseasonaltrends_results.php":
        if TRENDING_INSERT[0] not in t:
            print("no trending pattern", path.name)
            return False
        t = t.replace(TRENDING_INSERT[0], TRENDING_INSERT[1], 1)
    else:
        if INSERT_AFTER_HELPERS[0] not in t:
            print("no helpers+header", path.name)
            return False
        t = t.replace(INSERT_AFTER_HELPERS[0], INSERT_AFTER_HELPERS[1], 1)
    if PV_BLOCK[0] not in t:
        print("no pv block", path.name)
        return False
    t = t.replace(PV_BLOCK[0], PV_BLOCK[1], 1)
    if OLD_IMG not in t:
        print("no img tail", path.name)
        return False
    t = t.replace(OLD_IMG, NEW_IMG, 1)
    path.write_text(t, encoding="utf-8")
    print("patched", path.name)
    return True


def main() -> None:
    files = sorted(ROOT.glob("categories*_results.php"))
    files.append(ROOT / "trendingseasonaltrends_results.php")
    for p in files:
        if p.exists():
            patch_file(p)


if __name__ == "__main__":
    main()
