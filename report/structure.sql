CREATE TABLE IF NOT EXISTS  logs (
  id serial PRIMARY KEY,
  app_id character(36) not null, /** unique service ID */
  app_name character(30) not null, /** micro-service name */
  action character(50) not null,
  databox_id character(36),
  base_id character(36),
  item character(36),
  user_id character(36),
  payload json,
  event_date timestamp not null default CURRENT_TIMESTAMP,
  created_at timestamp not null default CURRENT_TIMESTAMP
);
