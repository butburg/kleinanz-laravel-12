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


def bbox_iou(box_a: tuple, box_b: tuple) -> float:
    """Computes IoU between two boxes in xyxy format."""
    ax1, ay1, ax2, ay2 = box_a
    bx1, by1, bx2, by2 = box_b

    inter_x1 = max(ax1, bx1)
    inter_y1 = max(ay1, by1)
    inter_x2 = min(ax2, bx2)
    inter_y2 = min(ay2, by2)

    inter_w = max(0, inter_x2 - inter_x1)
    inter_h = max(0, inter_y2 - inter_y1)
    inter_area = inter_w * inter_h
    if inter_area == 0:
        return 0.0

    area_a = max(0, ax2 - ax1) * max(0, ay2 - ay1)
    area_b = max(0, bx2 - bx1) * max(0, by2 - by1)
    denom = area_a + area_b - inter_area
    if denom <= 0:
        return 0.0

    return inter_area / denom


def non_max_suppression(
    detections: list[ClothingDetection],
    iou_threshold: float = 0.5,
) -> list[ClothingDetection]:
    """Applies class-aware NMS, keeping the highest-confidence boxes."""
    if not detections:
        return []

    grouped: dict[str, list[ClothingDetection]] = {}
    for det in detections:
        grouped.setdefault(det.category, []).append(det)

    kept: list[ClothingDetection] = []
    for _, class_detections in grouped.items():
        candidates = sorted(class_detections, key=lambda d: d.confidence, reverse=True)
        class_kept: list[ClothingDetection] = []

        for candidate in candidates:
            if all(bbox_iou(candidate.bbox, existing.bbox) < iou_threshold for existing in class_kept):
                class_kept.append(candidate)

        kept.extend(class_kept)

    return kept


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

        raw_output = outputs[0]
        if raw_output.ndim == 3:
            raw_output = raw_output[0]

        if raw_output.ndim != 2:
            raise AutoCropError(f"Unsupported output tensor rank: {raw_output.ndim}")

        # Common exported formats:
        # 1) [num_detections, 6] -> [x, y, w, h, conf, cls]
        # 2) [channels, anchors] where channels = 4 + num_classes (YOLOv8 ONNX default)
        if raw_output.shape[1] == 6:
            predictions = raw_output
        elif raw_output.shape[0] >= 6 and raw_output.shape[1] > raw_output.shape[0]:
            transposed = raw_output.T  # [anchors, channels]
            box_xywh = transposed[:, :4]
            class_scores = transposed[:, 4:]

            if class_scores.size == 0:
                return []

            class_ids = np.argmax(class_scores, axis=1)
            confidences = class_scores[np.arange(class_scores.shape[0]), class_ids]
            predictions = np.concatenate(
                [box_xywh, confidences[:, None], class_ids.astype(np.float32)[:, None]],
                axis=1,
            )
        else:
            raise AutoCropError(
                f"Unsupported output tensor shape: {raw_output.shape}. "
                "Expected [N,6] or [C,A] with C>=6."
            )

        detections = []
        if predictions.size > 0:
            coord_max = float(np.max(predictions[:, :4]))
            coords_are_normalized = coord_max <= 2.5

            for pred in predictions:
                confidence = float(pred[4])
                if confidence < detection_threshold:
                    continue

                x_center, y_center, width, height = pred[:4]
                if coords_are_normalized:
                    x_center *= image.width
                    y_center *= image.height
                    width *= image.width
                    height *= image.height
                else:
                    # Exported detect ONNX models typically emit xywh in 640-space.
                    x_center *= image.width / 640
                    y_center *= image.height / 640
                    width *= image.width / 640
                    height *= image.height / 640

                x_min = int(max(0, x_center - width / 2))
                y_min = int(max(0, y_center - height / 2))
                x_max = int(min(image.width, x_center + width / 2))
                y_max = int(min(image.height, y_center + height / 2))

                if x_max <= x_min or y_max <= y_min:
                    continue

                area = (x_max - x_min) * (y_max - y_min)
                category = f"clothing_{int(pred[5])}"

                detections.append(ClothingDetection(
                    category=category,
                    confidence=confidence,
                    bbox=(x_min, y_min, x_max, y_max),
                    area=area,
                ))

        # Remove duplicate overlaps and prioritize larger detections first.
        detections = non_max_suppression(detections, iou_threshold=0.5)
        detections.sort(key=lambda d: (d.area, d.confidence), reverse=True)
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
) -> tuple[Image.Image, bool, dict]:
    """
    Main auto-crop function.

    Returns:
        (processed_image, was_cropped, diagnostics)
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
            return image, False, {
                'detection_count': 0,
                'main_confidence': None,
                'main_coverage': None,
                'decision_reason': 'no_detection',
            }

        main_item = detections[0]
        image_area = image.width * image.height
        coverage = main_item.area / image_area

        diagnostics = {
            'detection_count': len(detections),
            'main_confidence': float(main_item.confidence),
            'main_coverage': float(coverage),
            'decision_reason': None,
        }

        # Check if should crop
        if not should_crop(image, detections, closeup_threshold=closeup_threshold):
            diagnostics['decision_reason'] = 'already_closeup'

            return image, False, diagnostics

        # Crop
        cropped = crop_to_main_item(
            image,
            detections,
            margin_percent=margin_percent,
        )

        diagnostics['decision_reason'] = 'cropped'

        return cropped, True, diagnostics

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
        'detection_count': None,
        'main_confidence': None,
        'main_coverage': None,
        'decision_reason': None,
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

        cropped, was_cropped, diagnostics = auto_crop_if_needed(
            image_path,
            model_path,
            detection_threshold=args.detection_threshold,
            closeup_threshold=args.closeup_threshold,
            margin_percent=args.margin_percent,
        )

        result['was_cropped'] = was_cropped
        result['cropped_size'] = list(cropped.size)
        result['detection_count'] = diagnostics.get('detection_count')
        result['main_confidence'] = diagnostics.get('main_confidence')
        result['main_coverage'] = diagnostics.get('main_coverage')
        result['decision_reason'] = diagnostics.get('decision_reason')

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
