-- Destructive cleanup: removes social-network business data only.
-- Preserved: auth.users, public.profiles, public.social_profiles,
-- and every table belonging to the existing articles application.

begin;

delete from public.social_comments;
delete from public.social_likes;
delete from public.social_friendships;
delete from public.social_posts;

commit;

-- Verification: every value should be 0.
select
  (select count(*) from public.social_posts) as posts,
  (select count(*) from public.social_comments) as comments,
  (select count(*) from public.social_likes) as likes,
  (select count(*) from public.social_friendships) as friendships;
