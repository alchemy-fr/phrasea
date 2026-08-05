import io
import os
import threading

import open_clip
import torch
from fastapi import FastAPI, Request, UploadFile, HTTPException
from fastapi.responses import JSONResponse
from PIL import Image
from pydantic import BaseModel

MODEL_NAME = os.environ.get("CLIP_MODEL", "ViT-B-32")
PRETRAINED = os.environ.get("CLIP_PRETRAINED", "laion2b_s34b_b79k")
MAX_UPLOAD_BYTES = int(os.environ.get("MAX_UPLOAD_BYTES", str(2 * 1024 * 1024)))
MAX_TEXT_LENGTH = int(os.environ.get("MAX_TEXT_LENGTH", str(10_000)))

# Reject decompression bombs (Pillow raises DecompressionBombError above this)
Image.MAX_IMAGE_PIXELS = int(os.environ.get("MAX_IMAGE_PIXELS", str(5_000_000)))

app = FastAPI(title="Phrasea Similarity Embedder")

model, _, preprocess = open_clip.create_model_and_transforms(
    MODEL_NAME, pretrained=PRETRAINED
)
model.eval()
tokenizer = open_clip.get_tokenizer(MODEL_NAME)
dim = model.visual.output_dim

# torch inference is not thread-safe on a shared model
lock = threading.Lock()


class TextInput(BaseModel):
    text: str


@app.middleware("http")
async def limit_body_size(request: Request, call_next):
    content_length = request.headers.get("content-length")
    if content_length is not None:
        try:
            declared = int(content_length)
        except ValueError:
            return JSONResponse(status_code=400, content={"detail": "Invalid Content-Length"})
        if declared > MAX_UPLOAD_BYTES:
            return JSONResponse(
                status_code=413,
                content={"detail": f"Request body exceeds {MAX_UPLOAD_BYTES} bytes"},
            )

    return await call_next(request)


def to_response(features: torch.Tensor) -> dict:
    features = features / features.norm(dim=-1, keepdim=True)
    return {
        "vector": features.squeeze(0).tolist(),
        "model": f"{MODEL_NAME}/{PRETRAINED}",
        "dim": dim,
    }


@app.get("/healthz")
def healthz() -> dict:
    return {"status": "ok", "model": f"{MODEL_NAME}/{PRETRAINED}", "dim": dim}


@app.post("/embed")
async def embed(file: UploadFile) -> dict:
    # Content-Length may be absent (chunked encoding): also cap while reading
    data = bytearray()
    while chunk := await file.read(1024 * 1024):
        data.extend(chunk)
        if len(data) > MAX_UPLOAD_BYTES:
            raise HTTPException(
                status_code=413,
                detail=f"File exceeds {MAX_UPLOAD_BYTES} bytes",
            )

    try:
        image = Image.open(io.BytesIO(bytes(data)))
        # Image.open only reads the header: check dimensions before decoding pixels
        if image.width * image.height > Image.MAX_IMAGE_PIXELS:
            raise HTTPException(status_code=413, detail="Image has too many pixels")
        image = image.convert("RGB")
    except HTTPException:
        raise
    except Image.DecompressionBombError:
        raise HTTPException(status_code=413, detail="Image has too many pixels")
    except Exception:
        raise HTTPException(status_code=422, detail="Unsupported or corrupted image")

    tensor = preprocess(image).unsqueeze(0)
    with lock, torch.no_grad():
        features = model.encode_image(tensor)

    return to_response(features)


@app.post("/embed-text")
def embed_text(input: TextInput) -> dict:
    if not input.text.strip():
        raise HTTPException(status_code=422, detail="Empty text")
    if len(input.text) > MAX_TEXT_LENGTH:
        raise HTTPException(
            status_code=422,
            detail=f"Text exceeds {MAX_TEXT_LENGTH} characters",
        )

    tokens = tokenizer([input.text])
    with lock, torch.no_grad():
        features = model.encode_text(tokens)

    return to_response(features)
