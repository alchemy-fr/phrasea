SELECT
id,
app_id,
app_name,
action,
item,
user_id,
CAST (payload AS TEXT),
event_date,
created_at
FROM logs
WHERE created_at > :sql_last_value
ORDER BY created_at ASC
LIMIT 1000
