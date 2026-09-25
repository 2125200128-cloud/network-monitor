import sys
import paramiko

def execute_ssh_command(host, port, username, password, command):
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        # Secure non-persistent connection with 5s timeout
        client.connect(
            hostname=host,
            port=int(port),
            username=username,
            password=password,
            timeout=5.0
        )
        
        # Execute the command
        stdin, stdout, stderr = client.exec_command(command, timeout=60.0)
        
        out = stdout.read().decode('utf-8').strip()
        err = stderr.read().decode('utf-8').strip()
        
        if out:
            print(out)
        if err:
            print(err, file=sys.stderr)
            
    except paramiko.AuthenticationException:
        print("Authentication failed.", file=sys.stderr)
        sys.exit(1)
    except paramiko.SSHException as sshException:
        print(f"Unable to establish SSH connection: {sshException}", file=sys.stderr)
        sys.exit(1)
    except paramiko.ssh_exception.NoValidConnectionsError as e:
        print(f"Unable to connect to port {port} on {host}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Exception in connecting to the server: {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        # Immediately close SSH session
        client.close()

if __name__ == "__main__":
    if len(sys.argv) < 6:
        print("Usage: python ssh_executor.py <host> <port> <username> <password> <command>", file=sys.stderr)
        sys.exit(1)
        
    host = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]
    command = " ".join(sys.argv[5:]) # The command might be split if not properly quoted, but in Laravel we pass it as a single string argument. Process does this safely.

    execute_ssh_command(host, port, username, password, command)
