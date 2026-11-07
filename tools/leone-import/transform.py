"""Da prodotti Shopify di Leone1947 a piani di prodotto WooCommerce (FightHouse).

Su Leone ogni colore di un modello è un prodotto a sé (tag «figlio»), più a volte un prodotto
«combined» che li riunisce. Qui si raggruppano per codice modello (lo SKU «AB801-702/XS» è del
modello AB801) e ne esce un solo prodotto variabile per modello, con colore e taglia (o peso).

Niente rete qui: `import_wc.py` trasforma i nomi (attributi, categorie, marca) in ID.
"""

import re
from collections import defaultdict

BRAND = "Leone 1947"
SKU_PREFIX = "LE-"
# WordPress scarica tutte le foto mentre crea il prodotto: un modello con 10 colori da 8 foto l'una
# non finirebbe in tempo. Del primo colore se ne tengono di più, degli altri qualcuna.
MAX_IMAGES_FIRST_COLOR = 8
MAX_IMAGES_PER_COLOR = 3
MAX_IMAGES = 30

BOXING, MUAY_THAI, MMA = "Boxing", "Muay thai", "MMA"
FITNESS, JIU_JITSU, KARATE = "Fitness & crossfit", "Jiu-jitsu", "Karate e judo"
COMBAT = (BOXING, MUAY_THAI)  # prodotti generici: stanno in entrambe, come i Fairtex

# Tag di sport → rami di FightHouse. L'MMA va in Boxing e Muay thai (dove già c'è «Guanti mma») e
# nel ramo MMA.
SPORT_TAGS = {
    "BOXE": (BOXING,),
    "KICK E THA": (MUAY_THAI,),
    "MMA": (BOXING, MUAY_THAI, MMA),
    "JIU-JITSU": (JIU_JITSU,),
    "KAR E JUD": (KARATE,),
    "FIT/KAR": (FITNESS, KARATE),
}
# Stessa cosa ricavata dal titolo, per i prodotti senza tag (in ordine: «KICK/THAI» prima di «BOXE»).
SPORT_WORDS = [
    (r"\bMMA\b", SPORT_TAGS["MMA"]),
    (r"KICK|THAI", SPORT_TAGS["KICK E THA"]),
    (r"JIU|\bBJJ\b|\bGI\b", SPORT_TAGS["JIU-JITSU"]),
    (r"KARATE|JUDO", SPORT_TAGS["KAR E JUD"]),
    (r"\bBOXE\b", SPORT_TAGS["BOXE"]),
]
# Sottocategorie che hanno un ramo fisso, qualunque sia lo sport.
FIXED_BRANCH = {
    "Paratibie": (MUAY_THAI,),
    "Cavigliere": (MUAY_THAI,),
    "Calzari": (MUAY_THAI,),
    "Pantaloncini kick-thai": (MUAY_THAI,),
    "Scarpe boxe": (BOXING,),
    "Pantaloncini boxe": (BOXING,),
    "Canotte boxe": (BOXING,),
    "Pantaloncini mma": (MMA,),
    "Kimono": None,  # segue lo sport (karate/judo o jiu-jitsu)
}

# Tag di Leone che dicono già la sottocategoria.
TYPE_TAGS = {
    "GUANTONI": "Guantoni",
    "PRO": "Guantoni",
    "GN SACCO": "Guanti sacco",
    "GN MMA": "Guanti mma",
    "FIT/KAR": "Guanti fitness",
    "CASCHI": "Casco",
    "PARATIBIA": "Paratibie",
    "CAVIGLIERE": "Cavigliere",
    "BENDAGGI": "Fasce",
    "PARADENTI": "Paradenti",
    "CALZARI": "Calzari",
    "SACCO": "Sacco",
    "CORDA": "Corda",
    "STRUT E AT": "Strutture e attacchi",
    "RING": "Accessori ring",
    "TIMER": "Timer",
    "BORSONI": "Borsoni e zaini",
    "ZAINI": "Borsoni e zaini",
    "SACCHE": "Sacche e marsupi",
    "SCARPE": "Scarpe boxe",
    "CIABATTE": "Ciabatte",
    "PORTACHIAV": "Portachiavi",
    "ASCIUGAMAN": "Asciugamani",
    "BORRACCIA": "Borracce",
    "PALESTRA": "Accessori palestra",
}
# Titoli che vincono sui tag (la casacca da maestro su Leone è fra le borse).
TITLE_OVERRIDES = [
    (r"CASACCA", "Casacca maestro"),
    (r"BERSAGLIO|TARGET", "Scudo"),
]
# Poi il titolo (maiuscolo), in ordine: la prima che corrisponde vince.
TITLE_TYPES = [
    (r"PORTACHIAVI", "Portachiavi"),
    (r"PARATIBIA", "Paratibie"),
    (r"CAVIGLIER", "Cavigliere"),
    (r"CALZAR", "Calzari"),
    (r"PARADENT", "Paradenti"),
    (r"\bCASCO\b", "Casco"),
    (r"CONCHIGLIA", "Conchiglia"),
    (r"CINTURA.*(KARATE|JUDO|JIU|JU JITSU)", "Cinture"),
    (r"CINTURA|PARASENO|PARAPETTO|CORPETTO|PARAGOMIT|PARAGINOCCH|PARACOSC|PROTEZIONE", "Protezioni corpo"),
    (r"\bPAO\b|\bPAD\b|KICK SHIELD", "Pao"),
    (r"SCUDO", "Scudo"),
    (r"PASSATA|MITTS|FOCUS", "Guanti da passata"),
    (r"PIASTRA", "Piastra ematomi"),
    (r"\bPERA\b", "Pera veloce"),
    (r"PALLA TESA", "Palla tesa"),
    (r"GUANTI.*SACCO", "Guanti sacco"),
    (r"GUANTI.*MMA", "Guanti mma"),
    (r"\bSACCO\b|HEAVY BAG", "Sacco"),
    (r"GUANT", "Guantoni"),
    (r"BENDAGG(?!I GINOCCH)", "Fasce"),
    (r"BORSONE|ZAINO|BORSA XXL", "Borsoni e zaini"),
    (r"MARSUPIO|SACCA|BEAUTY|PORTA ", "Sacche e marsupi"),
    (r"STIVALETT|SCARPE", "Scarpe boxe"),
    (r"CIABATT", "Ciabatte"),
    (r"\bCORDA\b", "Corda"),
    (r"JUDOGI|KARATEGI|\bGI\b", "Kimono"),
    (r"FELPA|HOOD", "Felpa"),
    (r"RASHGUARD", "Rashguard"),
    (r"LEGGINGS", "Leggings"),
    (r"REGGISENO|\bTOP\b", "Top"),
    (r"\bTUTA\b|COMPLETO", "Tute"),
    (r"ACCAPPATOIO|VESTAGLIA|KIMONO DA RING", "Vestaglia"),
    (r"CAPPELL|BERRETT|CUFFIA|BANDANA", "Cappello"),
    (r"CANOTT|SMANICATO|SLEEVELESS|TANK", "Canotte"),  # boxe o casual: vedi _refine
    (r"T.?SHIRT|\bTEE\b|MAGLI|POLO", "T-shirt casual"),
    (r"PA\w?NTALONCIN|PATALONCIN|SHORTS?\b|BERMUDA|\bPANTA\b", "Pantaloncini"),  # vedi _refine
    (r"PANTALON|JOGGER|SWEATPANTS|TROUSERS", "Pantaloni"),
]
# Ultima risorsa: la famiglia del tag.
FAMILY_TAGS = {
    "ABBIGLIAME": "Abbigliamento",
    "ABB TEC": "Abbigliamento tecnico",
    "ACCESSORI": "Accessori",
    "ATTREZZI": "Attrezzatura",
    "PROTEZIONI": "Protezioni corpo",
    "CALZATURE": "Calzature",
    "BORSE": "Sacche e marsupi",
}
SERVICE_TAGS = {"figlio", "combined"}


def _sports(tags: set[str], title: str) -> tuple[str, ...]:
    branches: list[str] = []
    for tag, targets in SPORT_TAGS.items():
        if tag in tags:
            branches += [b for b in targets if b not in branches]
    if not branches:
        for pattern, targets in SPORT_WORDS:
            if re.search(pattern, title):
                branches = list(targets)
                break
    return tuple(branches)


def _refine(kind: str, tags: set[str], sports: tuple[str, ...]) -> str:
    """Pantaloncini e canotte: tecnici per sport, altrimenti casual."""
    technical = "ABB TEC" in tags or bool(sports)
    if kind == "Pantaloncini":
        if MUAY_THAI in sports and BOXING not in sports:
            return "Pantaloncini kick-thai"
        if MMA in sports:
            return "Pantaloncini mma"
        if BOXING in sports or technical:
            return "Pantaloncini boxe"
        return "Shorts"
    if kind == "Canotte":
        return "Canotte boxe" if technical and BOXING in sports and MMA not in sports else "Canotta casual"
    return kind


def category_path(tags: list[str], title: str) -> list[tuple[str, str]]:
    """(categoria madre, sottocategoria) in cui va il modello."""
    tagset = set(tags) - SERVICE_TAGS
    upper = title.upper()
    sports = _sports(tagset, upper)
    kind = next((k for pattern, k in TITLE_OVERRIDES if re.search(pattern, upper)), None)
    if kind is None:
        kind = next((TYPE_TAGS[t] for t in TYPE_TAGS if t in tagset), None)
    if kind is None:
        kind = next((k for pattern, k in TITLE_TYPES if re.search(pattern, upper)), None)
    if kind is None:
        kind = next((FAMILY_TAGS[t] for t in FAMILY_TAGS if t in tagset), "Accessori")
    kind = _refine(kind, tagset, sports)

    branches = FIXED_BRANCH.get(kind) or sports or COMBAT
    if kind in FIXED_BRANCH and FIXED_BRANCH[kind] and MMA in sports and MMA not in branches:
        branches = (*branches, MMA)  # es. paratibia MMA: Muay thai e MMA
    return [(branch, kind) for branch in branches]


# --- termini di attributo, nello stile di quelli già su FightHouse --------------------------------

# Colori scritti in breve o diversamente dai termini già su FightHouse.
COLOR_ALIASES = {
    "arancio": "arancione",
    "verde e arancio": "verde e arancione",
    "verde bottigli": "verde bottiglia",
    "verde mim": "verde mimetico",
    "grigio mim": "grigio mimetico",
    "bianco-italia": "bianco italia",
    "fuxia": "fucsia",
}
_LOWER_WORDS = {"da", "di", "del", "della", "con", "per", "e", "x", "a", "in", "il", "la", "le", "lo"}
_UPPER_WORDS = {"MMA", "DNA", "WAKO", "BJJ", "II", "III", "IV", "XXL", "XL", "XS", "PU", "EVA", "USA", "W", "UK"}


def color_term(value: str) -> str:
    """«Nero/Rosso/Oro» → «nero e rosso e oro», «camo blu/verde» → «camo blu e verde»."""
    term = " e ".join(part.strip() for part in value.strip().lower().split("/") if part.strip())
    term = re.sub(r"\s+", " ", term)
    return COLOR_ALIASES.get(term, term)


def size_term(value: str) -> str:
    """Taglie Leone → stile FightHouse: «10OZ» → «10 oz», «L/XL» → «L-XL», «041,5» → «41.5»…"""
    v = re.sub(r"\s+", " ", value.strip())
    if v.upper() in ("U", "UNICA", "TU"):
        return "Unica"
    if m := re.fullmatch(r"(\d+)\s*oz\s*([a-z])", v, re.IGNORECASE):  # «10ozM»: come «16-S oz»
        return f"{m[1]}-{m[2].upper()} oz"
    if m := re.fullmatch(r"(\d+)\s*oz", v, re.IGNORECASE):
        return f"{m[1]} oz"
    if m := re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*(?:m|mt|metri)", v, re.IGNORECASE):
        return f"{m[1].replace(',', '.')} mt"
    if m := re.fullmatch(r"0?(\d\d)(?:[.,](5))?", v):  # numeri di scarpa «041», «041,5»
        if 30 <= int(m[1]) <= 50:
            return m[1] + (f".{m[2]}" if m[2] else "")
    if m := re.fullmatch(r"(\d+)\s*(cm|mm)", v, re.IGNORECASE):
        return f"{m[1]} {m[2].lower()}"
    if re.fullmatch(r"\d?XL", v.upper()) and v[0].isdigit():  # «3XL» → «XXXL»
        return "X" * int(v[0]) + "L"
    v = re.sub(r"(?<=\w)/(?=\w)", "-", v)  # «L/XL» → «L-XL»
    if re.fullmatch(r"A\d+,\d", v):
        v = v.replace(",", ".")
    return v


def weight_term(value: str) -> str | None:
    """«30KG.» → «30 kg»; None se il valore non è un peso."""
    if m := re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*kg\.?", value.strip(), re.IGNORECASE):
        return f"{m[1].replace(',', '.')} kg"
    return None


def product_name(title: str) -> str:
    """«GUANTI BOXE LEONE X DOLLY NOIRE» → «Guanti Boxe Leone x Dolly Noire»."""
    words = []
    for i, word in enumerate(re.sub(r"\s+", " ", title.strip()).split(" ")):
        bare = re.sub(r"[^\w]", "", word)
        if bare.upper() in _UPPER_WORDS or re.fullmatch(r"\d+\w*", bare):
            words.append(word.upper() if bare.upper() in _UPPER_WORDS else word.lower())
        elif i and word.lower() in _LOWER_WORDS:
            words.append(word.lower())
        else:
            words.append(re.sub(r"(^|[-/(])(\w)", lambda m: m[1] + m[2].upper(), word.lower()))
    return " ".join(words)


# --- descrizione ------------------------------------------------------------------------------

_DESCRIPTION_CUTS = [
    re.compile(r"<img\b[^>]*>", re.IGNORECASE),  # loghi e schemi caricati dal sito di Leone
    re.compile(r"<a\b[^>]*leone1947\.com[^>]*>.*?</a>", re.IGNORECASE | re.DOTALL),
    re.compile(r"\s*Attenzione! Si consiglia di verificare[^<]*?nuovi arrivi\.", re.IGNORECASE),
]
_EMPTY_TAGS = re.compile(r"<(p|em|strong|span|div|h\d)\b[^>]*>(\s|&nbsp;|<br\s*/?>)*</\1>", re.IGNORECASE)


def clean_description(html: str) -> str:
    for pattern in _DESCRIPTION_CUTS:
        html = pattern.sub("", html)
    previous = None
    while previous != html:
        previous, html = html, _EMPTY_TAGS.sub("", html)
    return html.strip()


# --- raggruppamento per modello ---------------------------------------------------------------

_HANDLE_MODEL = re.compile(r"-([a-z]{1,4}\d{2,4}[a-z]?)-[a-z0-9-]+$")


def model_code(product: dict) -> str:
    sku = next((v["sku"] for v in product["variants"] if v.get("sku")), None)
    if sku:
        return re.split(r"[-/]", sku)[0].upper()
    if m := _HANDLE_MODEL.search(product["handle"]):
        return m[1].upper()
    return product["handle"].upper()


def is_merchandise(product: dict) -> bool:
    """Fuori le gift card (opzione «Denominazioni», nessun tag di Leone)."""
    return bool(set(product["tags"]) & SERVICE_TAGS) and all(
        o["name"] in ("Colore", "Taglia") for o in product["options"]
    )


def group_models(products: list[dict]) -> dict[str, list[dict]]:
    """Codice modello → prodotti Shopify; i colori «figlio» prima, il «combined» in fondo."""
    models: dict[str, list[dict]] = defaultdict(list)
    for product in products:
        if is_merchandise(product):
            models[model_code(product)].append(product)
    for group in models.values():
        group.sort(key=lambda p: "combined" in p["tags"])
    return dict(models)


def _prices(variant: dict) -> tuple[str, str]:
    """(prezzo pieno, prezzo scontato): se Shopify ha un «prezzo prima» più alto è in saldo."""
    price = variant["price"]
    compare = variant.get("compare_at_price")
    if compare and float(compare) > float(price):
        return compare, price
    return price, ""


def _option_slugs(product: dict) -> dict[int, str]:
    """Posizione dell'opzione → attributo (colore, taglie o peso se le taglie sono tutte pesi)."""
    slugs = {}
    for option in product["options"]:
        if option["name"] == "Colore":
            slugs[option["position"]] = "colore"
        elif option["name"] == "Taglia":
            weights = all(weight_term(v) for v in option["values"])
            slugs[option["position"]] = "peso" if weights else "taglie"
    return slugs


_TERM = {"colore": color_term, "taglie": size_term, "peso": lambda v: weight_term(v) or v}


def model_plan(code: str, group: list[dict]) -> dict:
    lead = next((p for p in group if "combined" not in p["tags"]), group[0])
    name = product_name(lead["title"])
    tags = sorted({t for p in group for t in p["tags"]})
    description = max((p["body_html"] or "" for p in group), key=len)
    plan: dict = {
        "source_ids": [p["id"] for p in group],
        "model": code,
        "sku": f"{SKU_PREFIX}{code}",
        "name": name,
        "description": clean_description(description),
        "brand": BRAND,
        "categories": category_path(tags, lead["title"]),
        "images": [],
        "attributes": {},
        "variations": [],
        "meta": {"_leone_model": code, "_leone_handles": ",".join(p["handle"] for p in group)},
    }

    seen_src: dict[str, int] = {}
    seen_sku: set[str] = set()
    for number, product in enumerate(group):
        slugs = _option_slugs(product)
        color_index = next((pos for pos, slug in slugs.items() if slug == "colore"), None)
        own_images: dict[int, int] = {}  # id immagine Shopify → indice in plan["images"]
        limit = MAX_IMAGES_FIRST_COLOR if number == 0 else MAX_IMAGES_PER_COLOR
        for image in product["images"][:limit]:
            src = image["src"].split("?")[0]
            if src not in seen_src and len(plan["images"]) < MAX_IMAGES:
                seen_src[src] = len(plan["images"])
                color = product["variants"][0].get(f"option{color_index}") if color_index else None
                alt = image.get("alt") or (f"{name} {color_term(color)}" if color else name)
                plan["images"].append({"src": src, "alt": alt})
            if src in seen_src:
                own_images[image["id"]] = seen_src[src]
        first_image = own_images.get(product["images"][0]["id"]) if product["images"] else None

        for variant in product["variants"]:
            attributes = {}
            for position, slug in slugs.items():
                value = variant.get(f"option{position}")
                if value:
                    term = _TERM[slug](value)
                    attributes[slug] = term
                    values = plan["attributes"].setdefault(slug, [])
                    if term not in values:
                        values.append(term)
            sku = variant.get("sku") or "/".join([code, *(re.sub(r"\W+", "", a).upper() for a in attributes.values())])
            if sku in seen_sku:
                continue
            seen_sku.add(sku)
            featured = variant.get("featured_image")
            regular, sale = _prices(variant)
            plan["variations"].append(
                {
                    "sku": sku,
                    "regular_price": regular,
                    "sale_price": sale,
                    "stock_status": "instock" if variant["available"] else "outofstock",
                    "attributes": attributes,
                    "image_index": own_images.get(featured["id"], first_image) if featured else first_image,
                }
            )

    if len(plan["variations"]) == 1:  # una sola variante (es. taglia «U»): prodotto semplice
        variation = plan["variations"][0]
        plan.update(
            type="simple",
            regular_price=variation["regular_price"],
            sale_price=variation["sale_price"],
            stock_status=variation["stock_status"],
            attributes={},
            variations=[],
        )
    else:
        plan["type"] = "variable"
    return plan


def all_plans(products: list[dict]) -> tuple[list[dict], list[str]]:
    """(piani, modelli saltati). Un piano per modello con foto: su Leone i «combined» senza foto
    sono schede vuote, senza disponibilità. Ai titoli doppi si aggiunge il codice modello."""
    plans, skipped = [], []
    for code, group in group_models(products).items():
        if any(p["images"] for p in group):
            plans.append(model_plan(code, group))
        else:
            skipped.append(f"{code} {group[0]['title']}")
    counts: dict[str, int] = defaultdict(int)
    for plan in plans:
        counts[plan["name"]] += 1
    for plan in plans:
        if counts[plan["name"]] > 1:
            plan["name"] = f"{plan['name']} ({plan['model']})"
    return plans, skipped
