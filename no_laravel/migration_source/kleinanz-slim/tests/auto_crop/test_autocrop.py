"""
Test script for auto-crop functionality with detailed logging.
"""

import io
from PIL import Image, ImageDraw
import sys
from pathlib import Path

# Add app directory to path so imports work
repo_root = Path(__file__).resolve().parents[2]
app_dir = repo_root / "app"
sys.path.insert(0, str(app_dir))


# Create a test image with a simple "clothing" shape

def create_test_image():
    """Create a 800x600 test image with a rectangle in center (simulating clothing)."""
    img = Image.new('RGB', (800, 600), color='white')
    draw = ImageDraw.Draw(img)

    # Draw a "clothing item" - rectangle in center
    draw.rectangle([(250, 150), (550, 500)],
                   fill='blue', outline='black', width=3)

    # Add some "background" noise
    draw.rectangle([(0, 0), (100, 100)], fill='gray')
    draw.rectangle([(700, 500), (800, 600)], fill='gray')

    print(f"✓ Created test image: {img.size}")
    return img


def test_auto_crop():
    """Test auto-crop with detailed logging."""
    print("\n" + "="*60)
    print("AUTO-CROP TEST")
    print("="*60 + "\n")

    # Check imports
    print("1. Testing imports...")
    try:
        from services.auto_crop import auto_crop_if_needed
        from config import (
            AUTO_CROP_DETECTION_THRESHOLD,
            AUTO_CROP_CLOSEUP_THRESHOLD,
            AUTO_CROP_MARGIN_PERCENT,
            AUTO_CROP_MODEL
        )
        print("   ✓ Imports successful")
    except Exception as e:
        print(f"   ✗ Import failed: {e}")
        import traceback
        traceback.print_exc()
        return

    # Check config
    print("\n2. Configuration:")
    print(f"   Auto-crop: ALWAYS ENABLED (mandatory)")
    print(f"   DETECTION_THRESHOLD: {AUTO_CROP_DETECTION_THRESHOLD}")
    print(f"   CLOSEUP_THRESHOLD: {AUTO_CROP_CLOSEUP_THRESHOLD}")
    print(f"   MARGIN_PERCENT: {AUTO_CROP_MARGIN_PERCENT}")
    print(f"   MODEL: {AUTO_CROP_MODEL}")

    # Create test image
    print("\n3. Loading test image...")
    img_path = Path(__file__).parent / "test_person.jpg"
    if not img_path.exists():
        print(f"   ✗ Error: {img_path} not found!")
        return

    img = Image.open(img_path)
    print(f"   ✓ Loaded: {img_path}")
    print(f"   Size: {img.size}")
    print(f"   Mode: {img.mode}")

    # Save original
    original_path = Path(__file__).parent / "test_original.jpg"
    img.save(original_path)
    print(f"   ✓ Saved original: {original_path}")

    # Test auto-crop
    print("\n4. Running auto-crop...")
    print("   (Using ONNX model - pure Python inference)")
    try:
        cropped_img, was_cropped = auto_crop_if_needed(img)
        print(f"   ✓ Auto-crop completed")
        print(f"   Was cropped: {was_cropped}")
        print(f"   Original size: {img.size}")
        print(f"   Result size: {cropped_img.size}")

        # Save result
        result_path = Path(__file__).parent / "test_cropped.jpg"
        cropped_img.save(result_path)
        print(f"   ✓ Saved result: {result_path}")

    except Exception as e:
        print(f"   ✗ Auto-crop failed: {e}")
        import traceback
        traceback.print_exc()
        return

    print("\n" + "="*60)
    print("TEST COMPLETE")
    print("="*60)
    print("\nCheck the generated files:")
    print(f"  - {original_path} (original)")
    print(f"  - {result_path} (after auto-crop)")
    print()


if __name__ == "__main__":
    test_auto_crop()
