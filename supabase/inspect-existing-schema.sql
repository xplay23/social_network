-- READ-ONLY schema inspection for an existing Supabase project.
-- Run in Dashboard > SQL Editor, then download/copy the single JSON result.
-- This query reads metadata only; it does not read application rows or auth.users.

with
tables_info as (
  select jsonb_agg(
    jsonb_build_object(
      'table', c.relname,
      'rls_enabled', c.relrowsecurity,
      'rls_forced', c.relforcerowsecurity
    ) order by c.relname
  ) as value
  from pg_class c
  join pg_namespace n on n.oid = c.relnamespace
  where n.nspname = 'public'
    and c.relkind in ('r', 'p')
),
columns_info as (
  select jsonb_agg(
    jsonb_build_object(
      'table', cols.table_name,
      'column', cols.column_name,
      'position', cols.ordinal_position,
      'type', cols.data_type,
      'udt', cols.udt_name,
      'nullable', cols.is_nullable,
      'default', cols.column_default
    ) order by cols.table_name, cols.ordinal_position
  ) as value
  from information_schema.columns cols
  where cols.table_schema = 'public'
),
constraints_info as (
  select jsonb_agg(
    jsonb_build_object(
      'table', rel.relname,
      'name', con.conname,
      'type', case con.contype
        when 'p' then 'primary_key'
        when 'f' then 'foreign_key'
        when 'u' then 'unique'
        when 'c' then 'check'
        when 'x' then 'exclusion'
        else con.contype::text
      end,
      'definition', pg_get_constraintdef(con.oid, true)
    ) order by rel.relname, con.conname
  ) as value
  from pg_constraint con
  join pg_class rel on rel.oid = con.conrelid
  join pg_namespace n on n.oid = rel.relnamespace
  where n.nspname = 'public'
),
indexes_info as (
  select jsonb_agg(
    jsonb_build_object(
      'table', i.tablename,
      'name', i.indexname,
      'definition', i.indexdef
    ) order by i.tablename, i.indexname
  ) as value
  from pg_indexes i
  where i.schemaname = 'public'
),
triggers_info as (
  select jsonb_agg(
    jsonb_build_object(
      'schema', event_object_schema,
      'table', event_object_table,
      'name', trigger_name,
      'timing', action_timing,
      'event', event_manipulation,
      'statement', action_statement
    ) order by event_object_schema, event_object_table, trigger_name
  ) as value
  from information_schema.triggers
  where event_object_schema in ('public', 'auth')
),
policies_info as (
  select jsonb_agg(
    jsonb_build_object(
      'table', p.tablename,
      'name', p.policyname,
      'permissive', p.permissive,
      'roles', to_jsonb(p.roles),
      'command', p.cmd,
      'using', p.qual,
      'check', p.with_check
    ) order by p.tablename, p.policyname
  ) as value
  from pg_policies p
  where p.schemaname = 'public'
),
functions_info as (
  select jsonb_agg(
    jsonb_build_object(
      'schema', n.nspname,
      'name', proc.proname,
      'identity_arguments', pg_get_function_identity_arguments(proc.oid),
      'result', pg_get_function_result(proc.oid),
      'security_definer', proc.prosecdef,
      'definition', pg_get_functiondef(proc.oid)
    ) order by n.nspname, proc.proname
  ) as value
  from pg_proc proc
  join pg_namespace n on n.oid = proc.pronamespace
  where n.nspname in ('public', 'auth')
    and (
      n.nspname = 'public'
      or proc.oid in (
        select tg.tgfoid
        from pg_trigger tg
        join pg_class rel on rel.oid = tg.tgrelid
        join pg_namespace trigger_ns on trigger_ns.oid = rel.relnamespace
        where trigger_ns.nspname = 'auth' and not tg.tgisinternal
      )
    )
)
select jsonb_pretty(
  jsonb_build_object(
    'tables', coalesce((select value from tables_info), '[]'::jsonb),
    'columns', coalesce((select value from columns_info), '[]'::jsonb),
    'constraints', coalesce((select value from constraints_info), '[]'::jsonb),
    'indexes', coalesce((select value from indexes_info), '[]'::jsonb),
    'triggers', coalesce((select value from triggers_info), '[]'::jsonb),
    'policies', coalesce((select value from policies_info), '[]'::jsonb),
    'functions', coalesce((select value from functions_info), '[]'::jsonb)
  )
) as existing_schema_metadata;
