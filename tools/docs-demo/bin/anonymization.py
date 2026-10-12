#!/usr/bin/env python3
"""The anonymization map an admin of Fernway drew on /larapilot/anonymization: UK formatters for the
people, the studios kept as they are (they are customers who agreed to be named in the beta)."""
import sys
from pathlib import Path

TARGET = Path(sys.argv[1]).resolve()
MAP = """# How the rows of the database leave Larapilot when they are anonymized —
# on the Database page for a developer in production, and in every SQL dump or seeder
# downloaded with Anonymize. `locale` is the Faker locale of the replacements; under
# `tables`, each column names a strategy (keep, mask, shape) or a fakerphp.org formatter.
# A column with no line follows the safe default. Edit it at /larapilot/anonymization. Commit it.

locale: en_GB
tables:
  instructors:
    bio: realText
    display_name: name
  payments:
    provider_reference: shape
  studios:
    city: keep
    name: keep
    slug: keep
  users:
    email: safeEmail
    name: name
    phone: mobileNumber
"""
(TARGET / "fernway" / ".larapilot" / "anonymization.yaml").write_text(MAP)
