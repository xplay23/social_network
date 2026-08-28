# Existing schema analysis

Reviewed metadata from the existing Supabase project on 2026-08-27.

## Shared identity

- `auth.users` is the canonical account store.
- `public.profiles.id` is both its primary key and a foreign key to `auth.users.id` with cascade deletion.
- `public.profiles` already stores `display_name` and `avatar_url`; these remain the shared identity fields.
- `on_auth_user_created` is the single existing trigger on `auth.users` and invokes `public.new_user_profile()`.
- The migration does not create another auth trigger.

## Existing application

- `articles`, `comments`, `article_tags`, and `tags` belong to the existing application.
- Their tables, functions, triggers, indexes, and RLS policies are not changed.
- In particular, the existing `comments` table is not reused by the social network.

## Additive social-network model

- Add nullable, case-insensitively unique `profiles.username` for shared identity.
- Keep social-only fields in `social_profiles`.
- Store business data only in `social_posts`, `social_comments`, `social_likes`, and `social_friendships`.
- Apply RLS and grants only to the new `social_*` tables.

## Deferred storage work

Storage buckets and policies were not present in the supplied metadata. No storage changes are included until the existing `storage` schema has been inspected.
