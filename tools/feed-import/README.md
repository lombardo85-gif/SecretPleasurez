# Supplier feed importer

Syncs a wholesaler CSV/XML feed into the PrestaShop catalog: creates new
products, keeps prices and stock current, and retires products the supplier
has dropped.

## Running it

```bash
# always dry-run a new feed first — it writes nothing
docker exec -w /var/www/html/themes/PRS935/tools/feed-import spz-shop \
  php import.php --config=config/acme.json --dry-run

# first 50 rows only, to check a new supplier's column mapping
docker exec -w /var/www/html/themes/PRS935/tools/feed-import spz-shop \
  php import.php --config=config/acme.json --dry-run --limit=50

# for real
docker exec -w /var/www/html/themes/PRS935/tools/feed-import spz-shop \
  php import.php --config=config/acme.json
```

| Option | Effect |
| --- | --- |
| `--config=PATH` | Job config (required). Relative paths resolve against this directory. |
| `--dry-run` | Report every change, write nothing. |
| `--limit=N` | Stop after N rows. Also disables the discontinued sweep, since a partial feed is not evidence a product is gone. |
| `--quiet` | Suppress INFO on stdout; warnings and errors still print. |
| `--no-disable` | Skip the discontinued sweep for this run. |

Exit codes: `0` clean, `1` some rows failed, `2` fatal (bad config, unreadable
or empty feed).

## Configuring a supplier

Copy `config/example.json`, then set `mapping` to your feed's **exact**
column headers (case-sensitive). For XML, set `feed.item_node` to the
repeating element; nested values are addressed as `stock.quantity` and
attributes as `item.@sku`.

### Pricing

Tiers are evaluated in order and the first match wins; `up_to` is inclusive
and `null` means "and above".

```json
"tiers": [
  { "up_to": 10.0,  "markup_percent": 140.0 },
  { "up_to": null,  "markup_percent": 65.0 }
]
```

Order of application: tier markup → `handling_fee` → `min_margin_amount`
floor → rounding. **MAP overrides all of it** — if the feed supplies a
minimum advertised price, the product never lists below it, including after
rounding.

`rounding: "charm"` rounds *up* to the next `.99`, so a computed 25.01
becomes 25.99. That is deliberate — it can only ever protect margin, never
erode it. Use `"none"` for exact cent pricing.

## Safety behaviour

These exist because a bad supplier feed can otherwise destroy a catalog:

- **Products are disabled, never deleted.** A supplier omitting a line is
  routine; deleting the product would take its URL, reviews and order history
  references with it.
- **The discontinued sweep aborts if a feed omits more than
  `max_disable_ratio` of the catalog** (default 25%). A feed that suddenly
  drops most of your products is nearly always truncated, not a mass
  discontinuation.
- **An empty feed aborts the run** before the sweep, rather than reading zero
  rows as "everything is discontinued".
- **`sku_prefix` scopes the sweep to one supplier**, so importing supplier B
  never disables supplier A's products.
- **Idempotent.** Fields are compared before assignment, so an unchanged feed
  performs no writes and does not bump `date_upd`.

## Images

Images are attached separately, after the catalog exists. Image work is slow
and IO-bound, and a failed thumbnail should never hold up a price sync.

```bash
docker exec -w /var/www/html/themes/PRS935/tools/feed-import spz-shop \
  php import-images.php --config=config/supplier.json \
                        --images-dir=/mnt/wp-uploads --dry-run
```

Files are matched on the feed's own image filename. WordPress size variants
(`name-300x300.jpg`) are ignored so the full-size original is always used, and
every registered PrestaShop image type gets a derivative generated — skipping
those leaves broken thumbnails across the storefront. Products that already
have an image are skipped unless you pass `--overwrite`.

## Scheduling

On this Windows host, schedule `schedule/run-sync.ps1` with Task Scheduler
rather than calling `docker exec` directly. The wrapper starts Docker and the
stack if the machine has rebooted since the last run, and returns the
importer's own exit code so Task Scheduler shows a real result instead of
always-success. Register it with `Register-ScheduledTask`, passing
`-File <repo>\tools\feed-import\schedule\run-sync.ps1 -Config config/supplier.json`.

A sensible cadence is stock and prices every 4 hours; the discontinued sweep
only needs to run once a day, so give that its own task with a config where
`disable_missing` is true and leave it false on the frequent one.

Inside the container the equivalent is plain cron:

```cron
# prices and stock every 4 hours
0 */4 * * * cd /var/www/html/themes/PRS935/tools/feed-import && php import.php --config=config/supplier.json --quiet
```

Logs land in `var/log/import-<timestamp>.log` (gitignored). Errors always go
to stderr so cron mail is never silently empty on failure, and the final
summary line always prints even under `--quiet` — a scheduled run that reports
nothing is indistinguishable from one that never ran.

## Known constraints

- Images are not imported yet; products are created without them.
- Product variants (size/colour) are not handled — each feed row becomes one
  simple product.
- `id_tax_rules_group: 0` means no tax is applied. Set this to a real tax
  group before taking orders.
