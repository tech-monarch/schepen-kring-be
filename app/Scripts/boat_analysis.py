import sys
import json
import google.generativeai as genai
from pinecone import Pinecone
from PIL import Image
import requests
from io import BytesIO

# Inputs
API_KEY_GEMINI = sys.argv[1]
API_KEY_PINECONE = sys.argv[2]
INDEX_NAME = sys.argv[3]
QUERY_TEXT = sys.argv[4]

genai.configure(api_key=API_KEY_GEMINI)
pc = Pinecone(api_key=API_KEY_PINECONE)
index = pc.Index(INDEX_NAME)

def analyze():
    try:
        # 1. Get embedding for the query to find relevant reference images
        model_emb = "models/multimodalembedding@001"
        emb_result = genai.embed_content(model=model_emb, content=QUERY_TEXT, task_type="retrieval_query")
        
        # 2. Search Pinecone for the 3 most similar reference images
        search_results = index.query(vector=emb_result['embedding'], top_k=3, include_metadata=True)
        
        # 3. Download the images found in Pinecone to show them to Gemini
        reference_images = []
        for match in search_results['matches']:
            img_url = match['metadata']['url']
            response = requests.get(img_url)
            reference_images.append(Image.open(BytesIO(response.content)))

        # 4. Ask Gemini 1.5 Flash to identify parts using these images as context
        model_flash = genai.GenerativeModel('gemini-1.5-flash')
        
        prompt = f"""
        You are a professional marine mechanic. A user is asking: "{QUERY_TEXT}"
        
        I have provided {len(reference_images)} images from our boat database that match this query.
        Please identify the boat parts visible in these images and explain their function. 
        If the user is asking about a specific part, pinpoint it based on these technical references.
        Be concise and technical.
        """
        
        # We send the prompt + the actual image objects
        response = model_flash.generate_content([prompt] + reference_images)
        
        print(json.dumps({
            "answer": response.text,
            "references": [m['metadata']['url'] for m in search_results['matches']]
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    analyze()