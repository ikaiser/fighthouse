# /// script
# requires-python = ">=3.11"
# dependencies = ["httpx>=0.27"]
# ///
"""Crea su FightHouse (WooCommerce REST v3) i prodotti letti da `scrape.py`, come bozze.

Si può rilanciare: un prodotto si riconosce dallo SKU «CA-<id Shopify>» e non si ricrea; di un
prodotto già presente si creano solo le varianti che mancano (import interrotto a metà).
Categorie, marche e termini di colore/taglia mancanti si creano.

Chiavi REST (WooCommerce → Impostazioni → Avanzate → REST API, lettura/scrittura) in
`tools/combatarena-import/.env` (fuori da git), oppure nell'ambiente:
    WC_KEY=ck_...
    WC_SECRET=cs_...
Uso:
    uv run tools/combatarena-import/import_wc.py --dry-run          # nessuna scrittura
    uv run tools/combatarena-import/import_wc.py --limit 3          # prova su 3 prodotti
    uv run tools/combatarena-import/import_wc.py                    # tutto
"""

import argparse
import json
import os
import sys
from pathlib import Path

import httpx

from transform import product_plan

DEFAULT_SITE = "https://fighthouse.it"
HERE = Path(__file__).parent
BATCH = 50


class Woo:
    def __init__(self, site: str, key: str, secret: str) -> None:
        # Le immagini le scarica WordPress mentre crea il prodotto: può volerci un po'.
        self._client = httpx.Client(
            base_url=f"{site.rstrip('/')}/wp-json/wc/v3/",
            auth=(key, secret),
            timeout=300,
            headers={"User-Agent": "fighthouse-combatarena-import"},
        )

    def _check(self, response: httpx.Response) -> object:
        if response.status_code >= 400:
            raise RuntimeError(f"{response.request.method} {response.request.url}: {response.status_code} {response.text[:500]}")
        return response.json()

    def get(self, path: str, **params: str | int) -> object:
        return self._check(self._client.get(path, params=params))

    def post(self, path: str, body: dict) -> dict:
        return self._check(self._client.post(path, json=body))  # type: ignore[return-value]

    def all(self, path: str, **params: str | int) -> list[dict]:
        out: list[dict] = []
        page = 1
        while True:
            batch = self.get(path, per_page=100, page=page, **params)
            out.extend(batch)  # type: ignore[arg-type]
            if len(batch) < 100:  # type: ignore[arg-type]
                return out
            page += 1


class Lookups:
    """Nomi → ID su FightHouse, creando quel che manca."""

    def __init__(self, woo: Woo, dry_run: bool) -> None:
        self.woo = woo
        self.dry_run = dry_run
        self.created: list[str] = []
        self._fake = 0
        self.attributes = {a["slug"].removeprefix("pa_"): a["id"] for a in woo.all("products/attributes")}
        for slug in ("colore", "taglie"):
            if slug not in self.attributes:
                raise RuntimeError(f"Attributo globale pa_{slug} non trovato su FightHouse")
        self._terms = {
            slug: {t["name"].lower(): t["name"] for t in woo.all(f"products/attributes/{aid}/terms")}
            for slug, aid in self.attributes.items()
            if slug in ("colore", "taglie")
        }
        self._categories = {(c["parent"], c["name"].lower()): c["id"] for c in woo.all("products/categories")}
        self._brands = {b["name"].lower(): b["id"] for b in woo.all("products/brands")}

    def _create(self, path: str, body: dict, label: str) -> int:
        self.created.append(label)
        if self.dry_run:
            self._fake -= 1
            return self._fake
        return self.woo.post(path, body)["id"]

    def term(self, slug: str, name: str) -> str:
        """Il nome del termine come è su FightHouse (stessa parola, maiuscole a parte)."""
        existing = self._terms[slug].get(name.lower())
        if existing is None:
            self._create(f"products/attributes/{self.attributes[slug]}/terms", {"name": name}, f"termine {slug}: {name}")
            existing = self._terms[slug][name.lower()] = name
        return existing

    def category(self, parent_name: str, child_name: str) -> int:
        parent = self._categories.get((0, parent_name.lower()))
        if parent is None:
            parent = self._create("products/categories", {"name": parent_name}, f"categoria {parent_name}")
            self._categories[(0, parent_name.lower())] = parent
        key = (parent, child_name.lower())
        if key not in self._categories:
            self._categories[key] = self._create(
                "products/categories", {"name": child_name, "parent": parent}, f"categoria {parent_name} › {child_name}"
            )
        return self._categories[key]

    def brand(self, name: str) -> int:
        if name.lower() not in self._brands:
            self._brands[name.lower()] = self._create("products/brands", {"name": name}, f"marca {name}")
        return self._brands[name.lower()]


def product_body(plan: dict, lookups: Lookups) -> dict:
    body: dict = {
        "name": plan["name"],
        "type": plan["type"],
        "status": "draft",
        "sku": plan["sku"],
        "description": plan["description"],
        "categories": [{"id": lookups.category(*path)} for path in plan["categories"]],
        "brands": [{"id": lookups.brand(plan["brand"])}],
        "images": plan["images"],
        "meta_data": [
            {"key": "_combatarena_id", "value": str(plan["source_id"])},
            {"key": "_combatarena_handle", "value": plan["source_handle"]},
        ],
    }
    if plan["type"] == "simple":
        body.update(
            regular_price=plan["regular_price"], sale_price=plan["sale_price"], stock_status=plan["stock_status"]
        )
    else:
        body["attributes"] = [
            {
                "id": lookups.attributes[slug],
                "visible": True,
                "variation": True,
                "options": [lookups.term(slug, value) for value in values],
            }
            for slug, values in plan["attributes"].items()
        ]
    return body


def variation_bodies(plan: dict, lookups: Lookups, image_ids: list[int]) -> list[dict]:
    bodies = []
    for variation in plan["variations"]:
        body = {
            "sku": variation["sku"],
            "regular_price": variation["regular_price"],
            "sale_price": variation["sale_price"],
            "stock_status": variation["stock_status"],
            "attributes": [
                {"id": lookups.attributes[slug], "option": lookups.term(slug, value)}
                for slug, value in variation["attributes"].items()
            ],
        }
        index = variation["image_index"]
        if index is not None and index < len(image_ids):
            body["image"] = {"id": image_ids[index]}
        bodies.append(body)
    return bodies


def import_product(plan: dict, woo: Woo, lookups: Lookups, dry_run: bool) -> str:
    existing = woo.get("products", sku=plan["sku"], status="any")
    if dry_run:
        product_body(plan, lookups)
        variation_bodies(plan, lookups, [])
        return "esiste già" if existing else f"da creare ({len(plan['variations'])} varianti)"

    if existing:
        product = existing[0]  # type: ignore[index]
        status = "esiste già"
    else:
        product = woo.post("products", product_body(plan, lookups))
        status = "creato"
    if plan["type"] == "simple":
        return status

    present = {v["sku"] for v in woo.all(f"products/{product['id']}/variations")}
    image_ids = [image["id"] for image in product.get("images", [])]
    missing = [b for b in variation_bodies(plan, lookups, image_ids) if b["sku"] not in present]
    for start in range(0, len(missing), BATCH):
        result = woo.post(f"products/{product['id']}/variations/batch", {"create": missing[start : start + BATCH]})
        errors = [item["error"] for item in result.get("create", []) if "error" in item]
        if errors:
            raise RuntimeError(f"varianti di {plan['sku']}: {errors[:3]}")
    return f"{status}, {len(missing)} varianti create"


def load_env(path: Path) -> None:
    """Righe CHIAVE=valore; l'ambiente vince sul file."""
    if not path.exists():
        return
    for line in path.read_text().splitlines():
        key, sep, value = line.strip().partition("=")
        if sep and not key.startswith("#"):
            os.environ.setdefault(key.strip(), value.strip().strip("'\""))


def main() -> int:
    load_env(HERE / ".env")
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", type=Path, default=HERE / "data" / "combatarena.json")
    parser.add_argument("--site", default=os.environ.get("WC_SITE", DEFAULT_SITE))
    parser.add_argument("--brand", action="append", help="solo queste marche (ripetibile)")
    parser.add_argument("--limit", type=int, help="al massimo N prodotti")
    parser.add_argument("--dry-run", action="store_true", help="legge FightHouse ma non scrive nulla")
    args = parser.parse_args()

    key, secret = os.environ.get("WC_KEY"), os.environ.get("WC_SECRET")
    if not key or not secret:
        print("Servono WC_KEY e WC_SECRET (chiavi REST di WooCommerce).", file=sys.stderr)
        return 2

    brands = json.loads(args.data.read_text())["brands"]
    plans = [
        product_plan(product)
        for brand, products in brands.items()
        if not args.brand or brand in args.brand
        for product in products
    ][: args.limit]

    woo = Woo(args.site, key, secret)
    lookups = Lookups(woo, args.dry_run)
    failures = 0
    for number, plan in enumerate(plans, 1):
        try:
            outcome = import_product(plan, woo, lookups, args.dry_run)
        except Exception as exc:  # un prodotto fallito non ferma gli altri; si rilancia dopo
            failures += 1
            outcome = f"ERRORE {exc}"
        print(f"[{number}/{len(plans)}] {plan['sku']} {plan['name']}: {outcome}", flush=True)

    if lookups.created:
        print(f"\n{'Da creare' if args.dry_run else 'Creati'}: {len(lookups.created)} fra categorie, marche e termini")
        for label in lookups.created:
            print(f"  {label}")
    print(f"\n{len(plans) - failures} prodotti ok, {failures} errori")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
