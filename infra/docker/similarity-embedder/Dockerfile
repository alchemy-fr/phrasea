FROM python:3.12-slim

WORKDIR /srv/app

COPY requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

ENV CLIP_MODEL=ViT-B-32 \
    CLIP_PRETRAINED=laion2b_s34b_b79k \
    HF_HOME=/srv/cache

RUN python -c "import open_clip, os; open_clip.create_model_and_transforms(os.environ['CLIP_MODEL'], pretrained=os.environ['CLIP_PRETRAINED'])"

COPY main.py .

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
