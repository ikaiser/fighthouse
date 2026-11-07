# /// script
# requires-python = ">=3.11"
# dependencies = ["pytest>=8"]
# ///
"""uv run --with pytest pytest tools/leone-import"""

from transform import (
    all_plans,
    category_path,
    clean_description,
    color_term,
    group_models,
    model_code,
    product_name,
    size_term,
    weight_term,
)


def _variant(sku, color, size, price="27.90", compare="0.00", available=True):
    return {
        "sku": sku,
        "option1": color,
        "option2": size,
        "price": price,
        "compare_at_price": compare,
        "available": available,
        "featured_image": None,
    }


def _child(pid, color, code, images, tags=("ABB TEC", "figlio", "KICK E THA"), body=""):
    return {
        "id": pid,
        "title": "PANTA THAI BASIC W",
        "handle": f"panta-thai-basic-w-ab801-{color}",
        "body_html": body,
        "tags": list(tags),
        "options": [
            {"name": "Colore", "position": 1, "values": ["nero", "lavanda"]},
            {"name": "Taglia", "position": 2, "values": ["S", "M"]},
        ],
        "images": [{"id": pid * 10 + i, "src": f"https://cdn/{color}{i}.jpg?v=1", "alt": None} for i in range(images)],
        "variants": [
            _variant(f"AB801-{code}/S", color, "S"),
            _variant(f"AB801-{code}/M", color, "M", price="25.00", compare="27.90", available=False),
        ],
    }


def test_terms_follow_existing_fighthouse_style():
    assert color_term("Nero/Rosso/Oro") == "nero e rosso e oro"
    assert color_term("camo blu/verde") == "camo blu e verde"
    assert color_term("arancio") == "arancione"
    assert size_term("10OZ") == "10 oz"
    assert size_term("22 OZ") == "22 oz"
    assert size_term("10ozM") == "10-M oz"
    assert size_term("U") == "Unica"
    assert size_term("L/XL") == "L-XL"
    assert size_term("3XL") == "XXXL"
    assert size_term("3,5 m") == "3.5 mt"
    assert size_term("041") == "41"
    assert size_term("041,5") == "41.5"
    assert size_term("000") == "000"  # cinture da karate, non scarpe
    assert size_term("A1,5") == "A1.5"
    assert size_term("110cm") == "110 cm"
    assert weight_term("30KG.") == "30 kg"
    assert weight_term("L") is None


def test_product_name():
    assert product_name("GUANTI BOXE LEONE X DOLLY NOIRE") == "Guanti Boxe Leone x Dolly Noire"
    assert product_name("PANTALONCINO KICK/THAI URBAN FIGHTER") == "Pantaloncino Kick/Thai Urban Fighter"
    assert product_name("GUANTI MMA CAGE") == "Guanti MMA Cage"
    assert product_name("PERA DNA 3KG") == "Pera DNA 3kg"


def test_category_path():
    assert category_path(["GUANTI", "GUANTONI"], "GUANTI BOXE URBAN FIGHTER") == [("Boxing", "Guantoni")]
    assert category_path(["ABB TEC", "KICK E THA"], "PANTA THAI BASIC W") == [("Muay thai", "Pantaloncini kick-thai")]
    assert category_path(["PARATIBIA", "PROTEZIONI"], "PARATIBIA WAKO") == [("Muay thai", "Paratibie")]
    assert category_path(["GN MMA", "GUANTI", "MMA"], "GUANTI MMA CAGE") == [
        ("Boxing", "Guanti mma"),
        ("Muay thai", "Guanti mma"),
        ("MMA", "Guanti mma"),
    ]
    assert category_path(["ABBIGLIAME", "TUTE E FEL"], "FELPA CON CAPPUCCIO DNA") == [
        ("Boxing", "Felpa"),
        ("Muay thai", "Felpa"),
    ]
    assert category_path(["ABB TEC", "KAR E JUD"], "JUDOGI") == [("Karate e judo", "Kimono")]
    assert category_path(["BORSE", "SACCHE", "BOXE"], "CASACCA MAESTRO PREMIUM") == [("Boxing", "Casacca maestro")]
    # senza tag: dal titolo
    assert category_path(["combined"], "PARATIBIA JUNGLE FORCE") == [("Muay thai", "Paratibie")]


def test_clean_description_drops_leone_site_references():
    html = (
        '<p>Portachiavi. Attenzione! Si consiglia di verificare per il colore desiderato la disponibilità '
        "e di controllare sul sito www.leone1947.com i nuovi arrivi.</p>"
        '<p><em><img alt="x" src="https://www.leone1947.com/media/a.png"></em></p>'
        '<div><a class="b" href="https://www.leone1947.com/p/bendaggio">clicca qui</a></div>'
        "<p>Pelle di vitello</p>"
    )
    assert clean_description(html) == "<p>Portachiavi.</p><p>Pelle di vitello</p>"


def test_children_of_a_model_become_one_variable_product():
    products = [
        _child(1, "nero", "01", images=10, body="<p>Lunga descrizione</p>"),
        _child(2, "lavanda", "702", images=5),
        {"id": 9, "title": "Gift card 50", "handle": "gift-card-50", "body_html": "", "tags": [],
         "options": [{"name": "Denominazioni", "position": 1, "values": ["50 €"]}],
         "images": [], "variants": [_variant("GIFT50", "50 €", None)]},
    ]
    assert model_code(products[0]) == "AB801"
    assert list(group_models(products)) == ["AB801"]  # niente gift card

    (plan,), skipped = all_plans(products)
    assert skipped == []
    assert plan["sku"] == "LE-AB801"
    assert plan["type"] == "variable"
    assert plan["name"] == "Panta Thai Basic W"
    assert plan["description"] == "<p>Lunga descrizione</p>"
    assert plan["categories"] == [("Muay thai", "Pantaloncini kick-thai")]
    assert plan["attributes"] == {"colore": ["nero", "lavanda"], "taglie": ["S", "M"]}
    # 8 foto del primo colore, 3 degli altri, senza «?v=»
    assert len(plan["images"]) == 11
    assert plan["images"][0] == {"src": "https://cdn/nero0.jpg", "alt": "Panta Thai Basic W nero"}
    assert [v["sku"] for v in plan["variations"]] == ["AB801-01/S", "AB801-01/M", "AB801-702/S", "AB801-702/M"]
    second = plan["variations"][1]
    assert (second["regular_price"], second["sale_price"], second["stock_status"]) == ("27.90", "25.00", "outofstock")
    assert plan["variations"][0]["image_index"] == 0
    assert plan["variations"][2]["image_index"] == 8  # prima foto della lavanda


def test_single_variant_is_simple_and_imageless_models_are_skipped():
    single = _child(1, "nero", "01", images=1)
    single["variants"] = single["variants"][:1]
    empty = _child(2, "nero", "01", images=0, tags=("combined",))
    empty["variants"] = [_variant("GN999-01/12", "nero", "12OZ")]
    plans, skipped = all_plans([single, empty])
    assert [p["sku"] for p in plans] == ["LE-AB801"]
    assert plans[0]["type"] == "simple"
    assert plans[0]["regular_price"] == "27.90"
    assert plans[0]["attributes"] == {}
    assert skipped == ["GN999 PANTA THAI BASIC W"]
