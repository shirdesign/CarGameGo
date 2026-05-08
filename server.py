#!/usr/bin/env python3
"""
Local development server for CarGameGo.
Serves files + handles saving levels.json via POST /save-levels
"""

import http.server
import socketserver
import os
import json

PORT = 8000

class MyHTTPRequestHandler(http.server.SimpleHTTPRequestHandler):
    def end_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', '*')
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.end_headers()

    def do_POST(self):
        if self.path == '/save-levels':
            length = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(length)
            try:
                data = json.loads(body)
                with open('levels.json', 'w', encoding='utf-8') as f:
                    json.dump(data, f, ensure_ascii=False, indent=2)
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(b'{"ok":true}')
                print(f"✅ levels.json saved ({len(data)} levels)")
            except Exception as e:
                self.send_response(500)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps({"ok": False, "error": str(e)}).encode())
                print(f"❌ Save error: {e}")
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, format, *args):
        # Only log non-asset requests
        if not any(ext in args[0] for ext in ['.png', '.jpg', '.js', '.css']):
            super().log_message(format, *args)

os.chdir(os.path.dirname(os.path.abspath(__file__)))

print(f"🚀 Server running at http://localhost:{PORT}/")
print(f"📋 Admin:  http://localhost:{PORT}/admin.html")
print(f"🎮 Game:   http://localhost:{PORT}/index.html")
print("   Press Ctrl+C to stop\n")

with socketserver.TCPServer(("", PORT), MyHTTPRequestHandler) as httpd:
    httpd.serve_forever()
