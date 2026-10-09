import sys
import time
import os

def execute_ssh_command(host, port, username, password, command):
    try:
        import paramiko
    except ImportError as e:
        print(f"Error: Librería SSH paramiko no encontrada ({e})", file=sys.stderr)
        sys.exit(1)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        # Timeout de conexión
        client.connect(
            hostname=host,
            port=int(port),
            username=username,
            password=password,
            timeout=5.0,
            banner_timeout=8.0,
            auth_timeout=5.0,
            look_for_keys=False,
            allow_agent=False
        )
        
        # Intentar exec_command
        try:
            stdin, stdout, stderr = client.exec_command(command, timeout=8.0)
            out = stdout.read().decode('utf-8', errors='replace').strip()
            err = stderr.read().decode('utf-8', errors='replace').strip()
            
            if out or err:
                if out:
                    print(out)
                if err:
                    print(err, file=sys.stderr)
                return
        except Exception:
            pass

        # Shell interactiva para Cisco IOS / Fortinet
        channel = client.invoke_shell(width=160, height=50)
        time.sleep(0.4)
        
        # Desactivar paginación en Cisco
        channel.send("terminal length 0\n")
        time.sleep(0.2)
        
        # Enviar comando
        channel.send(command + "\n")
        time.sleep(1.0)
        
        output = ""
        while channel.recv_ready():
            chunk = channel.recv(65535).decode('utf-8', errors='replace')
            output += chunk
            time.sleep(0.2)
            
        if output:
            lines = output.replace('\r\n', '\n').split('\n')
            clean_lines = [l for l in lines if not l.strip().startswith('terminal length 0')]
            print('\n'.join(clean_lines).strip())
        else:
            print("Sesión establecida. Comando ejecutado.")
            
    except paramiko.AuthenticationException:
        print(f"Error de autenticación: Credenciales rechazadas para usuario '{username}' en {host}:{port}", file=sys.stderr)
        sys.exit(2)
    except paramiko.ssh_exception.NoValidConnectionsError:
        print(f"Error de conexión: El puerto SSH {port} está cerrado o no responde en {host}", file=sys.stderr)
        sys.exit(3)
    except TimeoutError:
        print(f"Tiempo de espera agotado: El equipo {host}:{port} no respondió en 5 segundos.", file=sys.stderr)
        sys.exit(4)
    except Exception as e:
        err_name = type(e).__name__
        print(f"Fallo de conexión SSH ({err_name}): {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        try:
            client.close()
        except Exception:
            pass

if __name__ == "__main__":
    if len(sys.argv) < 6:
        print("Uso: python ssh_executor.py <host> <port> <username> <password> <command>", file=sys.stderr)
        sys.exit(1)
        
    host = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]
    password = sys.argv[4]
    command = " ".join(sys.argv[5:])

    execute_ssh_command(host, port, username, password, command)
