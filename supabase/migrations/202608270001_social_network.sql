-- Additive migration for the social network inside an EXISTING Supabase project.
-- Reviewed against the public schema metadata supplied on 2026-08-27.
-- Existing articles, comments, tags, policies, and data are not modified.

begin;

-- Shared identity profile: preserve the existing display_name/avatar_url model.
alter table public.profiles
  add column if not exists username text;

alter table public.profiles
  add constraint profiles_username_format_check
  check (
    username is null
    or username ~ '^[a-z0-9_]{3,30}$'
  );

create unique index if not exists profiles_username_lower_key
  on public.profiles (lower(username))
  where username is not null;

-- Keep the one existing auth.users trigger. This is a backward-compatible
-- extension: old clients without metadata retain the previous email fallback.
create or replace function public.new_user_profile()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, display_name, username)
  values (
    new.id,
    coalesce(
      nullif(trim(new.raw_user_meta_data ->> 'name'), ''),
      split_part(new.email, '@', 1)
    ),
    lower(nullif(trim(new.raw_user_meta_data ->> 'username'), ''))
  );

  return new;
end
$$;

create table if not exists public.social_profiles (
  user_id uuid primary key references public.profiles(id) on delete cascade,
  bio text,
  location text,
  cover_url text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint social_profiles_bio_length_check
    check (bio is null or char_length(bio) <= 500),
  constraint social_profiles_location_length_check
    check (location is null or char_length(location) <= 120)
);

create table if not exists public.social_posts (
  id uuid primary key default gen_random_uuid(),
  author_id uuid not null references public.profiles(id) on delete cascade,
  content text not null,
  image_url text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint social_posts_content_check
    check (char_length(trim(content)) between 1 and 5000)
);

create table if not exists public.social_comments (
  id uuid primary key default gen_random_uuid(),
  post_id uuid not null references public.social_posts(id) on delete cascade,
  author_id uuid not null references public.profiles(id) on delete cascade,
  content text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint social_comments_content_check
    check (char_length(trim(content)) between 1 and 1000)
);

create table if not exists public.social_likes (
  id uuid primary key default gen_random_uuid(),
  post_id uuid not null references public.social_posts(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  created_at timestamptz not null default now(),
  constraint social_likes_post_user_key unique (post_id, user_id)
);

create table if not exists public.social_friendships (
  id uuid primary key default gen_random_uuid(),
  sender_id uuid not null references public.profiles(id) on delete cascade,
  receiver_id uuid not null references public.profiles(id) on delete cascade,
  status text not null default 'pending',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint social_friendships_not_self_check check (sender_id <> receiver_id),
  constraint social_friendships_status_check check (status in ('pending', 'accepted', 'declined'))
);

-- Prevent duplicate relationships in either direction.
create unique index if not exists social_friendships_pair_key
  on public.social_friendships (
    least(sender_id, receiver_id),
    greatest(sender_id, receiver_id)
  );

create index if not exists social_posts_author_idx
  on public.social_posts (author_id);
create index if not exists social_posts_created_idx
  on public.social_posts (created_at desc);
create index if not exists social_comments_post_created_idx
  on public.social_comments (post_id, created_at);
create index if not exists social_comments_author_idx
  on public.social_comments (author_id);
create index if not exists social_likes_user_idx
  on public.social_likes (user_id);
create index if not exists social_friendships_sender_idx
  on public.social_friendships (sender_id);
create index if not exists social_friendships_receiver_idx
  on public.social_friendships (receiver_id);
create index if not exists social_friendships_status_idx
  on public.social_friendships (status);

create or replace function public.set_social_updated_at()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  new.updated_at = now();
  return new;
end
$$;

create trigger social_profiles_set_updated_at
before update on public.social_profiles
for each row execute function public.set_social_updated_at();

create trigger social_posts_set_updated_at
before update on public.social_posts
for each row execute function public.set_social_updated_at();

create trigger social_comments_set_updated_at
before update on public.social_comments
for each row execute function public.set_social_updated_at();

create trigger social_friendships_set_updated_at
before update on public.social_friendships
for each row execute function public.set_social_updated_at();

alter table public.social_profiles enable row level security;
alter table public.social_posts enable row level security;
alter table public.social_comments enable row level security;
alter table public.social_likes enable row level security;
alter table public.social_friendships enable row level security;

grant select, insert, update, delete on public.social_profiles to authenticated;
grant select, insert, update, delete on public.social_posts to authenticated;
grant select, insert, update, delete on public.social_comments to authenticated;
grant select, insert, delete on public.social_likes to authenticated;
grant select, insert, delete on public.social_friendships to authenticated;
grant update (status) on public.social_friendships to authenticated;

create policy "social profiles authenticated read"
on public.social_profiles for select
to authenticated
using (true);

create policy "social profiles own insert"
on public.social_profiles for insert
to authenticated
with check (auth.uid() = user_id);

create policy "social profiles own update"
on public.social_profiles for update
to authenticated
using (auth.uid() = user_id)
with check (auth.uid() = user_id);

create policy "social profiles own delete"
on public.social_profiles for delete
to authenticated
using (auth.uid() = user_id);

create policy "social posts authenticated read"
on public.social_posts for select
to authenticated
using (true);

create policy "social posts own insert"
on public.social_posts for insert
to authenticated
with check (auth.uid() = author_id);

create policy "social posts own update"
on public.social_posts for update
to authenticated
using (auth.uid() = author_id)
with check (auth.uid() = author_id);

create policy "social posts own delete"
on public.social_posts for delete
to authenticated
using (auth.uid() = author_id);

create policy "social comments authenticated read"
on public.social_comments for select
to authenticated
using (true);

create policy "social comments own insert"
on public.social_comments for insert
to authenticated
with check (auth.uid() = author_id);

create policy "social comments own update"
on public.social_comments for update
to authenticated
using (auth.uid() = author_id)
with check (auth.uid() = author_id);

create policy "social comments own delete"
on public.social_comments for delete
to authenticated
using (auth.uid() = author_id);

create policy "social likes authenticated read"
on public.social_likes for select
to authenticated
using (true);

create policy "social likes own insert"
on public.social_likes for insert
to authenticated
with check (auth.uid() = user_id);

create policy "social likes own delete"
on public.social_likes for delete
to authenticated
using (auth.uid() = user_id);

create policy "social friendships participants read"
on public.social_friendships for select
to authenticated
using (auth.uid() = sender_id or auth.uid() = receiver_id);

create policy "social friendships sender insert"
on public.social_friendships for insert
to authenticated
with check (
  auth.uid() = sender_id
  and sender_id <> receiver_id
  and status = 'pending'
);

create policy "social friendships participants update"
on public.social_friendships for update
to authenticated
using (auth.uid() = sender_id or auth.uid() = receiver_id)
with check (auth.uid() = sender_id or auth.uid() = receiver_id);

create policy "social friendships participants delete"
on public.social_friendships for delete
to authenticated
using (auth.uid() = sender_id or auth.uid() = receiver_id);

commit;
