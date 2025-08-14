# M06 — Social & Secure Messaging

Tables:
- posts
- safe_rooms
- messages
- panic_codes

Test locally:
- Create user and auth via Sanctum.
- Posts: GET/POST /api/posts
- Rooms: GET/POST /api/rooms
- Messages: GET/POST /api/rooms/{room}/messages
- Panic: POST /api/panic/code, POST /api/panic/trigger
