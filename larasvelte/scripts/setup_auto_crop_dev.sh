#!/bin/bash
#
# LOCAL Development Setup for Auto-Crop
# Creates a persistent virtualenv for the Laravel app
#
# Usage:
#   bash scripts/setup_auto_crop_dev.sh
#   source .venv-crop/bin/activate
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
VENV_PATH="$PROJECT_ROOT/.venv-crop"
MODEL_PATH="$PROJECT_ROOT/storage/models/yolov8n-fashionpedia-1.onnx"
REQUIREMENTS_FILE="$SCRIPT_DIR/requirements-auto-crop.txt"

echo "========================================="
echo "🛠️  Auto-Crop Local Development Setup"
echo "========================================="
echo ""

# 1. Check Python
echo "1️⃣  Checking Python version..."
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 not found. Please install Python 3.8+"
    exit 1
fi

PYTHON_VERSION=$(python3 --version)
echo "   ✅ $PYTHON_VERSION"
echo ""

# 2. Create virtualenv if missing
if [[ -d "$VENV_PATH" ]]; then
    echo "2️⃣  Virtualenv already exists at: $VENV_PATH"
    echo ""
else
    echo "2️⃣  Creating virtualenv at: $VENV_PATH"
    python3 -m venv "$VENV_PATH"
    echo "   ✅ Created"
    echo ""
fi

# 3. Activate and upgrade pip
echo "3️⃣  Upgrading pip..."
source "$VENV_PATH/bin/activate"
pip install --upgrade pip --quiet
echo "   ✅ Pip upgraded"
echo ""

# 4. Install packages
echo "4️⃣  Installing required packages..."
if [[ ! -f "$REQUIREMENTS_FILE" ]]; then
    echo "❌ Requirements file missing: $REQUIREMENTS_FILE"
    exit 1
fi
pip install -r "$REQUIREMENTS_FILE" --quiet
echo "   ✅ All packages installed"
echo ""

# 5. Ensure model directory exists and check model file
echo "5️⃣  Checking model path..."
mkdir -p "$PROJECT_ROOT/storage/models"
if [[ -f "$MODEL_PATH" ]]; then
    echo "   ✅ Model found: $MODEL_PATH"
else
    echo "   ⚠️  Model file missing: $MODEL_PATH"
    echo ""
    echo "   Place yolov8n-fashionpedia-1.onnx at:"
    echo "   $MODEL_PATH"
    echo ""
    echo "   Auto-crop will fail until this file exists."
fi
echo ""

# 6. Verify imports
echo "6️⃣  Verifying imports..."
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
echo "  2. Configure local .env defaults:"
echo "     PYTHON_PATH=.venv-crop/bin/python"
echo "     PYTHON_PACKAGES_PATH="
echo "     AUTO_CROP_MODEL_PATH=storage/models/yolov8n-fashionpedia-1.onnx"
echo ""
echo "  3. Run tests:"
echo "     php artisan test"
echo ""
echo "  4. Start development services:"
echo "     Terminal 1: npm run dev"
echo "     Terminal 2: php artisan serve"
echo "     Terminal 3: php artisan queue:work --queue=default -v"
echo ""
echo "  5. Deactivate virtualenv when done:"
echo "     deactivate"
echo ""
