from datetime import datetime
from pymongo import MongoClient

# ===== CONNECT =====
uri = "mongodb+srv://yourtrip.2r7ak3d.mongodb.net/?retryWrites=true&w=majority&appName=YourTrip"

client = MongoClient(
    uri,
    username="verontmy_db_user",
    password="7O7f7vqdLvB0FVbZ"
)

db = client["yourtrip_nosql"]

route_details = db["RouteDetails"]
passenger_feedback = db["PassengerFeedback"]

# ===== CLEAN START (for demo) =====
route_details.delete_many({})
passenger_feedback.delete_many({})

# ===== CREATE (INSERT) =====
route_docs = [
    {
        "_id": "179_1",
        "service_no": "179",
        "direction": 1,
        "operator": "SBST",
        "category": "TRUNK",
        "stops": [
            {"stop_id": 22009, "name": "BOON LAY INT", "lat": 1.3394, "lng": 103.7064},
            {"stop_id": 27031, "name": "NANYANG DR - OPP HALL 1", "lat": 1.3456, "lng": 103.6812}
        ]
    },
    {
        "_id": "199_1",
        "service_no": "199",
        "direction": 1,
        "operator": "SBST",
        "category": "TRUNK",
        "stops": [
            {"stop_id": 22009, "name": "BOON LAY INT", "lat": 1.3394, "lng": 103.7064},
            {"stop_id": 27211, "name": "NANYANG DR - HALL 6", "lat": 1.3480, "lng": 103.6840}
        ]
    }
]

route_details.insert_many(route_docs)

feedback_docs = [
    {"route": "179", "stop_id": 22009, "rating": 3, "comment": "Very crowded in the morning", "created_at": datetime.utcnow()},
    {"route": "179", "stop_id": 27031, "rating": 4, "comment": "Acceptable during off-peak", "created_at": datetime.utcnow()},
    {"route": "199", "stop_id": 22009, "rating": 5, "comment": "Fast and frequent service", "created_at": datetime.utcnow()}
]

passenger_feedback.insert_many(feedback_docs)

print("Inserted RouteDetails and PassengerFeedback")

# ===== READ =====
print("\nRoute 179 Details:")
for doc in route_details.find({"service_no": "179"}):
    print(doc)

# ===== UPDATE =====
passenger_feedback.update_one(
    {"route": "179", "stop_id": 22009},
    {"$set": {"rating": 4}}
)
print("\nUpdated rating for route 179 at stop 22009")

# ===== DELETE =====
passenger_feedback.delete_one(
    {"route": "199", "stop_id": 22009}
)
print("Deleted one feedback for route 199")

# ===== AGGREGATION (ADVANCED) =====
print("\nAverage rating per route:")
pipeline = [
    {"$group": {"_id": "$route", "avg_rating": {"$avg": "$rating"}, "count": {"$sum": 1}}},
    {"$sort": {"_id": 1}}
]

for result in passenger_feedback.aggregate(pipeline):
    print(result)

client.close()
