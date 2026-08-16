# Deployment Setup - Kleinanz Laravel 12

## GitHub Secrets & Variables

Go to: **GitHub → Settings → Secrets and variables → Actions**

### Secrets tab
| Name | Value |
|------|-------|
| `LIMA_SFTP_HOST` | Lima server hostname |
| `LIMA_SFTP_USERNAME` | Lima SSH username |
| `LIMA_SSH_PRIVATE_KEY` | Contents of `~/.ssh/deploy_kleinanz` (private key, multi-line) |
| `KLEINANZ_ENV_VARIABLES` | Contents of `larasvelte/.env.production` (multi-line production overrides) |

> The workflow starts from the committed `larasvelte/.env.example`, then replaces only the keys listed in `KLEINANZ_ENV_VARIABLES`. This keeps shared defaults in one place and prevents the production secret from duplicating the whole file.
> Every override key must already exist in `.env.example`; deployment fails for an unknown key.

Copy `larasvelte/.env.production` into the existing `KLEINANZ_ENV_VARIABLES` GitHub Actions secret. Replace its placeholders with the hoster's database and SMTP credentials before copying it. Keep production-only secrets such as `APP_KEY` in this secret; do not add them to `.env.example`.

## Mail Delivery

The local `.env` uses `MAIL_MAILER=log`, which is useful for development because verification and password-reset messages are written to `larasvelte/storage/logs/laravel.log` instead of being sent.

For the hoster, create a mailbox such as `noreply@yourdomain.example` and copy the SMTP values from the hoster's mail settings into the production `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.your-hoster.example
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.example
MAIL_PASSWORD=the-mailbox-password
MAIL_EHLO_DOMAIN=yourdomain.example
MAIL_FROM_ADDRESS=noreply@yourdomain.example
MAIL_FROM_NAME="Weedy Universe Classifieds"
```

Use `MAIL_PORT=465` with `MAIL_SCHEME=smtps` if the hoster specifies implicit TLS instead of STARTTLS on port 587. Do not use the mailbox password as the application's password, and never commit the production `.env` or SMTP credentials. After changing mail settings, run `php artisan config:clear` and test both registration verification and password reset. The hoster must also allow outbound SMTP and the sender domain should have its SPF/DKIM records configured.

The scheduler must run every minute in production so unverified accounts can be removed after 24 hours:

```cron
* * * * * cd /path/to/larasvelte && php artisan schedule:run >> /dev/null 2>&1
```

---

## SSH Key Setup (one-time)

> ⚠️ Must use `-m PEM` flag — default OpenSSH format causes "error in libcrypto" in GitHub Actions.

```bash
# 1. Generate key in PEM format (on local WSL machine)
ssh-keygen -t rsa -b 4096 -m PEM -f ~/.ssh/deploy_kleinanz
# Press Enter twice (no passphrase)
# Key must start with: -----BEGIN RSA PRIVATE KEY-----
# NOT:                 -----BEGIN OPENSSH PRIVATE KEY-----
```

```bash
# 2. Copy public key → add to Lima server (via hosting web console)
cat ~/.ssh/deploy_kleinanz.pub
# Paste as a new line in ~/.ssh/authorized_keys on Lima
```

```bash
# 3. Copy private key → add to GitHub secret LIMA_SSH_PRIVATE_KEY
cat ~/.ssh/deploy_kleinanz
# Paste entire content including BEGIN/END lines
```

**If regenerating** (e.g. fixing the format error):
```bash
rm ~/.ssh/deploy_kleinanz ~/.ssh/deploy_kleinanz.pub
# Then repeat steps 1–3 above, replacing old key in Lima and GitHub
```

---

## Python / Auto-Crop

Lima has Python 3.12 available, but `venv` is not usable on this host. The workflow therefore installs `numpy`, `pillow`, and `onnxruntime` into `~/kleinanz/.python-packages` via `python3 -m pip --target ...` and reuses that directory on later deploys.
