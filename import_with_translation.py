"""
Script to import stop names from MySQL to MongoDB with automatic translation
Uses MyMemory Translation API to translate to Chinese, Tamil, and Malay
"""

import mysql.connector
from pymongo import MongoClient
import requests
import time
from datetime import datetime
from urllib.parse import quote

# MySQL Configuration
# Using SSH tunnel credentials from MySQL Workbench
# SSH: inf2003-dev@104.198.169.207
# MySQL: inf2003-sqldev@127.0.0.1:3306
MYSQL_CONFIG = {
    'host': '127.0.0.1',
    'port': 3306,
    'user': 'inf2003-sqldev',
    'password': 'Inf2003#DevSecure!2025',
    'database': 'yourtrip_db'
}

# MongoDB Atlas Configuration
MONGO_USERNAME = 'inf2003-mongoDB'
MONGO_PASSWORD = 'Password123456'
MONGO_CLUSTER = 'inf2003-nosql.pblgwjp'
MONGO_URI = f'mongodb+srv://{MONGO_USERNAME}:{MONGO_PASSWORD}@{MONGO_CLUSTER}.mongodb.net/?retryWrites=true&w=majority'

# MyMemory Translation API
MYMEMORY_API_URL = 'https://api.mymemory.translated.net/get'

# Language codes for MyMemory API
LANGUAGES = {
    'zh': 'zh-CN',  # Chinese (Simplified)
    'ta': 'ta',     # Tamil
    'ms': 'ms'      # Malay
}


def translate_text(text, target_lang, source_lang='en'):
    """
    Translate text using MyMemory Translation API

    Args:
        text: Text to translate
        target_lang: Target language code (zh-CN, ta, ms)
        source_lang: Source language code (default: en)

    Returns:
        Translated text or original text if translation fails
    """
    try:
        # MyMemory API has rate limits (free tier: 1000 words/day, 10 requests/second)
        # Add small delay to avoid rate limiting
        time.sleep(0.2)

        # URL encode the text
        encoded_text = quote(text)

        # Build API request
        url = f'{MYMEMORY_API_URL}?q={encoded_text}&langpair={source_lang}|{target_lang}'

        response = requests.get(url, timeout=10)

        if response.status_code == 200:
            data = response.json()

            # Check if translation was successful
            if data.get('responseStatus') == 200:
                translated = data['responseData']['translatedText']

                # MyMemory sometimes returns the original text if translation fails
                # Also check if translation makes sense (not too similar to original)
                if translated and translated != text:
                    print(f"  ✓ {text} -> {translated} ({target_lang})")
                    return translated

        # If translation fails, return original text
        print(f"  ⚠ Translation failed for '{text}' to {target_lang}, using original")
        return text

    except Exception as e:
        print(f"  ✗ Error translating '{text}': {str(e)}")
        return text


def translate_stop_name(english_name):
    """
    Translate a stop name to all supported languages

    Args:
        english_name: English name of the stop

    Returns:
        Dictionary with translations in all languages
    """
    translations = {
        'en': english_name
    }

    print(f"\nTranslating: {english_name}")

    for lang_code, mymemory_code in LANGUAGES.items():
        translated = translate_text(english_name, mymemory_code)
        translations[lang_code] = translated

    return translations


def connect_mysql():
    """Connect to MySQL database"""
    try:
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        print("✓ Connected to MySQL")
        return conn
    except Exception as e:
        print(f"✗ MySQL connection failed: {str(e)}")
        raise


def connect_mongo():
    """Connect to MongoDB Atlas"""
    try:
        client = MongoClient(MONGO_URI, serverSelectionTimeoutMS=5000)
        # Test connection
        client.admin.command('ping')
        db = client['yourtrip_db']
        print("✓ Connected to MongoDB Atlas")
        return db
    except Exception as e:
        print(f"✗ MongoDB Atlas connection failed: {str(e)}")
        print("  Check your username, password, and cluster name")
        raise


def fetch_mrt_stations(mysql_conn):
    """Fetch MRT stations from MySQL"""
    cursor = mysql_conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT DISTINCT
            stop_id,
            name,
            lat,
            lng
        FROM MRTStations
        WHERE name IS NOT NULL
        ORDER BY stop_id
    """)
    stations = cursor.fetchall()
    cursor.close()
    print(f"✓ Found {len(stations)} MRT stations")
    return stations


def fetch_bus_stops(mysql_conn, limit=100):
    """Fetch bus stops from MySQL"""
    cursor = mysql_conn.cursor(dictionary=True)
    cursor.execute(f"""
        SELECT DISTINCT
            BUS_STOP as stop_id,
            LOC_DESC as name,
            Latitude as lat,
            Longitude as lng
        FROM BusStops
        WHERE LOC_DESC IS NOT NULL
        ORDER BY BUS_STOP
        LIMIT {limit}
    """)
    stops = cursor.fetchall()
    cursor.close()
    print(f"✓ Found {len(stops)} bus stops")
    return stops


def import_stops_with_translation(stops, stop_type, mongo_db):
    """
    Import stops to MongoDB with automatic translation

    Args:
        stops: List of stop dictionaries from MySQL
        stop_type: 'mrt' or 'bus'
        mongo_db: MongoDB database connection

    Returns:
        Tuple of (inserted_count, updated_count)
    """
    collection = mongo_db['multilingual_stops']

    inserted = 0
    updated = 0
    total = len(stops)

    print(f"\nStarting translation and import for {total} {stop_type.upper()} stops...")
    print("This may take a few minutes due to API rate limits...")

    for idx, stop in enumerate(stops, 1):
        print(f"\n[{idx}/{total}] Processing: {stop['name']} ({stop['stop_id']})")

        # Translate the stop name
        translations = translate_stop_name(stop['name'])

        # Prepare MongoDB document
        document = {
            'stop_id': str(stop['stop_id']),
            'stop_type': stop_type,
            'names': translations,
            'lat': float(stop['lat']) if stop['lat'] else None,
            'lng': float(stop['lng']) if stop['lng'] else None,
            'updated_at': datetime.now().isoformat()
        }

        # Upsert to MongoDB
        try:
            result = collection.update_one(
                {'stop_id': document['stop_id']},
                {'$set': document},
                upsert=True
            )

            if result.upserted_id:
                inserted += 1
                print(f"  ✓ Inserted to MongoDB")
            else:
                updated += 1
                print(f"  ✓ Updated in MongoDB")

        except Exception as e:
            print(f"  ✗ MongoDB error: {str(e)}")

    return inserted, updated


def main():
    """Main import function"""
    print("="*60)
    print("MULTILINGUAL STOP NAMES IMPORT WITH AUTO-TRANSLATION")
    print("="*60)
    print(f"Translation API: MyMemory")
    print(f"Languages: English, Chinese, Tamil, Malay")
    print(f"Target: MongoDB Atlas (Cluster: inf2003-nosql)")
    print("="*60)

    try:
        # Connect to MySQL
        print("\n1. Connecting to MySQL...")
        mysql_conn = connect_mysql()

        # Connect to MongoDB Atlas
        print("\n2. Connecting to MongoDB Atlas...")
        mongo_db = connect_mongo()

        # Ask user what to import
        print("\n3. What would you like to import?")
        print("   1) MRT stations only")
        print("   2) Bus stops only (first 100)")
        print("   3) Both MRT and Bus stops")

        choice = input("\nEnter your choice (1-3): ").strip()

        if choice in ['1', '3']:
            # Import MRT stations
            print("\n" + "="*60)
            print("IMPORTING MRT STATIONS")
            print("="*60)
            mrt_stations = fetch_mrt_stations(mysql_conn)
            mrt_inserted, mrt_updated = import_stops_with_translation(
                mrt_stations, 'mrt', mongo_db
            )
            print(f"\n✓ MRT Complete: {mrt_inserted} inserted, {mrt_updated} updated")

        if choice in ['2', '3']:
            # Import bus stops
            print("\n" + "="*60)
            print("IMPORTING BUS STOPS (Limited to 100 for demo)")
            print("="*60)
            bus_stops = fetch_bus_stops(mysql_conn, limit=100)
            bus_inserted, bus_updated = import_stops_with_translation(
                bus_stops, 'bus', mongo_db
            )
            print(f"\n✓ Bus Complete: {bus_inserted} inserted, {bus_updated} updated")

        # Close MySQL connection
        mysql_conn.close()

        print("\n" + "="*60)
        print("IMPORT COMPLETED SUCCESSFULLY!")
        print("="*60)
        print("\nYou can now:")
        print("1. Start the multilingual API: python multilingual_api.py")
        print("2. View data in MongoDB Atlas web interface")
        print("3. Test the API endpoints")

    except Exception as e:
        print(f"\n✗ Error: {str(e)}")
        return 1

    return 0


if __name__ == '__main__':
    exit(main())
