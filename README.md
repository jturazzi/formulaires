# Formulaires

> 🇫🇷 [Version française](README.fr.md)

**Formulaires** is an open source, self-hosted form builder. Create forms with sections, questions and file uploads, share a public link, and collect all responses in one place — with GDPR compliance built in.

Built with **Laravel 13**, **Inertia.js v2** and **Vue 3** (TypeScript, Tailwind CSS).

## Features

- **Form builder** — sections, drag & drop reordering, ten question types: short/long text, email, number, date, single choice, multiple choice, dropdown, file upload and static text blocks.
- **Branding** — per-form logo and theme color.
- **Public link** — respondents don't need an account. Optional per-form settings: email verification by 6-digit code, response limit, closing date.
- **Centralized responses** — table view, detail view, secure file downloads, CSV export (Excel-friendly).
- **Microsoft 365 SSO** — managers sign in with their organization account (Azure AD / Entra ID). The first user to sign in becomes administrator. Classic email/password auth is available as an option for deployments without Microsoft 365.
- **Roles** — administrators (manage users, legal pages and GDPR defaults) and creators (manage their own forms).
- **GDPR** — explicit consent checkbox with links to editable terms of use and privacy policy, per-form document retention period, daily automatic purge of expired responses **and their files**, right-to-erasure deletion of individual responses.
- **Bilingual** — French and English out of the box (framework ready for more locales).
- **Email notifications** — optional email to the form owner on each new response.

## Requirements

- PHP ≥ 8.3 with common extensions
- Composer
- Node.js ≥ 20 and npm
- MySQL/MariaDB (recommended), PostgreSQL or SQLite

## Installation

```bash
git clone https://github.com/jturazzi/formulaires.git
cd formulaires

composer install
npm install

cp .env.example .env
php artisan key:generate

# Configure DB_* in .env, then:
php artisan migrate --seed   # seeds default GDPR legal page templates
php artisan storage:link

npm run build
```

For local development:

```bash
composer run dev   # serves the app, queue, logs and Vite dev server
```

### Scheduled purge (GDPR)

Add the Laravel scheduler to your crontab so expired responses are purged daily:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

You can preview what would be deleted with:

```bash
php artisan responses:purge --dry-run
```

### Microsoft 365 SSO setup

1. Go to [Microsoft Entra admin center](https://entra.microsoft.com) → *App registrations* → *New registration*.
2. Set the redirect URI (type *Web*) to `https://your-domain/auth/microsoft/callback`.
3. Create a client secret under *Certificates & secrets*.
4. Fill in `.env`:

```dotenv
MICROSOFT_CLIENT_ID=your-application-id
MICROSOFT_CLIENT_SECRET=your-client-secret
MICROSOFT_TENANT_ID=your-tenant-id   # or "common" to allow any tenant
```

The **first user** who signs in becomes the administrator. No Microsoft 365? Set `REGISTRATION_ENABLED=true` to allow email/password sign-up instead.

### Mail

Respondent verification codes and owner notifications are sent by email. Configure the `MAIL_*` variables in `.env` (any SMTP provider works).

## GDPR notes

- The consent checkbox is **always required** before a response is submitted; the consent timestamp is stored with the response.
- The terms of use and privacy policy pages are editable from the admin panel (Markdown, French and English). Default templates are seeded — **replace the `[placeholders]` with your organization's details**.
- Each form displays its retention period to respondents. After that period, responses and uploaded documents are deleted automatically by the daily purge.
- Deleting a response, a form or a user also deletes every associated uploaded file.
- Uploaded files are stored on the private local disk and served only to the form owner and administrators, never publicly.

## Testing

```bash
php artisan test
```

## Contributing

Issues and pull requests are welcome! Please run `php artisan test`, `npm run lint` and `npm run format` before submitting.

## License

[MIT](LICENSE)
