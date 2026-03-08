#!/bin/bash
#
# STANDALONE SSH Testing Script for Auto-Crop Python Environment
# Creates temporary virtualenv, tests dependencies, then cleans up
#
# Portable: Works in any directory on Lima City SSH
# Falls back to --user installation if venv not available
#
# Usage on Lima City SSH:
#   scp test_auto_crop_env.sh eweedy@lima-ssh:~/
#   ssh eweedy@lima-ssh 'bash ~/test_auto_crop_env.sh'
#
# Or to keep environment for debugging:
#   bash test_auto_crop_env.sh --keep-env
#

set -e  # Exit on any error

KEEP_ENV=${1:-""}
TEST_ENV_PATH="/tmp/auto-crop-test-venv-$$"  # PID ensures uniqueness
CLEANUP_ON_EXIT=true
USE_VENV=true

cleanup() {
    if [[ "$CLEANUP_ON_EXIT" == "true" ]] && [[ "$USE_VENV" == "true" ]] && [[ -d "$TEST_ENV_PATH" ]]; then
        echo "🧹 Cleaning up temporary environment..."
        rm -rf "$TEST_ENV_PATH"
        echo "✅ Cleaned up: $TEST_ENV_PATH"
    elif [[ "$USE_VENV" == "false" ]]; then
        echo "ℹ️  User installation packages remain in ~/.local (can be cleaned manually)"
    elif [[ "$USE_VENV" == "true" ]]; then
        echo ""
        echo "📁 Test environment preserved at: $TEST_ENV_PATH"
        echo "   Activate with: source $TEST_ENV_PATH/bin/activate"
        echo "   Clean manually: rm -rf '$TEST_ENV_PATH'"
    fi
}

trap cleanup EXIT

# Check if --keep-env flag was passed
if [[ "$KEEP_ENV" == "--keep-env" ]]; then
    CLEANUP_ON_EXIT=false
    echo "⚙️  --keep-env flag: Environment will NOT be deleted after test"
    echo ""
fi

echo "========================================="
echo "🔬 Auto-Crop Environment Test Script"
echo "========================================="
echo ""

# 1. Check Python
echo "1️⃣  Checking Python..."
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 not found"
    exit 1
fi
PYTHON_VERSION=$(python3 --version)
echo "   ✅ $PYTHON_VERSION"
echo ""

# 2. Create temporary virtualenv (or use --user installation as fallback)
echo "2️⃣  Creating Python environment..."

# Try venv first
if python3 -m venv "$TEST_ENV_PATH" 2>/dev/null; then
    echo "   ✅ Created virtualenv at: $TEST_ENV_PATH"
    USE_VENV=true
else
    echo "   ⚠️  venv not available, using --user installation instead"
    USE_VENV=false
    TEST_ENV_PATH="$HOME/.local"  # Use user site-packages
    mkdir -p "$TEST_ENV_PATH"
fi
echo ""

# 3. Activate virtualenv (if venv was used) or set PATH (if --user)
echo "3️⃣  Setting up Python environment..."
if [[ "$USE_VENV" == "true" ]]; then
    source "$TEST_ENV_PATH/bin/activate"
    echo "   ✅ Activated virtualenv ($(which python3))"
else
    # For --user installation, we need to ensure user site-packages are in PATH
    export PYTHONUSERBASE="$HOME/.local"
    export PATH="$HOME/.local/bin:$PATH"
    echo "   ✅ Using --user installation mode"
fi
echo ""

# 4. Upgrade pip
echo "4️⃣  Upgrading pip..."
if [[ "$USE_VENV" == "true" ]]; then
    pip install --upgrade pip --quiet
else
    pip install --user --upgrade pip --quiet
fi
echo "   ✅ Pip upgraded"
echo ""

# 5. Install required packages
echo "5️⃣  Installing required packages..."
echo "   - onnxruntime"
echo "   - pillow"
echo "   - numpy"
PACKAGES=("onnxruntime" "pillow" "numpy")
for pkg in "${PACKAGES[@]}"; do
    if [[ "$USE_VENV" == "true" ]]; then
        pip install "$pkg" --quiet || {
            echo "❌ Failed to install $pkg"
            exit 1
        }
    else
        pip install --user "$pkg" --quiet || {
            echo "❌ Failed to install $pkg (try: pip install --user $pkg)"
            exit 1
        }
    fi
done
echo "   ✅ All packages installed"
echo ""

# 6. Verify imports
echo "6️⃣  Verifying imports..."
python3 << 'EOF'
try:
    import onnxruntime as ort
    print(f"   ✅ onnxruntime: {ort.__version__}")
except ImportError as e:
    print(f"   ❌ onnxruntime: {e}")
    exit(1)

try:
    import PIL
    print(f"   ✅ PIL (Pillow): {PIL.__version__}")
except ImportError as e:
    print(f"   ❌ PIL: {e}")
    exit(1)

try:
    import numpy as np
    print(f"   ✅ numpy: {np.__version__}")
except ImportError as e:
    print(f"   ❌ numpy: {e}")
    exit(1)
EOF
echo ""

# 7. Test subprocess execution
echo "7️⃣  Testing subprocess execution..."
python3 -c "import subprocess; result = subprocess.run(['echo', 'test'], capture_output=True); print('   ✅ Subprocess works')"
echo ""

# 8. Deactivate (if using venv)
if [[ "$USE_VENV" == "true" ]]; then
    deactivate
fi
echo "========================================="
echo "✅ All tests passed!"
echo "========================================="
echo ""
echo "📋 Summary:"
echo "   - Python: $(python3 --version)"
if [[ "$USE_VENV" == "true" ]]; then
    echo "   - Environment: Virtualenv at $TEST_ENV_PATH"
    echo "   - Cleanup: $([ "$CLEANUP_ON_EXIT" == "true" ] && echo "Automatic" || echo "Manual")"
else
    echo "   - Environment: User installation (--user)"
    echo "   - Location: ~/.local"
    echo "   - Packages installed system-wide to user profile"
fi
echo ""
