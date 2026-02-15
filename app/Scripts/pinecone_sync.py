#!/usr/bin/env python3
"""
Pinecone sync script for boat image embeddings.
Receives arguments from Laravel: API keys, image path, public URL.
Outputs one line prefixed with SUCCESS|, SKIPPED|, or ERROR| for Laravel to parse.
All debug/error information goes to stderr and can be captured in Laravel logs.
"""

import os
import sys
import logging
import google.generativeai as genai
from pinecone import Pinecone, exceptions as pinecone_exceptions

# Setup logging to stderr so Laravel can capture it in error output
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    stream=sys.stderr
)
logger = logging.getLogger(__name__)

def main():
    # Validate arguments
    if len(sys.argv) != 6:
        logger.error("Incorrect number of arguments. Expected 5, got %d", len(sys.argv)-1)
        print("ERROR|Invalid arguments passed to script")
        sys.exit(1)

    API_KEY_GEMINI = sys.argv[1]
    API_KEY_PINECONE = sys.argv[2]
    INDEX_NAME = sys.argv[3]
    IMAGE_PATH = sys.argv[4]
    PUBLIC_URL = sys.argv[5]

    logger.info("Starting sync for image: %s", IMAGE_PATH)

    # Check if image file exists
    if not os.path.isfile(IMAGE_PATH):
        logger.error("Image file not found: %s", IMAGE_PATH)
        print("ERROR|Image file not found")
        sys.exit(1)

    filename = os.path.basename(IMAGE_PATH)

    try:
        # Configure APIs
        genai.configure(api_key=API_KEY_GEMINI)
        pc = Pinecone(api_key=API_KEY_PINECONE)
        index = pc.Index(INDEX_NAME)
        logger.info("APIs configured successfully")
    except Exception as e:
        logger.exception("Failed to configure APIs")
        print(f"ERROR|API configuration failed: {str(e)}")
        sys.exit(1)

    # Check if vector already exists in Pinecone
    try:
        fetch_response = index.fetch(ids=[filename])
        if filename in fetch_response.get('vectors', {}):
            logger.info("Vector already exists for %s, skipping", filename)
            print(f"SKIPPED|{filename} already exists in Pinecone.")
            return
        logger.info("No existing vector found, proceeding with upload")
    except pinecone_exceptions.PineconeException as e:
        logger.exception("Pinecone fetch failed")
        print(f"ERROR|Pinecone fetch error: {str(e)}")
        sys.exit(1)

    # Upload file to Gemini
    try:
        logger.info("Uploading file to Gemini...")
        img_file = genai.upload_file(path=IMAGE_PATH)
        logger.info("Upload successful, URI: %s", img_file.uri)
    except Exception as e:
        logger.exception("Gemini upload failed")
        print(f"ERROR|Gemini upload failed: {str(e)}")
        sys.exit(1)

    # Generate embedding
    try:
        logger.info("Generating embedding...")
        result = genai.embed_content(
            model="models/multimodalembedding@001",
            content=img_file,
            task_type="retrieval_document"
        )
        embedding = result['embedding']
        logger.info("Embedding generated, dimensions: %d", len(embedding))
    except Exception as e:
        logger.exception("Embedding generation failed")
        print(f"ERROR|Embedding generation failed: {str(e)}")
        sys.exit(1)

    # Upsert to Pinecone
    try:
        logger.info("Upserting to Pinecone...")
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
    except pinecone_exceptions.PineconeException as e:
        logger.exception("Pinecone upsert failed")
        print(f"ERROR|Pinecone upsert error: {str(e)}")
        sys.exit(1)
    except Exception as e:
        logger.exception("Unexpected error during upsert")
        print(f"ERROR|Unexpected error: {str(e)}")
        sys.exit(1)

    # Success
    print(f"SUCCESS|{filename} indexed")
    logger.info("Successfully indexed %s", filename)

if __name__ == "__main__":
    main()