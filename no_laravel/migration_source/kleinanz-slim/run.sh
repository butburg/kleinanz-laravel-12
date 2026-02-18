#!/bin/bash
# Run Streamlit with proper environment configuration

export HEADLESS=1
export OPENBLAS_NUM_THREADS=1
export OMP_NUM_THREADS=1
export LIBGL_ALWAYS_INDIRECT=1

# For ultralytics/opencv to work without full OpenGL
export LD_LIBRARY_PATH=/usr/lib/x86_64-linux-gnu:$LD_LIBRARY_PATH

cd "$(dirname "$0")"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🚀 Starting kleinanz-slim with Streamlit..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

pipenv run streamlit run app/main.py
