#!/usr/bin/env python3
import os
import sys
import logging
import google.generativeai as genai
from pinecone import Pinecone, exceptions as pinecone_exceptions
from PIL import Image

# Setup logging to stderr
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    stream=sys.stderr
)
logger = logging.getLogger(__name__)

def main():
    if len(sys.argv) != 6:
        logger.error("Incorrect arguments. Expected 5, got %d", len(sys.argv)-1)
        print("ERROR|Invalid arguments")
        sys.exit(1)

    API_KEY_GEMINI = sys.argv[1]
    API_KEY_PINECONE = sys.argv[2]
    INDEX_NAME = sys.argv[3]
    IMAGE_PATH = sys.argv[4]
    PUBLIC_URL = sys.argv[5]

    logger.info("Processing: %s", IMAGE_PATH)

    # Check if image exists
    if not os.path.isfile(IMAGE_PATH):
        logger.error("Image not found: %s", IMAGE_PATH)
        print("ERROR|Image file not found")
        sys.exit(1)

    filename = os.path.basename(IMAGE_PATH)

    try:
        genai.configure(api_key=API_KEY_GEMINI)
        pc = Pinecone(api_key=API_KEY_PINECONE)
        index = pc.Index(INDEX_NAME)
        logger.info("APIs configured")
    except Exception as e:
        logger.exception("API config failed")
        print(f"ERROR|API config: {str(e)}")
        sys.exit(1)

    # Check if vector already exists
    try:
        fetch_response = index.fetch(ids=[filename])
        if filename in fetch_response.get('vectors', {}):
            logger.info("Vector already exists for %s, skipping", filename)
            print(f"SKIPPED|{filename} already exists")
            return
    except pinecone_exceptions.PineconeException as e:
        logger.exception("Pinecone fetch failed")
        print(f"ERROR|Pinecone fetch: {str(e)}")
        sys.exit(1)

    # Load image with PIL
    try:
        image = Image.open(IMAGE_PATH)
        logger.info("Image loaded, size: %s", image.size)
    except Exception as e:
        logger.exception("Failed to load image")
        print(f"ERROR|Image load: {str(e)}")
        sys.exit(1)

    # Generate embedding using the correct multimodal model
    try:
        # Try the most common model name first
        model_name = "models/multimodalembedding@001"
        logger.info("Using model: %s", model_name)
        result = genai.embed_content(
            model=model_name,
            content=image,
            task_type="retrieval_document"
        )
        embedding = result['embedding']
        logger.info("Embedding generated, dimensions: %d", len(embedding))
    except Exception as e:
        # Fallback: try without "models/" prefix (some API versions require this)
        logger.warning("First attempt failed, trying alternative model name")
        try:
            model_name = "multimodalembedding@001"
            result = genai.embed_content(
                model=model_name,
                content=image,
                task_type="retrieval_document"
            )
            embedding = result['embedding']
        except Exception as e2:
            logger.exception("Both embedding attempts failed")
            print(f"ERROR|Embedding failed: {str(e)} | {str(e2)}")
            sys.exit(1)

    # Upsert to Pinecone
    try:
        upsert_response = index.upsert(vectors=[{
            "id": filename,
            "values": embedding,
            "metadata": {
                "url": PUBLIC_URL,
                "path": IMAGE_PATH,
                "filename": filename
            }
        }])
        logger.info("Upsert response: %s", upsert_response)
        print(f"SUCCESS|{filename} indexed")
    except pinecone_exceptions.PineconeException as e:
        logger.exception("Pinecone upsert failed")
        print(f"ERROR|Pinecone upsert: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()