"""Da prodotto Shopify (Combat Arena) a piano di prodotto WooCommerce (FightHouse).

Niente rete qui: `import_wc.py` trasforma i nomi (attributi, categorie, marca) in ID.
"""

import re

# Tipi di prodotto che su FightHouse stanno sotto «Muay thai»; tutti gli altri sotto «Boxing».
# Fairtex è una marca thai: i suoi prodotti generici (guantoni, caschi…) stanno in entrambe,
# come i guantoni Adidas già presenti.
MUAY_THAI_TYPES = {"pantaloncini kick-thai", "paratibie", "cavigliere", "pao", "cinturone", "paracosce"}
BOTH_BRANDS = {"Fairtex"}

# Nome dell'opzione Shopify → slug dell'attributo globale su FightHouse (pa_colore, pa_taglie).
OPTION_ATTRIBUTES = {"Color": "colore", "Taglia": "taglie"}

_ALT_SUFFIX = re.compile(r"\s*-\s*Combat Arena\s*$", re.IGNORECASE)


def color_term(value: str) -> str:
    """«BIANCO-ORO» → «bianco e oro», come i termini già su FightHouse."""
    return " e ".join(part.strip() for part in value.strip().lower().split("-") if part.strip())


def size_term(value: str) -> str:
    """«12 Oz» → «12 oz», «UNICA» → «Unica»; le altre taglie restano come sono."""
    value = value.strip()
    if value.upper() == "UNICA":
        return "Unica"
    value = re.sub(r"(\S)\(", r"\1 (", value)
    return re.sub(r"\bOz\b", "oz", value, flags=re.IGNORECASE)


_TERM = {"colore": color_term, "taglie": size_term}


def category_path(product_type: str, brand: str) -> list[tuple[str, str]]:
    """(categoria madre, sottocategoria) in cui va il prodotto."""
    kind = product_type.strip()
    child = kind[:1].upper() + kind[1:]
    if kind.lower() in MUAY_THAI_TYPES:
        return [("Muay thai", child)]
    if brand in BOTH_BRANDS:
        return [("Boxing", child), ("Muay thai", child)]
    return [("Boxing", child)]


def _prices(variant: dict) -> tuple[str, str]:
    """(prezzo pieno, prezzo scontato): se Shopify ha un «prezzo prima» più alto è in saldo."""
    price = variant["price"]
    compare = variant.get("compare_at_price")
    if compare and float(compare) > float(price):
        return compare, price
    return price, ""


def product_plan(product: dict) -> dict:
    images = [
        {"src": image["src"], "alt": _ALT_SUFFIX.sub("", image.get("alt") or "")}
        for image in product["images"]
    ]
    image_index = {image["id"]: i for i, image in enumerate(product["images"])}
    plan = {
        "source_id": product["id"],
        "source_handle": product["handle"],
        "sku": f"CA-{product['id']}",
        "name": product["title"].strip(),
        "description": product["body_html"] or "",
        "brand": product["vendor"],
        "categories": category_path(product["product_type"], product["vendor"]),
        "images": images,
        "attributes": {},
        "variations": [],
    }

    options = [(o["position"], OPTION_ATTRIBUTES.get(o["name"])) for o in product["options"]]
    if all(slug is None for _, slug in options):
        variant = product["variants"][0]
        regular, sale = _prices(variant)
        plan.update(
            type="simple",
            regular_price=regular,
            sale_price=sale,
            stock_status="instock" if variant["available"] else "outofstock",
        )
        return plan

    plan["type"] = "variable"
    for variant in product["variants"]:
        attributes = {}
        for position, slug in options:
            value = variant.get(f"option{position}")
            if slug and value:
                term = _TERM[slug](value)
                attributes[slug] = term
                values = plan["attributes"].setdefault(slug, [])
                if term not in values:
                    values.append(term)
        regular, sale = _prices(variant)
        featured = variant.get("featured_image")
        plan["variations"].append(
            {
                "sku": variant["sku"],
                "regular_price": regular,
                "sale_price": sale,
                "stock_status": "instock" if variant["available"] else "outofstock",
                "attributes": attributes,
                "image_index": image_index.get(featured["id"]) if featured else None,
            }
        )
    return plan
