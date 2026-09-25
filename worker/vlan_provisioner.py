import sys
import json
import time
import paramiko

def execute_vlan_commands(host, port, username, password, commands_json):
    try:
        commands = json.loads(commands_json)
    except json.JSONDecodeError:
        print("Invalid JSON commands provided.", file=sys.stderr)
        sys.exit(1)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        # Secure connection with 5s timeout
        client.connect(
            hostname=host,
            port=int(port),
            username=username,
            password=password,
            timeout=5.0,
            look_for_keys=False,
            allow_agent=False
        )
        
        # Invoke an interactive shell to maintain state (config mode)
        shell = client.invoke_shell()
        shell.settimeout(10.0)
        
        output = ""
        
        # Helper to read output until it stops (basic wait)
        def read_until_ready(wait_time=0.5):
            time.sleep(wait_time)
            out = ""
            while shell.recv_ready():
                out += shell.recv(4096).decode('utf-8')
            return out

        # Read initial login banner
        output += read_until_ready(1.0)
        
        for cmd in commands:
            shell.send(cmd + "\n")
            # Usually "write memory" takes a bit longer
            wait_time = 2.0 if cmd.startswith("write") or cmd.startswith("copy") else 0.5
            output += read_until_ready(wait_time)
        
        print(output)
            
    except paramiko.AuthenticationException:
        print("Authentication failed.", file=sys.stderr)
        sys.exit(1)
    except paramiko.SSHException as sshException:
        print(f"Unable to establish SSH connection: {sshException}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Exception in connecting to the server: {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        client.close()

if __name__ == "__main__":
    if len(sys.argv) < 6:
        print("Usage: python vlan_provisioner.py <host> <port> <username> <password> <json_commands>", file=sys.stderr)
        sys.exit(1)
        
    host = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]
    commands_json = sys.argv[5]

    execute_vlan_commands(host, port, username, password, commands_json)
