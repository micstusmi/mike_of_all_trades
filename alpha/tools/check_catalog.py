#!/usr/bin/env python3
"""Fail when a protected UI choice or feature evidence disappears without review."""
import json
import re
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[2]
catalog = json.loads((root / "alpha/catalog/features.json").read_text())
controls = json.loads((root / "alpha/catalog/protected_controls.json").read_text())
errors = []
seen = set()
for item in catalog:
    if item["id"] in seen:
        errors.append(f"duplicate feature {item['id']}")
    seen.add(item["id"])
    if item["policy"] not in {"protected", "flexible"}:
        errors.append(f"invalid policy for {item['id']}")
    if item["alpha"] not in {"foundation", "in development", "planned migration", "planned per-business adapter", "planned per-business service", "planned"}:
        errors.append(f"invalid alpha availability for {item['id']}")
    if not (root / item["evidence"]).is_file():
        errors.append(f"missing evidence for {item['id']}: {item['evidence']}")
    if item["screenshot"] is not None and not (root / item["screenshot"]).is_file():
        errors.append(f"missing screenshot for {item['id']}")

for filename, groups in controls.items():
    text = (root / filename).read_text()
    for name, expected in groups.items():
        if name == "quick_actions":
            actual = set(re.findall(r'name="quick_action"\s+value="([^"]+)"', text))
        else:
            select = re.search(r'<select\b[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', text, re.S)
            actual = set(re.findall(r'<option\s+value="([^"]+)"', select.group(1))) if select else set()
        missing = sorted(set(expected) - actual)
        if missing:
            errors.append(f"{filename}: {name} lost {', '.join(missing)}")

if errors:
    print("Feature catalogue regression check failed:\n" + "\n".join(errors), file=sys.stderr)
    sys.exit(1)
print(f"PASS: {len(catalog)} catalogue entries and protected UI choices checked.")
