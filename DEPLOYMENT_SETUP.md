# Deployment Setup - Kleinanz Laravel 12

## GitHub Secrets & Variables

Go to: **GitHub → Settings → Secrets and variables → Actions**

### Secrets tab
| Name | Value |
|------|-------|
| `LIMA_SFTP_HOST` | Lima server hostname |
| `LIMA_SFTP_USERNAME` | Lima SSH username |
| `LIMA_SSH_PRIVATE_KEY` | Contents of `~/.ssh/deploy_kleinanz` (private key, multi-line) |
| `KLEINANZ_ENV_VARIABLES` | Contents of your local `larasvelte/.env` (multi-line) |

> `.env` is gitignored so it can't be read from the repo in CI — it must be stored as a secret.
> The workflow writes it to the server and automatically patches `APP_ENV` → `production`,
> `APP_URL` → `PRODUCTION_URL`, `PYTHON_PATH=python3`, and `PYTHON_PACKAGES_PATH=.python-packages`.

### Variables tab
| Name | Value |
|------|-------|
| `PRODUCTION_URL` | `https://yourdomain.com/kleinanz/public` |

The workflow uses `.env` from the repo as-is (with all DB credentials, OpenAI key etc.) and only overwrites `APP_ENV` → `production` and `APP_URL` → `PRODUCTION_URL`.

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
