Admin deployment notes

- The PHP admin scripts are stored in this folder and should be uploaded to the server via SFTP/FTP.
- For security, do NOT store secrets in these files. Instead set the following environment variable on the server:
  - ADMIN_PASSWORD (the login password used by admin/index.php). If not set, admin login is disabled.
- Ensure these files are writable by the web server where required (tokens.json and push_log.txt will be created by the scripts).
- Files that must NOT be committed to git and must remain on the server only:
  - admin/tokens.json
  - admin/*.p8
  - admin/push_log.txt
  - admin/*.json (other credentials)

Deployment steps:
1. Upload the PHP files (index.php, send_notification.php, edit_config.php, register_token.php) to /admin directory on the server.
2. Set the ADMIN_PASSWORD environment variable in your hosting control panel or in the server environment.
3. Ensure PHP has write permissions to create/update /admin/tokens.json and /admin/push_log.txt.
4. Test: open https://tvbragado.com.ar/admin/, login, and test sending a notification.
