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

genai.configure(api_key=API_KEY_GEMINI)
pc = Pinecone(api_key=API_KEY_PINECONE)
index = pc.Index(INDEX_NAME)

def process():
    try:
        # 1. Upload file to Gemini
        img_file = genai.upload_file(path=IMAGE_PATH)
        
        # 2. Generate Embedding (1408 dimensions)
        result = genai.embed_content(
            model="models/multimodalembedding@001",
            content=img_file,
            task_type="retrieval_document"
        )
        
        # 3. Upsert to Pinecone
        filename = os.path.basename(IMAGE_PATH)
        index.upsert(vectors=[{
            "id": filename,
            "values": result['embedding'],
            "metadata": {
                "url": PUBLIC_URL,
                "path": IMAGE_PATH
            }
        }])
        print(f"SUCCESS|{filename}")
    except Exception as e:
        print(f"ERROR|{str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    process()