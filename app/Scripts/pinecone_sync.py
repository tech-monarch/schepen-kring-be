#!/usr/bin/env python3
import os
import sys
import logging
from PIL import Image
from pinecone import Pinecone, exceptions as pinecone_exceptions
from google import genai

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

    # Configure Pinecone
    try:
        pc = Pinecone(api_key=API_KEY_PINECONE)
        index = pc.Index(INDEX_NAME)
        logger.info("Pinecone configured")
    except Exception as e:
        logger.exception("Pinecone config failed")
        print(f"ERROR|Pinecone config: {str(e)}")
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

    # Configure Gemini client
    try:
        client = genai.Client(api_key=API_KEY_GEMINI)
        logger.info("Gemini client configured")
    except Exception as e:
        logger.exception("Gemini config failed")
        print(f"ERROR|Gemini config: {str(e)}")
        sys.exit(1)

    # Step 1: Generate a detailed description of the image using Gemini Flash
    try:
        logger.info("Generating image description with Gemini Flash...")
        response = client.models.generate_content(
            model="models/gemini-2.5-flash",   # or "gemini-1.5-flash"
            contents=[
                "Describe this boat image in detail, focusing on visible parts, type of vessel, and any distinctive features. Be concise but thorough.",
                image
            ]
        )
        description = response.text
        logger.info("Description generated (first 100 chars): %s", description[:100])
    except Exception as e:
        logger.exception("Description generation failed")
        print(f"ERROR|Description failed: {str(e)}")
        sys.exit(1)

    # Step 2: Embed the description using the correct embedding model
    try:
        logger.info("Embedding description with models/gemini-embedding-001")
        emb_response = client.models.embed_content(
            model="models/gemini-embedding-001",
            contents=[description]
        )
        embedding = emb_response.embeddings[0].values
        logger.info("Embedding generated, dimensions: %d", len(embedding))
    except Exception as e:
        logger.exception("Embedding failed")
        print(f"ERROR|Embedding failed: {str(e)}")
        sys.exit(1)

    # Step 3: Upsert to Pinecone
    try:
        upsert_response = index.upsert(vectors=[{
            "id": filename,
            "values": embedding,
            "metadata": {
                "url": PUBLIC_URL,
                "path": IMAGE_PATH,
                "filename": filename,
                "description": description   # store description for later use
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