"""
Build tools/feed-import/var/feeds/upc-image-map.csv: product reference -> photo URL.

Why this exists: the 2022 catalogue that is loaded names its images only by
filename, and the host that served them (secretpleasurez.com/imgs) is gone.
The newer 2023 feed has live CloudFront photo URLs, keyed by UPC barcode. The
2022 feed also carries UPCs, so joining the two on UPC gives the loaded
products real photos without replacing the catalogue. Run 2026-09-13: 13,998
of 16,663 products matched.

Consumed by config/images-by-upc.json via import-images.php --from-url.

Run on the host (the container has no Python):
    python tools/dev/build-upc-image-map.py
"""

import csv
import io
import re
import sys
from pathlib import Path

FEEDS = Path(__file__).resolve().parents[1] / "feed-import" / "var" / "feeds"
OLD_FEED = FEEDS / "supplier-feed.xml"      # 2022: PRODUCTS_MODEL is our product reference
NEW_FEED = FEEDS / "wholesale-2023.csv"     # 2023: upc -> image_url
OUT = FEEDS / "upc-image-map.csv"


def normalise_upc(value):
    """Compare barcodes as digits only, without leading zeros.

    The two feeds disagree on formatting: 2023 pads to 12+ digits
    ("000000000609"), 2022 often does not.
    """
    return re.sub(r"\D", "", value or "").lstrip("0")


def read_old_feed():
    """reference -> normalised UPC, streamed so the 28 MB file never loads whole."""
    refs = {}
    record = {}
    with io.open(OLD_FEED, encoding="utf-8", errors="ignore") as f:
        for line in f:
            m = re.search(r"<(PRODUCTS_MODEL|ITEM_UPC)>(.*?)</", line)
            if m:
                record[m.group(1)] = m.group(2).strip()
            if "</PRODUCT>" in line:
                ref = record.get("PRODUCTS_MODEL")
                upc = normalise_upc(record.get("ITEM_UPC"))
                if ref and upc:
                    refs[ref] = upc
                record = {}
    return refs


def read_new_feed():
    """normalised UPC -> first usable absolute image URL."""
    images = {}
    with io.open(NEW_FEED, encoding="utf-8", errors="ignore", newline="") as f:
        for row in csv.DictReader(f):
            upc = normalise_upc(row.get("upc"))
            url = (row.get("image_url") or "").strip()
            if upc and url.startswith("http"):
                images.setdefault(upc, url)
    return images


def main():
    for path in (OLD_FEED, NEW_FEED):
        if not path.is_file():
            sys.exit(f"missing feed: {path}")

    refs = read_old_feed()
    images = read_new_feed()
    matched = [(ref, images[upc]) for ref, upc in refs.items() if upc in images]

    with io.open(OUT, "w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["reference", "image_url"])
        writer.writerows(matched)

    print(f"2022 products with a UPC: {len(refs)}")
    print(f"2023 UPCs with a photo:   {len(images)}")
    print(f"matched, written to {OUT.name}: {len(matched)}")


if __name__ == "__main__":
    main()
