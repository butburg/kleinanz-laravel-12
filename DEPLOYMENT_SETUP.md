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

```bash
# 1. Generate key locally
ssh-keygen -t rsa -b 4096 -f ~/.ssh/deploy_kleinanz
# (no passphrase)

# 2. Add public key to Lima server (via hosting web console)
cat ~/.ssh/deploy_kleinanz.pub
# Paste into ~/.ssh/authorized_keys on Lima

# 3. Add private key to GitHub (Secrets → LIMA_SSH_PRIVATE_KEY)
cat ~/.ssh/deploy_kleinanz
# Paste entire content including BEGIN/END lines
```

---

## Python / Auto-Crop

Lima has Python 3.12 available. The workflow automatically creates a `.venv` inside `~/kleinanz/` and installs `numpy`, `pillow`, `onnxruntime` on every deploy (fast if already installed). No manual setup needed.
