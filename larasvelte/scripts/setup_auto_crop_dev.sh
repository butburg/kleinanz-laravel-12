#!/bin/bash
#
# LOCAL Development Setup for Auto-Crop
# Creates a persistent virtualenv for the Laravel app
#
# Usage:
#   bash scripts/setup_auto_crop_dev.sh
#   source venv/bin/activate
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
VENV_PATH="$PROJECT_ROOT/venv"

echo "========================================="
echo "🛠️  Auto-Crop Local Development Setup"
echo "========================================="
echo ""

# Check if virtualenv already exists
if [[ -d "$VENV_PATH" ]]; then
    echo "✅ Virtualenv already exists at: $VENV_PATH"
    echo ""
    echo "To activate, run:"
    echo "  source $VENV_PATH/bin/activate"
    echo ""
    echo "To recreate, run:"
    echo "  rm -rf $VENV_PATH"
    echo "  bash scripts/setup_auto_crop_dev.sh"
    exit 0
fi

# 1. Check Python
echo "1️⃣  Checking Python version..."
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 not found. Please install Python 3.8+"
    exit 1
fi

PYTHON_VERSION=$(python3 --version)
echo "   ✅ $PYTHON_VERSION"
echo ""

# 2. Create virtualenv
echo "2️⃣  Creating virtualenv at: $VENV_PATH"
python3 -m venv "$VENV_PATH"
echo "   ✅ Created"
echo ""

# 3. Activate and upgrade pip
echo "3️⃣  Upgrading pip..."
source "$VENV_PATH/bin/activate"
pip install --upgrade pip --quiet
echo "   ✅ Pip upgraded"
echo ""

# 4. Install packages
echo "4️⃣  Installing required packages..."
echo "   - onnxruntime"
echo "   - pillow"
echo "   - numpy"
pip install onnxruntime pillow numpy --quiet
echo "   ✅ All packages installed"
echo ""

# 5. Verify imports
echo "5️⃣  Verifying imports..."
python3 << 'EOF'
import onnxruntime as ort
import PIL
import numpy as np
print(f"   ✅ onnxruntime: {ort.__version__}")
print(f"   ✅ PIL/Pillow: {PIL.__version__}")
print(f"   ✅ numpy: {np.__version__}")
EOF
echo ""

deactivate

echo "========================================="
echo "✅ Setup complete!"
echo "========================================="
echo ""
echo "📋 Next steps:"
echo ""
echo "  1. Activate the virtualenv:"
echo "     source $VENV_PATH/bin/activate"
echo ""
echo "  2. Run tests:"
echo "     php artisan test"
echo ""
echo "  3. Watch both PHP + Python auto-reload:"
echo "     Terminal 1: npm run dev"
echo "     Terminal 2: php artisan serve"
echo "     Terminal 3: php artisan queue:work"
echo ""
echo "  4. Deactivate virtualenv when done:"
echo "     deactivate"
echo ""
