# Existing Supabase project

This directory intentionally contains no migration yet. The existing remote schema must be inspected before creating one, especially `profiles`, registration triggers, RLS policies, foreign keys, and indexes.

Connect this repository to the existing project (never create a new project), then provide a schema dump for review:

```bash
supabase login
supabase link --project-ref YOUR_EXISTING_PROJECT_REF
supabase db dump --linked --schema public -f supabase/existing-schema.sql
```

Before committing, review whether the dump contains sensitive application data. A schema-only dump should contain definitions, not table rows.
