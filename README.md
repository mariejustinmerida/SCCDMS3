# SCC Document Management System

A comprehensive document management system for Saint Columban College.

## Directory Structure

```
/
├── actions/            # Form processing and action handlers
├── api/                # API endpoints and AJAX handlers
├── assets/             # Static assets
│   ├── css/            # CSS files
│   ├── images/         # Image files
│   └── js/             # JavaScript files
├── auth/               # Authentication-related files
├── database/           # Database files and migrations
├── includes/           # Reusable PHP components and configuration
├── pages/              # Main page files
├── storage/            # Storage for uploaded and generated content
│   ├── documents/      # Document files
│   ├── logs/           # Log files
│   ├── profiles/       # Profile images
│   └── uploads/        # Uploaded files
└── vendor/             # Composer dependencies
```

## Key Files

- `index.php` - Entry point that redirects to login
- `.htaccess` - Apache configuration for routing and security
- `includes/config.php` - Database configuration
- `auth/login.php` - Login page
- `pages/dashboard.php` - Main dashboard

## Setup Instructions

1. Clone the repository.
2. Install dependencies: `composer install`
3. Copy `includes/.env.example` to `includes/.env` and set your database credentials and API keys (e.g. `OPENAI_API_KEY`). For a full production template see `config/production.env.example`. **Never commit `.env` or real secrets.**
4. Import the database schema from `database/scc_dms.sql`
5. Ensure proper permissions for `storage/` subdirectories (documents, logs, profiles, uploads).
6. Access the application through your web server.

## Dependencies

- PHP 7.4+
- MySQL 5.7+
- Composer packages (see composer.json)

## Pushing to GitHub

1. **Initialize Git** (if not already):
   ```bash
   git init
   ```

2. **Add and commit**:
   ```bash
   git add .
   git commit -m "Initial commit: SCC Document Management System"
   ```

3. **Create a new repository** on GitHub (do not add a README or .gitignore there).

4. **Add remote and push**:
   ```bash
   git remote add origin https://github.com/YOUR_USERNAME/SCCDMS3.git
   git branch -M main
   git push -u origin main
   ```
   Replace `YOUR_USERNAME/SCCDMS3` with your GitHub username and repo name. If you use SSH: `git@github.com:YOUR_USERNAME/SCCDMS3.git`.

**Security check:** Before pushing, ensure `.env` and `includes/.env` are not staged (they are in `.gitignore`). Run `git status` and confirm no sensitive files are listed. If you ever committed a secret, rotate the key (e.g. generate a new OpenAI API key) and remove it from history. 