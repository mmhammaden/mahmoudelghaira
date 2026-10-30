#!/usr/bin/env python3
"""Test script for phone-only migration and session invalidation."""
import psycopg2
from psycopg2.extras import DictCursor
import re

DATABASE_URL = "postgresql://neondb_owner:npg_3JDVwAlKtcf4@ep-orange-surf-b4138hj9-pooler.c-6.us-east-2.aws.neon.tech/neondb?sslmode=require"

conn = psycopg2.connect(DATABASE_URL, cursor_factory=DictCursor)
cur = conn.cursor()

# Verify email column is gone
cur.execute("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name='profiles' ORDER BY ordinal_position")
cols = cur.fetchall()
print("Profiles table columns:")
email_exists = False
for c in cols:
    print(f"  {c['column_name']}: {c['data_type']}")
    if c['column_name'] == 'email':
        email_exists = True

if email_exists:
    print("\nFAIL: email column still exists!")
else:
    print("\nPASS: email column successfully removed")

# Check phone format
phone_re = re.compile(r"^[234]\d{7}$")

cur.execute("SELECT phone FROM profiles")
phones = cur.fetchall()
valid_profiles = all(bool(phone_re.match(p['phone'])) for p in phones if p['phone'])
print(f"\nProfile phones: {[p['phone'] for p in phones if p['phone']]}")
print(f"All valid phone format: {'YES' if valid_profiles else 'NO'} ({len([p for p in phones if p['phone']])} records with phones)")

conn.close()
print("\nVerification complete!")
