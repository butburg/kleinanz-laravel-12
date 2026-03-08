#!/usr/bin/env python3
"""
Auto-Crop Script for Clothing Detection

Detects clothing items using YOLOv8 and crops to the main product.
Reads image path from command line, outputs JSON result to stdout.

Usage:
  python3 auto_crop.py /path/to/image.jpg
  python3 auto_crop.py /path/to/image.jpg --output /path/to/cropped.jpg

Output (JSON):
  {
    "success": true,
    "was_cropped": true,
    "original_size": [1000, 1200],
    "cropped_size": [850, 950],
    "error": null
  }
"""

import json
import sys
import argparse
from pathlib import Path
from dataclasses import dataclass, asdict

import numpy as np
from PIL import Image

try:
    import onnxruntime as ort
    ONNX_AVAILABLE = True
except ImportError:
    ONNX_AVAILABLE = False


@dataclass
class ClothingDetection:
    """Represents a detected clothing item."""
    category: str
    confidence: float
    bbox: tuple  # (x_min, y_min, x_max, y_max)
    area: int


class AutoCropError(Exception):
    """Raised when auto-crop fails."""
    pass


def detect_clothing_items(
    image: Image.Image,
    model_path: Path,
    detection_threshold: float = 0.7,
) -> list[ClothingDetection]:
    """
    Detects clothing items using ONNX Runtime.

    Args:
        image: PIL Image
        model_path: Path to .onnx model
        detection_threshold: Minimum confidence (0.0 - 1.0)

    Returns:
        List of ClothingDetection objects, sorted by area (largest first)

    Raises:
        AutoCropError: If model fails to load or predict
    """
    if not ONNX_AVAILABLE:
        raise AutoCropError("onnxruntime not installed. Run: pip install onnxruntime")

    if not model_path.exists():
        raise AutoCropError(f"Model file not found: {model_path}")

    try:
        # Prepare image for YOLO (640x640, normalized)
        img_resized = image.convert('RGB').resize((640, 640))
        img_array = np.array(img_resized).astype(np.float32) / 255.0
        img_tensor = np.transpose(img_array, (2, 0, 1))[np.newaxis, ...]

        # Load and run model
        session = ort.InferenceSession(str(model_path), providers=['CPUExecutionProvider'])
        input_name = session.get_inputs()[0].name
        outputs = session.run(None, {input_name: img_tensor})

        # Parse YOLO outputs (typically detection arrays)
        # Output format: [batch, num_detections, 6] where columns are:
        # x_center, y_center, width, height, confidence, class_id
        raw_detections = outputs[0]

        detections = []
        if raw_detections.size > 0:
            predictions = raw_detections[0]  # First batch

            for pred in predictions:
                confidence = float(pred[4])
                if confidence < detection_threshold:
                    continue

                # Convert from normalized to pixel coordinates
                scale_w = image.width / 640
                scale_h = image.height / 640

                x_center, y_center, width, height = pred[:4]
                x_center *= scale_w
                y_center *= scale_h
                width *= scale_w
                height *= scale_h

                x_min = int(max(0, x_center - width / 2))
                y_min = int(max(0, y_center - height / 2))
                x_max = int(min(image.width, x_center + width / 2))
                y_max = int(min(image.height, y_center + height / 2))

                area = (x_max - x_min) * (y_max - y_min)
                category = f"clothing_{int(pred[5])}"

                detections.append(ClothingDetection(
                    category=category,
                    confidence=confidence,
                    bbox=(x_min, y_min, x_max, y_max),
                    area=area,
                ))

        # Sort by area (largest first)
        detections.sort(key=lambda d: d.area, reverse=True)
        return detections

    except Exception as e:
        raise AutoCropError(f"Model inference failed: {e}")


def should_crop(
    image: Image.Image,
    detections: list[ClothingDetection],
    closeup_threshold: float = 0.70,
) -> bool:
    """
    Determines if image should be cropped.

    Don't crop if already a close-up (main item fills >70% of image).

    Args:
        image: PIL Image
        detections: List of detected clothing items
        closeup_threshold: Minimum coverage ratio (0.0 - 1.0)

    Returns:
        True if should crop, False if already close-up
    """
    if not detections:
        return False

    # Main (largest) detection
    main_item = detections[0]
    image_area = image.width * image.height
    coverage = main_item.area / image_area

    return coverage < closeup_threshold


def crop_to_main_item(
    image: Image.Image,
    detections: list[ClothingDetection],
    margin_percent: int = 2,
) -> Image.Image:
    """
    Crops image to main clothing item with margin.

    Args:
        image: PIL Image
        detections: List of detected items (assumed sorted by area)
        margin_percent: Margin around item as percentage of item size

    Returns:
        Cropped Image
    """
    if not detections:
        return image

    x_min, y_min, x_max, y_max = detections[0].bbox
    width = x_max - x_min
    height = y_max - y_min

    # Add margin
    margin_x = max(1, int(width * margin_percent / 100))
    margin_y = max(1, int(height * margin_percent / 100))

    x_min = max(0, x_min - margin_x)
    y_min = max(0, y_min - margin_y)
    x_max = min(image.width, x_max + margin_x)
    y_max = min(image.height, y_max + margin_y)

    return image.crop((x_min, y_min, x_max, y_max))


def auto_crop_if_needed(
    image_path: Path,
    model_path: Path,
    detection_threshold: float = 0.7,
    closeup_threshold: float = 0.70,
    margin_percent: int = 2,
) -> tuple[Image.Image, bool]:
    """
    Main auto-crop function.

    Returns:
        (processed_image, was_cropped)
    """
    try:
        # Load image
        image = Image.open(image_path).convert('RGB')

        # Detect clothing
        detections = detect_clothing_items(
            image,
            model_path,
            detection_threshold=detection_threshold,
        )

        if not detections:
            return image, False

        # Check if should crop
        if not should_crop(image, detections, closeup_threshold=closeup_threshold):
            return image, False

        # Crop
        cropped = crop_to_main_item(
            image,
            detections,
            margin_percent=margin_percent,
        )

        return cropped, True

    except Exception as e:
        raise AutoCropError(f"Auto-crop failed: {e}")


def main():
    parser = argparse.ArgumentParser(description='Auto-crop clothing images')
    parser.add_argument('image_path', help='Path to input image')
    parser.add_argument('--output', help='Path to save cropped image')
    parser.add_argument('--model', help='Path to ONNX model file')
    parser.add_argument('--detection-threshold', type=float, default=0.7)
    parser.add_argument('--closeup-threshold', type=float, default=0.70)
    parser.add_argument('--margin-percent', type=int, default=2)

    args = parser.parse_args()

    image_path = Path(args.image_path)
    model_path = Path(args.model) if args.model else Path(__file__).parent.parent / 'storage' / 'models' / 'yolov8n-fashionpedia-1.onnx'
    output_path = Path(args.output) if args.output else None

    result = {
        'success': False,
        'was_cropped': False,
        'original_size': None,
        'cropped_size': None,
        'error': None,
    }

    try:
        # Validate input
        if not image_path.exists():
            raise AutoCropError(f"Image file not found: {image_path}")

        if not model_path.exists():
            raise AutoCropError(f"Model file not found: {model_path}")

        # Load and process
        image = Image.open(image_path).convert('RGB')
        result['original_size'] = list(image.size)

        cropped, was_cropped = auto_crop_if_needed(
            image_path,
            model_path,
            detection_threshold=args.detection_threshold,
            closeup_threshold=args.closeup_threshold,
            margin_percent=args.margin_percent,
        )

        result['was_cropped'] = was_cropped
        result['cropped_size'] = list(cropped.size)

        # Save if requested
        if output_path:
            output_path.parent.mkdir(parents=True, exist_ok=True)
            cropped.save(output_path, 'JPEG', quality=85, optimize=True)
            result['output_path'] = str(output_path)

        result['success'] = True

    except AutoCropError as e:
        result['success'] = False
        result['error'] = str(e)
    except Exception as e:
        result['success'] = False
        result['error'] = f"Unexpected error: {e}"

    # Output JSON to stdout
    print(json.dumps(result, indent=2))

    # Exit with proper code
    sys.exit(0 if result['success'] else 1)


if __name__ == '__main__':
    main()
