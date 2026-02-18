#!/bin/bash
set -e

echo "🔧 Setting up kleinanz-slim environment..."
echo ""

# Check Python version
echo "✓ Python version:"
python3 --version
echo ""

# Check pipenv version
echo "✓ Pipenv version:"
pipenv --version
echo ""

# Remove potentially corrupted lock/venv
echo "📦 Cleaning up old environment..."
rm -rf .venv Pipfile.lock 2>/dev/null || true
echo ""

# Lock and install dependencies
echo "📥 Installing dependencies from Pipfile..."
pipenv install --deploy
echo ""

# Verify all packages are installed
echo "✓ Verifying all packages..."
pipenv run python3 << 'EOF'
import sys

packages = {
    'streamlit': 'Streamlit (web framework)',
    'streamlit_authenticator': 'Streamlit Authenticator (auth)',
    'PIL': 'Pillow (image library)',
    'sqlalchemy': 'SQLAlchemy (database ORM)',
    'pydantic': 'Pydantic (data validation)',
    'openai': 'OpenAI (LLM integration)',
    'st_copy': 'st-copy (copy-to-clipboard)',
    'onnxruntime': 'ONNX Runtime (auto-crop inference - pure Python)',
}

failed = []

print("  Required packages:")
for pkg, description in packages.items():
    try:
        __import__(pkg)
        print(f"    ✅ {description}")
    except ImportError as e:
        print(f"    ❌ {description}")
        failed.append((pkg, str(e)))

if failed:
    print(f"\n❌ {len(failed)} required package(s) failed to import:")
    for pkg, err in failed:
        print(f"    - {pkg}: {err}")
    print("\n⛔ Please run: pipenv install again")
    sys.exit(1)

print("\n✅ All required packages verified!")
EOF

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "✨ Setup complete! You can now run the app:"
    echo ""
    echo "   pipenv run streamlit run app/main.py"
    echo ""
    echo "   OR use the wrapper script:"
    echo ""
    echo "   bash run.sh"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
    echo "⛔ Setup failed. Please check errors above."
    exit 1
fi
