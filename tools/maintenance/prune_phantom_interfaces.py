import mysql.connector

DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': 'root',
    'database': 'network_monitor'
}

conn = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True, buffered=True)

# For each device, find if it has real interfaces (e.g. Gi0/X or Fa0/X or with if_index >= 10000)
# and old mock interfaces (e.g. GigabitEthernet1/0/X with sequential if_index 1..48 and 0 traffic)
cursor.execute("SELECT DISTINCT dispositivo_id FROM interfaces_red")
dev_ids = [r['dispositivo_id'] for r in cursor.fetchall()]

cleaned_count = 0
for dev_id in dev_ids:
    cursor.execute("SELECT id, if_index, nombre, port_type FROM interfaces_red WHERE dispositivo_id=%s ORDER BY id", (dev_id,))
    ifaces = cursor.fetchall()
    
    # Check if there are real Cisco short names (Gi0/X, Fa0/X, TeX/X/X) or if_index >= 1000
    real_ifaces = [i for i in ifaces if i['if_index'] >= 1000 or any(i['nombre'].startswith(p) for p in ['Gi0/', 'Fa0/', 'Te0/', 'Eth1/', 'mgmt0'])]
    mock_ifaces = [i for i in ifaces if i['if_index'] < 1000 and (i['nombre'].startswith('GigabitEthernet1/0/') or i['nombre'].startswith('TenGigabitEthernet1/1/'))]
    
    if real_ifaces and mock_ifaces:
        print(f"Dev {dev_id}: Found {len(real_ifaces)} real interfaces and {len(mock_ifaces)} mock interfaces to clean.")
        for mi in mock_ifaces:
            # Delete associated telemetria_interfaces and enlace if any
            cursor.execute("DELETE FROM telemetria_interfaces WHERE interfaz_id=%s", (mi['id'],))
            cursor.execute("DELETE FROM interfaces_red WHERE id=%s", (mi['id'],))
            cleaned_count += 1

conn.commit()
print(f"Cleaned {cleaned_count} duplicate/phantom interfaces.")
conn.close()
