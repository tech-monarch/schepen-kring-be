import os
import sys
import google.generativeai as genai
from pinecone import Pinecone

# Inputs from Laravel
API_KEY_GEMINI = sys.argv[1]
API_KEY_PINECONE = sys.argv[2]
INDEX_NAME = sys.argv[3]
IMAGE_PATH = sys.argv[4]
PUBLIC_URL = sys.argv[5]

# Configuration
genai.configure(api_key=API_KEY_GEMINI)
pc = Pinecone(api_key=API_KEY_PINECONE)
index = pc.Index(INDEX_NAME)

def process():
    filename = os.path.basename(IMAGE_PATH)
    
    try:
        # --- METHOD B: CHECK IF EXISTS ---
        # We check the index using the filename as the ID
        fetch_response = index.fetch(ids=[filename])
        
        if filename in fetch_response['vectors']:
            # If it exists, we exit early with a SKIPPED status
            print(f"SKIPPED|{filename} already exists in Pinecone.")
            return

        # --- PROCESS NEW IMAGE ---
        # 1. Upload file to Gemini
        img_file = genai.upload_file(path=IMAGE_PATH)
        
        # 2. Generate Embedding (1408 dimensions)
        # Using multimodal model to align text and image space
        result = genai.embed_content(
            model="models/multimodalembedding@001",
            content=img_file,
            task_type="retrieval_document"
        )
        
        # 3. Upsert to Pinecone
        index.upsert(vectors=[{
            "id": filename,
            "values": result['embedding'],
            "metadata": {
                "url": PUBLIC_URL,
                "path": IMAGE_PATH,
                "filename": filename
            }
        }])
        
        print(f"SUCCESS|{filename}")

    except Exception as e:
        # If Gemini or Pinecone fails, Laravel will catch this ERROR
        print(f"ERROR|{str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    process()