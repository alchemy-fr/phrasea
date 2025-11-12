---
title: Intro
---

# Uploader

The Uploader service is a core component of the Alchemy ecosystem, designed to securely handle and process assets uploaded by authenticated users. It acts as a bridge between users and downstream services, ensuring that uploaded files are efficiently managed and made available for further processing or storage.

## Key Responsibilities

- Accept file uploads from authenticated users.
- Validate and temporarily store uploaded assets.
- Notify or trigger other services (such as Phraseanet) to fetch and process the uploaded files.
- Support both push and pull workflows for asset delivery.

## Main Components

- **Upload API (backend):** Handles file upload requests, validation, storage, and communication with other services.
- **Uploader client (frontend):** Provides a user-friendly interface for uploading files, filling out metadata forms, and tracking upload progress.

## Typical Workflow

1. A user authenticates and uploads one or more files via the uploader client.
2. The backend API validates and stores the files temporarily.
3. Depending on configuration, the uploader either notifies Phraseanet (push mode) or waits for Phraseanet to fetch new uploads (pull mode).
4. Phraseanet or another service retrieves the files for further processing, cataloging, or storage.

## Integration

The uploader is designed to be flexible and can be integrated with various backend systems. It supports custom form schemas, bulk metadata, and can be tailored to different asset management workflows.
