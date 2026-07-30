# Admin Form for config.txt

This folder contains a simple PHP script to edit `config.txt` on the server.

Files:
- edit_config.php: minimal web form. Requires PHP on the host.

Usage:
1. Edit `admin/edit_config.php` and change the value of `$PASSWORD` to a secure secret.
2. Upload `edit_config.php` to the same directory where `config.txt` is served (e.g. the site root).
3. Open `https://tvbragado.com.ar/admin/edit_config.php` in a browser, enter the password, edit values and save.

Security:
- This is intentionally minimal. Protect the script by renaming, placing behind server auth, or removing after use.

Config format written by the script:
1st line: logo URL
2nd line: stream URL
follow lines: `fondo: #hex`, `botonfondo: value`, `colorletras: #hex`
