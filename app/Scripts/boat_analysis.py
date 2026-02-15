import sys
import json
import google.generativeai as genai
from pinecone import Pinecone
from PIL import Image
import requests
from io import BytesIO
import logging

# Inputs
API_KEY_GEMINI = sys.argv[1]
API_KEY_PINECONE = sys.argv[2]
INDEX_NAME = sys.argv[3]
QUERY_TEXT = sys.argv[4]

# Configure logging (optional, for debugging)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def detect_language(text):
    """
    Quick and dirty language detection (Dutch vs English).
    For production, consider using a proper library like langdetect.
    """
    common_dutch_words = ['de', 'het', 'een', 'van', 'en', 'is', 'voor', 'op', 'met']
    words = text.lower().split()
    dutch_score = sum(1 for word in words if word in common_dutch_words)
    return 'nl' if dutch_score > 0 else 'en'

def analyze():
    try:
        genai.configure(api_key=API_KEY_GEMINI)
        pc = Pinecone(api_key=API_KEY_PINECONE)
        index = pc.Index(INDEX_NAME)

        # 1. Detect user language
        user_lang = detect_language(QUERY_TEXT)

        # 2. Get embedding for the query
        model_emb = "models/multimodalembedding@001"
        emb_result = genai.embed_content(
            model=model_emb,
            content=QUERY_TEXT,
            task_type="retrieval_query"
        )
        query_vector = emb_result['embedding']

        # 3. Search Pinecone for top 3 relevant images
        search_results = index.query(
            vector=query_vector,
            top_k=3,
            include_metadata=True
        )

        matches = search_results.get('matches', [])
        reference_images = []
        image_urls = []

        # 4. Download images if found
        for match in matches:
            img_url = match['metadata']['url']
            try:
                response = requests.get(img_url, timeout=10)
                img = Image.open(BytesIO(response.content))
                reference_images.append(img)
                image_urls.append(img_url)
            except Exception as e:
                logger.warning(f"Failed to load image {img_url}: {e}")

        # 5. Prepare the prompt (with language instruction)
        if not reference_images:
            # No images found – fallback to text‑only answer
            prompt = f"""
You are a professional marine mechanic. A user asked: "{QUERY_TEXT}"

I could not find any matching reference images in the database. 
Please answer the question based on your general knowledge of boat parts and mechanics.
Be concise, technical, and respond in {'Dutch' if user_lang == 'nl' else 'English'}.
"""
            model_flash = genai.GenerativeModel('gemini-1.5-flash')
            response = model_flash.generate_content(prompt)
            answer = response.text

        else:
            # We have reference images
            prompt = f"""
You are a professional marine mechanic. A user asked: "{QUERY_TEXT}"

I have provided {len(reference_images)} images from our boat database that match this query.
Please identify the boat parts visible in these images and explain their function. 
If the user is asking about a specific part, pinpoint it based on these technical references.
Be concise, technical, and respond in {'Dutch' if user_lang == 'nl' else 'English'}.
"""
            model_flash = genai.GenerativeModel('gemini-1.5-flash')
            response = model_flash.generate_content([prompt] + reference_images)
            answer = response.text if response.text else "I'm sorry, I couldn't analyze the images."

        # 6. Return structured JSON
        print(json.dumps({
            "answer": answer,
            "references": image_urls
        }))

    except Exception as e:
        logger.exception("Analysis error")
        print(json.dumps({
            "error": str(e),
            "answer": "Our boat analysis service is temporarily unavailable. Please try again later.",
            "references": []
        }))
        sys.exit(1)

if __name__ == "__main__":
    analyze()