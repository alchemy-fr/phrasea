import io
import os
import threading

import open_clip
import torch
from fastapi import FastAPI, UploadFile, HTTPException
from PIL import Image
from pydantic import BaseModel

MODEL_NAME = os.environ.get("CLIP_MODEL", "ViT-B-32")
PRETRAINED = os.environ.get("CLIP_PRETRAINED", "laion2b_s34b_b79k")

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
    data = await file.read()
    try:
        image = Image.open(io.BytesIO(data)).convert("RGB")
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

    tokens = tokenizer([input.text])
    with lock, torch.no_grad():
        features = model.encode_text(tokens)

    return to_response(features)
