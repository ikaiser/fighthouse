# /// script
# requires-python = ">=3.11"
# dependencies = ["pytest>=8"]
# ///
"""uv run --with pytest pytest tools/combatarena-import"""

from transform import category_path, color_term, product_plan, size_term


def _product(**overrides):
    product = {
        "id": 15388371911036,
        "title": "Guanti da passata Fairtex Short FMV14 Rosso",
        "handle": "guanti-fmv14",
        "body_html": "<p>Descrizione</p>",
        "vendor": "Fairtex",
        "product_type": "guanti da passata",
        "options": [
            {"name": "Color", "position": 1, "values": ["ROSSO", "BLU-NERO"]},
            {"name": "Taglia", "position": 2, "values": ["12 Oz", "UNICA"]},
        ],
        "images": [
            {"id": 1, "src": "https://cdn/a.jpg", "alt": "Guanti Rosso-Combat Arena"},
            {"id": 2, "src": "https://cdn/b.jpg", "alt": None},
        ],
        "variants": [
            {
                "sku": "FMV14/12/ROSSO",
                "option1": "ROSSO",
                "option2": "12 Oz",
                "price": "120.00",
                "compare_at_price": None,
                "available": True,
                "featured_image": {"id": 2},
            },
            {
                "sku": "FMV14/UNICA/BLU-NERO",
                "option1": "BLU-NERO",
                "option2": "UNICA",
                "price": "90.00",
                "compare_at_price": "120.00",
                "available": False,
                "featured_image": None,
            },
        ],
    }
    product.update(overrides)
    return product


def test_terms_follow_existing_fighthouse_style():
    assert color_term("BIANCO-ORO") == "bianco e oro"
    assert color_term(" ROSSO ") == "rosso"
    assert size_term("12 Oz") == "12 oz"
    assert size_term("UNICA") == "Unica"
    assert size_term("XL") == "XL"
    assert size_term("38 (US 5.5)") == "38 (US 5.5)"
    assert size_term("48(US 13.5)") == "48 (US 13.5)"


def test_category_path():
    assert category_path("pantaloncini kick-thai", "Rival") == [("Muay thai", "Pantaloncini kick-thai")]
    assert category_path("guantoni", "Cleto Reyes") == [("Boxing", "Guantoni")]
    assert category_path("guantoni", "Fairtex") == [("Boxing", "Guantoni"), ("Muay thai", "Guantoni")]
    assert category_path("Scarpe allenamento", "Nike") == [("Boxing", "Scarpe allenamento")]


def test_variable_product_plan():
    plan = product_plan(_product())
    assert plan["sku"] == "CA-15388371911036"
    assert plan["type"] == "variable"
    assert plan["brand"] == "Fairtex"
    assert plan["images"] == [
        {"src": "https://cdn/a.jpg", "alt": "Guanti Rosso"},
        {"src": "https://cdn/b.jpg", "alt": ""},
    ]
    assert plan["attributes"] == {"colore": ["rosso", "blu e nero"], "taglie": ["12 oz", "Unica"]}
    first, second = plan["variations"]
    assert first == {
        "sku": "FMV14/12/ROSSO",
        "regular_price": "120.00",
        "sale_price": "",
        "stock_status": "instock",
        "attributes": {"colore": "rosso", "taglie": "12 oz"},
        "image_index": 1,
    }
    assert second["regular_price"] == "120.00"
    assert second["sale_price"] == "90.00"
    assert second["stock_status"] == "outofstock"
    assert second["image_index"] is None


def test_default_title_is_simple_product():
    product = _product(
        options=[{"name": "Title", "position": 1, "values": ["Default Title"]}],
        variants=[
            {
                "sku": "X1",
                "option1": "Default Title",
                "option2": None,
                "price": "15.00",
                "compare_at_price": None,
                "available": True,
                "featured_image": None,
            }
        ],
    )
    plan = product_plan(product)
    assert plan["type"] == "simple"
    assert plan["attributes"] == {}
    assert plan["variations"] == []
    assert plan["regular_price"] == "15.00"
    assert plan["stock_status"] == "instock"
