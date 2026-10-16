"""
Devuelve en JSON el último tráfico medido por snmp_poller.py (switch core configurado
o, si no hay, la suma global). Si no hay mediciones recientes responde status=no_data.
"""
import sys
import json

from nm_common import conectar, hace

sys.stdout.reconfigure(encoding='utf-8')


def get_live_traffic():
    try:
        conn = conectar()
        cur = conn.cursor(dictionary=True)
        cur.execute(f"""
            SELECT d.nombre, d.ip, m.traffic_in_mbps, m.traffic_out_mbps, m.fecha_registro
            FROM configuraciones_generales c
            JOIN dispositivos d ON d.ip = c.ip_switch_core
            JOIN metricas_red m ON m.dispositivo_id = d.id
            WHERE m.fecha_registro >= {hace(2)} AND m.traffic_in_mbps IS NOT NULL
            ORDER BY m.fecha_registro DESC LIMIT 1
        """)
        core = cur.fetchone()
        if core:
            conn.close()
            return {'status': 'success', 'fuente': 'core', 'dispositivo': core['nombre'], 'ip': core['ip'],
                    'in_mbps': float(core['traffic_in_mbps']), 'out_mbps': float(core['traffic_out_mbps']),
                    'medido_en': str(core['fecha_registro'])}

        cur.execute(f"""
            SELECT traffic_in_mbps, traffic_out_mbps, recorded_at FROM global_metrics
            WHERE recorded_at >= {hace(2)} AND traffic_in_mbps IS NOT NULL
            ORDER BY recorded_at DESC LIMIT 1
        """)
        g = cur.fetchone()
        conn.close()
        if g:
            return {'status': 'success', 'fuente': 'global', 'dispositivo': 'Suma de todos los equipos', 'ip': None,
                    'in_mbps': float(g['traffic_in_mbps']), 'out_mbps': float(g['traffic_out_mbps']),
                    'medido_en': str(g['recorded_at'])}
        return {'status': 'no_data', 'message': 'Sin mediciones de tráfico en los últimos 2 minutos'}
    except Exception as e:
        return {'status': 'error', 'message': str(e)}


if __name__ == '__main__':
    print(json.dumps(get_live_traffic()))
