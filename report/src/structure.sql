CREATE TABLE IF NOT EXISTS logs (
  id serial PRIMARY KEY,
  app_id varchar(36) not null, /** unique service ID */
  app_name varchar(30) not null, /** micro-service name */
  action varchar(50) not null,
  item varchar(36),
  user_id varchar(36),
  payload json,
  event_date timestamp not null default CURRENT_TIMESTAMP,
  created_at timestamp not null default CURRENT_TIMESTAMP
);
