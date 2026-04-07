#!/usr/bin/env python3
"""
Mock server per CashDrawer - simula https://{ip}/selfcashapi/

Avvio:
  1. Genera il certificato (una sola volta):
     openssl req -x509 -newkey rsa:2048 -keyout mock_key.pem -out mock_cert.pem -days 365 -nodes -subj '/CN=localhost'

  2. Lancia il server:
     python3 mock_cash_drawer.py

  Il server risponde su https://0.0.0.0:443/selfcashapi/
  Per usare una porta diversa: python3 mock_cash_drawer.py 8443
"""

import json
import ssl
import sys
import uuid
from http.server import BaseHTTPRequestHandler, HTTPServer

# Stato in memoria delle operazioni aperte
operations: dict[str, dict] = {}


class CashDrawerHandler(BaseHTTPRequestHandler):

    def do_POST(self):
        if self.path != '/selfcashapi/':
            self._send(404, {'error': 'not found'})
            return

        length = int(self.headers.get('Content-Length', 0))
        try:
            body = json.loads(self.rfile.read(length))
        except json.JSONDecodeError:
            self._send(400, {'error': 'json non valido'})
            return

        tipo = body.get('tipo')
        print(f"[mock] tipo={tipo} body={body}")

        if tipo == 1:
            response = self._open(body)
        elif tipo == 2:
            response = self._poll(body)
        elif tipo == 3:
            response = self._cancel(body)
        else:
            response = {'req_status': 0, 'error': f'tipo {tipo!r} non valido'}

        print(f"[mock] → {response}")
        self._send(200, response)

    def _open(self, body: dict) -> dict:
        op_id = str(uuid.uuid4())
        operations[op_id] = {
            'importo': body.get('importo'),
            'opName': body.get('opName'),
            'payment_status': 0,  # 0 = in attesa
        }
        return {'req_status': 1, 'id': op_id}

    def _poll(self, body: dict) -> dict:
        op_id = body.get('id', '')
        op = operations.get(op_id)
        if op is None:
            return {'req_status': 0, 'error': 'operazione non trovata'}

        # Simula: dopo il primo poll lo stato diventa "pagato" (2)
        if op['payment_status'] == 0:
            op['payment_status'] = 1

        return {
            'req_status': 1,
            'payment_status': op['payment_status'],
            'payment_details': {'importo': op['importo']},
        }

    def _cancel(self, body: dict) -> dict:
        op_id = body.get('id', '')
        if op_id in operations:
            del operations[op_id]
        return {'req_status': 1}

    def _send(self, code: int, data: dict):
        body = json.dumps(data).encode()
        self.send_response(code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, fmt, *args):
        pass  # disabilita il log HTTP verboso di default


if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 443

    ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    ctx.load_cert_chain('mock_cert.pem', 'mock_key.pem')

    server = HTTPServer(('0.0.0.0', port), CashDrawerHandler)
    server.socket = ctx.wrap_socket(server.socket, server_side=True)

    print(f"[mock] CashDrawer in ascolto su https://0.0.0.0:{port}/selfcashapi/")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[mock] Fermato.")
