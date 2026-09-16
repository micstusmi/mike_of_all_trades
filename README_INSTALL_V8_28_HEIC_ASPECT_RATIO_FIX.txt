V8.28 - PRESERVE HEIC PHOTO ASPECT RATIO
=========================================

ROOT CAUSE

The Imagick HEIC conversion called thumbnailImage(..., true, true). Imagick's
fourth argument means "fill the requested dimensions". It forced portrait and
landscape iPhone photos onto a square 1600 x 1600 white canvas before the social
cropper ever received them.

The label and wireframe logo were consequently inside the JPEG canvas but could
appear outside the visible photograph because the white padding was already part
of the stored source.

FIX

- Auto-orient the HEIC image before resizing.
- Preserve its real aspect ratio.
- Never add a square white fill canvas.
- The later social crop can now fill its exact output dimensions, with the label
  top-centred and the logo bottom-right inside those same dimensions.

This corrects new HEIC/HEIF uploads. Old test images whose stored source was
already converted onto a white square cannot be restored without re-uploading
the original iPhone photo.
