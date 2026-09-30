import sys
import json
import mysql.connector

sys.stdout.reconfigure(encoding='utf-8')

DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': 'root',
    'database': 'network_monitor'
}

def get_cdp_adjacencies():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cur = conn.cursor(dictionary=True)
        
        # Consultar enlaces de red existentes
        cur.execute("""
            SELECT e.id, 
                   d1.nombre as origen_dispositivo, d1.ip as origen_ip,
                   d2.nombre as destino_dispositivo, d2.ip as destino_ip,
                   e.tipo_medio, e.estado, e.velocidad_mbps
            FROM enlaces_red e
            JOIN dispositivos d1 ON d1.id = e.origen_dispositivo_id
            JOIN dispositivos d2 ON d2.id = e.destino_dispositivo_id
            LIMIT 50
        """)
        rows = cur.fetchall()
        conn.close()
        
        adyacencias = []
        for r in rows:
            adyacencias.append({
                "local_device": r['origen_dispositivo'],
                "local_ip": r['origen_ip'],
                "remote_device": r['destino_dispositivo'],
                "remote_ip": r['destino_ip'],
                "type": r['tipo_medio'],
                "speed_mbps": r['velocidad_mbps'],
                "status": r['estado']
            })
            
        return {
            "status": "success",
            "count": len(adyacencias),
            "adyacencias": adyacencias
        }
    except Exception as e:
        return {
            "status": "error",
            "message": str(e),
            "adyacencias": []
        }

if __name__ == '__main__':
    result = get_cdp_adjacencies()
    print(json.dumps(result))
