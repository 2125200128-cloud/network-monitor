"""
worker/metrics_collector.py — OBSOLETO.

Las métricas globales (global_metrics) ahora las calcula snmp_poller.py en cada ciclo
con los valores medidos en ese mismo ciclo. Este colector usaba el contador absoluto
ifInOctets del ifIndex 1 como si fueran Mbps y competía escribiendo en la misma tabla,
por lo que se desactivó para no mezclar datos incorrectos.
"""
import sys

if __name__ == '__main__':
    print("metrics_collector.py está obsoleto: global_metrics lo calcula worker/snmp_poller.py.", file=sys.stderr)
    sys.exit(0)
