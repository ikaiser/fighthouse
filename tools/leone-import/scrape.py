# /// script
# requires-python = ">=3.11"
# dependencies = ["httpx>=0.27"]
# ///
"""Legge da Leone1947 (Shopify) tutto il catalogo e lo salva in un JSON.

Stesso client prudente di `tools/combatarena-import/scrape.py`: robots.txt rispettato, pausa
casuale fra le richieste, tetto di richieste, davanti a un 403 o alla verifica di Cloudflare ci si
ferma. Leone rallenta `/products.json` dopo tre pagine piene (429 senza Retry-After): pause più
lunghe e fino a tre nuovi tentativi, aspettando ogni volta di più (60, 120, 180 s).

Leone è una marca sola e non ha collezioni per marca: si legge `/products.json` (250 per pagina,
circa 2000 prodotti, ognuno un colore di un modello). Il raggruppamento per modello è in
`transform.py`.

Uso:  uv run tools/leone-import/scrape.py [--out data/leone.json]
"""

import argparse
import json
import random
import re
import sys
import time
from datetime import UTC, datetime
from pathlib import Path
from urllib.parse import urlsplit

import httpx

BASE_URL = "https://www.leone1947.com"
PAGE_SIZE = 250
DELAY_S = (10.0, 20.0)
MAX_REQUESTS = 40
MAX_WAIT_S = 180
RETRIES = 3
USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36"
CHALLENGE_MARKERS = ("Just a moment", "challenges.cloudflare.com")


class Blocked(Exception):
    pass


def parse_robots(text: str) -> list[tuple[bool, str]]:
    """Regole (consentito?, schema) del gruppo `User-agent: *`."""
    rules: list[tuple[bool, str]] = []
    agents: list[str] = []
    in_rules = False
    for raw in text.splitlines():
        key, sep, value = raw.split("#", 1)[0].strip().partition(":")
        if not sep:
            continue
        key, value = key.strip().lower(), value.strip()
        if key == "user-agent":
            if in_rules:
                agents, in_rules = [], False
            agents.append(value)
        elif key in ("allow", "disallow"):
            in_rules = True
            if "*" in agents and value:
                rules.append((key == "allow", value))
    return rules


def robots_allows(rules: list[tuple[bool, str]], path: str) -> bool:
    """Vince la regola con lo schema più lungo; a parità, quella che consente."""
    best: tuple[int, bool] | None = None
    for allow, pattern in rules:
        anchored = pattern.endswith("$")
        body = pattern[:-1] if anchored else pattern
        regex = ".*".join(re.escape(part) for part in body.split("*")) + ("$" if anchored else "")
        if re.match(regex, path):
            best = max(best, (len(pattern), allow)) if best else (len(pattern), allow)
    return True if best is None else best[1]


class PoliteClient:
    def __init__(self) -> None:
        self._client = httpx.Client(
            headers={"User-Agent": USER_AGENT, "Accept-Language": "it-IT,it;q=0.9"},
            timeout=30,
            follow_redirects=True,
        )
        self._rules: list[tuple[bool, str]] | None = None
        self.requests_made = 0

    def _fetch(self, url: str) -> httpx.Response:
        if self.requests_made >= MAX_REQUESTS:
            raise Blocked(f"tetto di {MAX_REQUESTS} richieste raggiunto")
        if self.requests_made:
            time.sleep(random.uniform(*DELAY_S))
        self.requests_made += 1
        return self._client.get(url)

    def _robots(self) -> list[tuple[bool, str]]:
        if self._rules is None:
            response = self._fetch(f"{BASE_URL}/robots.txt")
            if response.status_code == 200:
                self._rules = parse_robots(response.text)
            elif 400 <= response.status_code < 500:
                self._rules = []
            else:
                self._rules = [(False, "/")]
        return self._rules

    def get_json(self, url: str) -> dict:
        parts = urlsplit(url)
        path = parts.path + (f"?{parts.query}" if parts.query else "")
        if not robots_allows(self._robots(), path):
            raise Blocked(f"robots.txt vieta {path}")
        response = self._fetch(url)
        for attempt in range(1, RETRIES + 1):
            if response.status_code not in (429, 503):
                break
            wait = max(int(response.headers.get("retry-after") or 0), 60 * attempt)
            if wait > MAX_WAIT_S:
                raise Blocked(f"{response.status_code}: il sito chiede di aspettare {wait}s")
            print(f"  {response.status_code}, aspetto {wait}s e riprovo", file=sys.stderr)
            time.sleep(wait + random.uniform(1, 5))
            response = self._fetch(url)
        if response.status_code in (403, 429, 503) or any(
            m in response.text for m in CHALLENGE_MARKERS
        ):
            raise Blocked(f"{response.status_code} su {url}")
        response.raise_for_status()
        return response.json()


def collect_all(client: PoliteClient) -> list[dict]:
    products: list[dict] = []
    page = 1
    while True:
        url = f"{BASE_URL}/products.json?limit={PAGE_SIZE}&page={page}"
        batch = client.get_json(url).get("products", [])
        products.extend(batch)
        print(f"pagina {page}, {len(batch)} prodotti", file=sys.stderr)
        if len(batch) < PAGE_SIZE:
            return products
        page += 1


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--out", type=Path, default=Path(__file__).parent / "data" / "leone.json")
    args = parser.parse_args()

    client = PoliteClient()
    try:
        products = collect_all(client)
    except Blocked as exc:
        print(f"Interrotto: {exc}", file=sys.stderr)
        return 1
    result = {"source": BASE_URL, "scraped_at": datetime.now(UTC).isoformat(), "products": products}
    args.out.parent.mkdir(parents=True, exist_ok=True)
    args.out.write_text(json.dumps(result, ensure_ascii=False, indent=1))
    print(f"{len(products)} prodotti in {args.out} ({client.requests_made} richieste)", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
