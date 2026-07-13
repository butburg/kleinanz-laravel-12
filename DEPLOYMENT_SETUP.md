# Deployment Setup - Kleinanz Laravel 12

## GitHub Secrets & Variables

Go to: **GitHub → Settings → Secrets and variables → Actions**

### Secrets tab
| Name | Value |
|------|-------|
| `LIMA_SFTP_HOST` | Lima server hostname |
| `LIMA_SFTP_USERNAME` | Lima SSH username |
| `LIMA_SSH_PRIVATE_KEY` | Contents of `~/.ssh/deploy_kleinanz` (private key, multi-line) |

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

Lima has Python 3.12 available. The workflow automatically creates a `.venv` inside `~/kleinanz/` and installs `numpy`, `pillow`, `onnxruntime` on every deploy (fast if already installed). No manual setup needed.
