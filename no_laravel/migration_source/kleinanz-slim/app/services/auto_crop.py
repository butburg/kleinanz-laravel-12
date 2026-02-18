"""
Auto-crop service for clothing products.

Detects clothing items (shirt, pants, dress, etc.) and crops to the main product,
cutting away faces, background, and non-product areas.

Uses ONNX Runtime with a fashion-specific YOLOv8 model (pure Python, works on Streamlit Cloud).
"""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from PIL import Image
import numpy as np

from config import (
    AUTO_CROP_DETECTION_THRESHOLD,
    AUTO_CROP_CLOSEUP_THRESHOLD,
    AUTO_CROP_MARGIN_PERCENT,
    AUTO_CROP_MODEL,
)
from utils import log_event

# Try to import ONNX Runtime; if missing, auto-crop will gracefully fail
try:
    import onnxruntime as ort
    ONNX_AVAILABLE = True
except ImportError:
    ONNX_AVAILABLE = False


@dataclass
class ClothingDetection:
    """Represents a detected clothing item."""

    category: str  # e.g., 'shirt', 'pants', 'dress'
    confidence: float  # 0.0 - 1.0
    bbox: tuple[int, int, int, int]  # (x_min, y_min, x_max, y_max)
    area: int  # bbox area in pixels


class AutoCropError(Exception):
    """Raised when auto-crop fails."""


def auto_crop_if_needed(
    image: Image.Image
) -> tuple[Image.Image, bool]:
    """
    Auto-crops image to main clothing item.

    Args:
        image: PIL Image to process

    Returns:
        tuple: (processed_image, was_cropped)
            - processed_image: Cropped image or original if no crop needed
            - was_cropped: True if image was cropped, False otherwise
    """
    try:
        # Detect clothing items
        detections = detect_clothing_items(image)

        if not detections:
            log_event("[auto_crop] No clothing detected, keeping original")
            return image, False

        # Check if already a close-up
        if not should_crop(image, detections):
            log_event("[auto_crop] Already close-up, skipping crop")
            return image, False

        # Crop to main item
        cropped = crop_to_main_item(image, detections)
        log_event(
            f"[auto_crop] Cropped from {image.size} to {cropped.size} "
            f"({len(detections)} items detected)"
        )
        return cropped, True

    except Exception as exc:
        log_event(f"[auto_crop] Error during auto-crop: {exc}")
        # On error, return original image (fail-safe)
        return image, False


def detect_clothing_items(image: Image.Image) -> list[ClothingDetection]:
    """
    Detects clothing items in the image using ONNX Runtime (pure Python).

    Args:
        image: PIL Image to analyze

    Returns:
        List of ClothingDetection objects, sorted by area (largest first)

    Raises:
        AutoCropError: If ONNX model fails to load or predict
    """
    if not ONNX_AVAILABLE:
        raise AutoCropError(
            "onnxruntime package not installed. Run: pip install onnxruntime"
        )

    try:
        model_path = Path(str(AUTO_CROP_MODEL))
        if not model_path.is_file():
            raise AutoCropError(
                f"ONNX model not found at: {model_path}"
            )

        log_event(f"[auto_crop] Using ONNX model: {model_path}")

        # Load ONNX model session
        session = ort.InferenceSession(
            str(model_path),
            providers=['CPUExecutionProvider']
        )

        # Prepare image for inference
        input_size = 640
        image_resized = image.resize(
            (input_size, input_size), Image.Resampling.BILINEAR)

        # Convert to numpy array and normalize
        image_np = np.array(image_resized).astype(np.float32)

        # BGR format if needed (YOLO typically works with RGB)
        # Normalize to [0, 1] range
        image_np = image_np / 255.0

        # Add batch dimension and convert to NCHW format
        image_np = np.transpose(image_np, (2, 0, 1))  # HWC -> CHW
        image_np = np.expand_dims(image_np, 0)  # Add batch dimension

        # Run inference
        input_name = session.get_inputs()[0].name
        output_name = session.get_outputs()[0].name

        output = session.run([output_name], {input_name: image_np})[0]

        # Output shape: (1, N, 8400) where N = 4 (bbox) + num_classes
        # output[0, 0:4, i] = [x, y, w, h] for anchor i
        # output[0, 4:, i] = class scores (no separate objectness)
        output = np.squeeze(output, 0)  # Shape: (N, 8400)

        detections = []
        original_width, original_height = image.size

        # Process each anchor point
        for i in range(output.shape[1]):  # Iterate through 8400 anchors
            # Extract bbox coordinates
            x = float(output[0, i])
            y = float(output[1, i])
            w = float(output[2, i])
            h = float(output[3, i])

            # Get max class score as confidence (no separate objectness in YOLOv8)
            class_scores = output[4:, i]
            conf = float(np.max(class_scores))

            # Filter by confidence threshold
            if conf < AUTO_CROP_DETECTION_THRESHOLD:
                continue

            # x, y, w, h are in 640x640 space, need to convert to original image space
            x_min = int((x - w / 2) * original_width / input_size)
            y_min = int((y - h / 2) * original_height / input_size)
            x_max = int((x + w / 2) * original_width / input_size)
            y_max = int((y + h / 2) * original_height / input_size)

            # Clamp to image bounds
            x_min = max(0, min(x_min, original_width - 1))
            y_min = max(0, min(y_min, original_height - 1))
            x_max = max(x_min + 1, min(x_max, original_width))
            y_max = max(y_min + 1, min(y_max, original_height))

            area = (x_max - x_min) * (y_max - y_min)
            if area < 100:  # Skip tiny detections
                continue

            detection_obj = ClothingDetection(
                category="clothing",
                confidence=conf,
                bbox=(x_min, y_min, x_max, y_max),
                area=area,
            )
            detections.append(detection_obj)

        # Sort by area (largest first)
        detections.sort(key=lambda d: d.area, reverse=True)
        return detections

    except Exception as exc:
        raise AutoCropError(f"ONNX detection failed: {exc}") from exc


def should_crop(image: Image.Image, detections: list[ClothingDetection]) -> bool:
    """
    Determines if image needs cropping or is already a close-up.

    Args:
        image: PIL Image
        detections: List of detected clothing items

    Returns:
        True if image should be cropped, False if already close-up

    Logic:
        If the main (largest) detection covers more than CLOSEUP_THRESHOLD
        (default 70%) of the image area, it's already a close-up.
    """
    if not detections:
        return False

    image_area = image.size[0] * image.size[1]
    main_detection = detections[0]  # Largest detection

    coverage_ratio = main_detection.area / image_area

    log_event(
        f"[auto_crop] Coverage: {coverage_ratio:.1%} "
        f"(threshold: {AUTO_CROP_CLOSEUP_THRESHOLD:.0%})"
    )

    return coverage_ratio < AUTO_CROP_CLOSEUP_THRESHOLD


def crop_to_main_item(
    image: Image.Image, detections: list[ClothingDetection]
) -> Image.Image:
    """
    Crops image to the main (largest) clothing item with margin.

    Args:
        image: PIL Image to crop
        detections: List of detected clothing items (assumed sorted by area)

    Returns:
        Cropped PIL Image

    Logic:
        - Selects the largest detection (main item)
        - Adds configurable margin around bbox
        - Ensures bbox stays within image boundaries
    """
    main_item = select_main_clothing(detections)

    x_min, y_min, x_max, y_max = main_item.bbox
    width, height = image.size

    # Calculate margin
    bbox_width = x_max - x_min
    bbox_height = y_max - y_min
    margin_x = int(bbox_width * AUTO_CROP_MARGIN_PERCENT / 100)
    margin_y = int(bbox_height * AUTO_CROP_MARGIN_PERCENT / 100)

    # Apply margin and clamp to image bounds
    x_min = max(0, x_min - margin_x)
    y_min = max(0, y_min - margin_y)
    x_max = min(width, x_max + margin_x)
    y_max = min(height, y_max + margin_y)

    # Crop and return
    return image.crop((x_min, y_min, x_max, y_max))


def select_main_clothing(detections: list[ClothingDetection]) -> ClothingDetection:
    """
    Selects the main clothing item from detections.

    Args:
        detections: List of detected clothing items, sorted by area (largest first)

    Returns:
        The main ClothingDetection (largest item)

    Logic:
        Simply returns the largest detected item - we don't need category filtering
        since we're cropping to one product anyway.
    """
    if not detections:
        raise AutoCropError("No detections to select from")

    # Return the largest detected item (already sorted by area)
    return detections[0]
