Notification backend (admin)

Files:
- register_token.php: endpoint for the app to register/unregister push tokens. Public.
- send_notification.php: protected form (password) to send push notifications to registered tokens.
- tokens.json: storage file created at runtime by register_token.php.

Notes:
- This is a minimal implementation using Expo Push API and a JSON file for tokens.
- For production, prefer using a database (Postgres). See SQL schema below.

Postgres schema (recommended):

CREATE TABLE push_tokens (
  id serial primary key,
  token text unique not null,
  device_id text,
  portal boolean default true,
  programacion boolean default true,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

You can modify send_notification.php to read tokens from the DB instead of tokens.json.
