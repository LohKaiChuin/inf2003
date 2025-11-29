"""
Multilingual Support API for Transport Stop Names
Supports: English, Chinese (中文), Tamil (தமிழ்), Malay (Bahasa)
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
from pymongo import MongoClient
from pymongo.errors import DuplicateKeyError
import os
from datetime import datetime

app = Flask(__name__)
CORS(app)  # Enable CORS for frontend access

# MongoDB Atlas Configuration
MONGO_USERNAME = os.getenv('MONGO_USERNAME', 'inf2003-mongoDB')
MONGO_PASSWORD = os.getenv('MONGO_PASSWORD', 'Password123456')
MONGO_CLUSTER = os.getenv('MONGO_CLUSTER', 'inf2003-nosql.pblgwjp')
MONGO_URI = f'mongodb+srv://{MONGO_USERNAME}:{MONGO_PASSWORD}@{MONGO_CLUSTER}.mongodb.net/?retryWrites=true&w=majority'

client = MongoClient(MONGO_URI)
db = client['yourtrip_db']

# Collections
stops_collection = db['multilingual_stops']

# Create indexes for better query performance
stops_collection.create_index('stop_id', unique=True)
stops_collection.create_index('stop_type')


@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'service': 'multilingual-api',
        'timestamp': datetime.now().isoformat()
    })


@app.route('/api/stops/import', methods=['POST'])
def import_stops():
    """
    Import stop names with multilingual support
    Expected JSON format:
    {
        "stops": [
            {
                "stop_id": "NS1",
                "stop_type": "mrt",  // or "bus"
                "names": {
                    "en": "Jurong East",
                    "zh": "裕廊东",
                    "ta": "ஜூரோங் கிழக்கு",
                    "ms": "Jurong Timur"
                },
                "lat": 1.3329,
                "lng": 103.7436
            }
        ]
    }
    """
    try:
        data = request.get_json()

        if not data or 'stops' not in data:
            return jsonify({'error': 'Invalid request format. Expected "stops" array'}), 400

        stops = data['stops']
        inserted_count = 0
        updated_count = 0
        errors = []

        for stop in stops:
            # Validate required fields
            if 'stop_id' not in stop or 'names' not in stop:
                errors.append(f"Missing required fields for stop: {stop}")
                continue

            # Prepare document
            document = {
                'stop_id': stop['stop_id'],
                'stop_type': stop.get('stop_type', 'bus'),
                'names': {
                    'en': stop['names'].get('en', ''),
                    'zh': stop['names'].get('zh', ''),
                    'ta': stop['names'].get('ta', ''),
                    'ms': stop['names'].get('ms', '')
                },
                'lat': stop.get('lat'),
                'lng': stop.get('lng'),
                'updated_at': datetime.now().isoformat()
            }

            # Upsert (insert or update)
            try:
                result = stops_collection.update_one(
                    {'stop_id': stop['stop_id']},
                    {'$set': document},
                    upsert=True
                )

                if result.upserted_id:
                    inserted_count += 1
                else:
                    updated_count += 1

            except Exception as e:
                errors.append(f"Error processing stop {stop['stop_id']}: {str(e)}")

        return jsonify({
            'success': True,
            'inserted': inserted_count,
            'updated': updated_count,
            'errors': errors
        }), 200

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/stops', methods=['GET'])
def get_stops():
    """
    Get all stops with optional filtering
    Query params:
    - stop_type: 'mrt' or 'bus'
    - lang: 'en', 'zh', 'ta', 'ms' (default: 'en')
    """
    try:
        stop_type = request.args.get('stop_type')
        lang = request.args.get('lang', 'en')

        # Build query
        query = {}
        if stop_type:
            query['stop_type'] = stop_type

        # Fetch stops
        stops = list(stops_collection.find(query, {'_id': 0}))

        # Format response with selected language
        result = []
        for stop in stops:
            result.append({
                'stop_id': stop['stop_id'],
                'stop_type': stop['stop_type'],
                'name': stop['names'].get(lang, stop['names'].get('en', '')),
                'names': stop['names'],  # Include all translations
                'lat': stop.get('lat'),
                'lng': stop.get('lng')
            })

        return jsonify({
            'success': True,
            'language': lang,
            'count': len(result),
            'stops': result
        }), 200

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/stops/<stop_id>', methods=['GET'])
def get_stop(stop_id):
    """
    Get a single stop by ID
    Query params:
    - lang: 'en', 'zh', 'ta', 'ms' (default: 'en')
    """
    try:
        lang = request.args.get('lang', 'en')

        stop = stops_collection.find_one({'stop_id': stop_id}, {'_id': 0})

        if not stop:
            return jsonify({'error': 'Stop not found'}), 404

        return jsonify({
            'success': True,
            'stop': {
                'stop_id': stop['stop_id'],
                'stop_type': stop['stop_type'],
                'name': stop['names'].get(lang, stop['names'].get('en', '')),
                'names': stop['names'],
                'lat': stop.get('lat'),
                'lng': stop.get('lng')
            }
        }), 200

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/stops/search', methods=['GET'])
def search_stops():
    """
    Search stops by name in any language
    Query params:
    - q: search query
    - lang: preferred language for results
    """
    try:
        query = request.args.get('q', '').strip()
        lang = request.args.get('lang', 'en')

        if not query:
            return jsonify({'error': 'Search query required'}), 400

        # Search in all language fields
        stops = list(stops_collection.find({
            '$or': [
                {'names.en': {'$regex': query, '$options': 'i'}},
                {'names.zh': {'$regex': query, '$options': 'i'}},
                {'names.ta': {'$regex': query, '$options': 'i'}},
                {'names.ms': {'$regex': query, '$options': 'i'}}
            ]
        }, {'_id': 0}))

        # Format results
        result = []
        for stop in stops:
            result.append({
                'stop_id': stop['stop_id'],
                'stop_type': stop['stop_type'],
                'name': stop['names'].get(lang, stop['names'].get('en', '')),
                'names': stop['names'],
                'lat': stop.get('lat'),
                'lng': stop.get('lng')
            })

        return jsonify({
            'success': True,
            'query': query,
            'language': lang,
            'count': len(result),
            'results': result
        }), 200

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/languages', methods=['GET'])
def get_languages():
    """Get list of supported languages"""
    return jsonify({
        'languages': [
            {'code': 'en', 'name': 'English', 'native_name': 'English'},
            {'code': 'zh', 'name': 'Chinese', 'native_name': '中文'},
            {'code': 'ta', 'name': 'Tamil', 'native_name': 'தமிழ்'},
            {'code': 'ms', 'name': 'Malay', 'native_name': 'Bahasa Melayu'}
        ]
    }), 200


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=True)
