# The Streets: Turf Wars

A browser-based gang war game set in Los Santos. Players join a faction, move between districts, commit crimes, run drugs, and fight in real-time turf wars to control the city.

Built with PHP, MySQL, and vanilla JavaScript. No frameworks, no build step — drop it on any shared host and go.

---

## Requirements

- PHP 7.4+ with PDO and PDO_MySQL extensions enabled
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (or equivalent Nginx config)
- A web host or local stack (XAMPP, Laragon, etc.)

---

## Installation

### 1. Create the database

Create a new MySQL database and import the schema:

```sql
CREATE DATABASE turf_wars CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema file:

```bash
mysql -u your_user -p turf_wars < sols_turf_wars.sql
```

> `sols_turf_wars.sql` contains all table definitions and seed data (factions, turfs, weapons, turf control) and is included in the root of the repo.

### 2. Configure database credentials

Copy the example env file and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```
DB_HOST=localhost
DB_NAME=turf_wars
DB_USER=your_db_user
DB_PASS=your_db_password
DB_PORT=3306
```

The app reads these via `getenv()`. You can set them as Apache environment variables instead if you prefer — add to your `.htaccess` or `VirtualHost`:

```apache
SetEnv DB_HOST localhost
SetEnv DB_NAME turf_wars
SetEnv DB_USER your_db_user
SetEnv DB_PASS your_db_password
```

> **Note:** The `.env` file is not automatically loaded by PHP. If you use a `.env` file you will need to load it via your server config (Apache `SetEnvIf`), a PHP autoloader, or a library like [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv). The recommended approach for most hosts is to use Apache `SetEnv` directives.

### 3. Upload files

Upload all project files to your web root (e.g. `public_html/`). The `includes/` directory contains sensitive files — it's protected by `.htaccess` but make sure your host respects `.htaccess` rules.

### 4. Set the base URL

In `includes/config.php`, the `base_url` defaults to the `APP_BASE_URL` environment variable. Set this to your domain:

```
APP_BASE_URL=https://yourdomain.com
```

Or edit the fallback directly in `config.php`.

### 5. Create an admin account

Register normally through the site. Then manually set `is_admin = 1` for your user in the database:

```sql
UPDATE users SET is_admin = 1 WHERE username = 'your_username';
```

The admin panel is then accessible at `/admin.php`.

---

## Configuration reference

All config is in `includes/config.php`. Values are read from environment variables with fallbacks:

| Env Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_NAME` | `turf_wars` | Database name |
| `DB_USER` | `db_user` | Database username |
| `DB_PASS` | *(empty)* | Database password |
| `DB_PORT` | `3306` | Database port |
| `APP_BASE_URL` | `http://localhost` | Public URL of the site |
| `APP_SESSION_NAME` | `stw_session` | PHP session cookie name |

To enable maintenance mode (blocks non-admin logins), set `maintenance => true` in `config.php`.

---

## Project structure

```
/
├── includes/           # Core PHP includes (DB, auth, config, CSRF, etc.)
│   ├── bootstrap.php   # Loaded by every page
│   ├── config.php      # App + DB configuration
│   ├── db.php          # PDO singleton
│   ├── auth.php        # Login/logout/session helpers
│   ├── functions.php   # Shared utility functions
│   ├── logger.php      # Game action logger
│   └── csrf.php        # CSRF token generation + validation
├── templates/
│   ├── header.php      # Nav, topbar, game shell open
│   └── footer.php      # Game shell close
├── assets/
│   ├── css/            # Stylesheet
│   ├── js/             # Client-side game logic
│   └── img/            # Map, faction icons, UI images
├── index.php           # Landing page
├── login.php           # Login
├── register.php        # Registration
├── dashboard.php       # Main game screen
├── actions.php         # All player actions (crime, drugs, capture, war, etc.)
├── turf.php            # Interactive map
├── war.php             # Real-time turf war screen
├── dominance.php       # City control leaderboard
├── players.php         # Player list
├── faction.php         # Faction roster
├── admin.php           # Admin panel
├── manual.php          # In-game manual
└── sols_turf_wars.sql  # Database schema + seed data
```

---

## Security notes

- All DB queries use PDO prepared statements
- CSRF tokens are validated on every POST and AJAX request
- The `includes/` directory is blocked from direct web access via `.htaccess`
- Passwords are hashed with `password_hash()` / verified with `password_verify()`
- Never commit your `.env` file or put real credentials in `config.php`
- The database user should have only `SELECT`, `INSERT`, `UPDATE`, `DELETE` — not `DROP` or `ALTER`

---

## License

MIT — do whatever you want with it, just don't claim you made it from scratch.
