import sys
import time
import os

# Lectura de la shell: se da por terminada la salida cuando el equipo deja de mandar datos este tiempo
# (comandos lentos como write memory o un ping largo siguen mandando), con un tope total.
SILENCIO_S = 2.0
TOPE_S = 45.0


def leer_hasta_silencio(channel, silencio=SILENCIO_S, tope=TOPE_S):
    salida = ''
    inicio = ultimo = time.time()
    while time.time() - inicio < tope:
        if channel.recv_ready():
            salida += channel.recv(65535).decode('utf-8', errors='replace')
            ultimo = time.time()
        elif channel.exit_status_ready() or time.time() - ultimo >= silencio:
            break
        else:
            time.sleep(0.1)
    return salida


def execute_ssh_command(host, port, username, password, command):
    try:
        import paramiko
    except ImportError as e:
        print(f"Error: Librería SSH paramiko no encontrada ({e})", file=sys.stderr)
        sys.exit(1)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    # Varias líneas (bloque de configuración o pegado): van todas en la misma sesión interactiva
    lineas = command.split('\n')
    bloque = len(lineas) > 1

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

        # Un solo comando: exec_command (la salida llega limpia, sin prompts)
        if not bloque:
            try:
                stdin, stdout, stderr = client.exec_command(command, timeout=TOPE_S)
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
        channel = client.invoke_shell(width=200, height=50)
        leer_hasta_silencio(channel, silencio=0.8, tope=5)   # banner y prompt inicial

        # Desactivar paginación en Cisco
        channel.send("terminal length 0\n")
        leer_hasta_silencio(channel, silencio=0.5, tope=3)

        output = ''
        for linea in lineas:
            # Las líneas vacías también se mandan: responden confirmaciones como "[confirm]" o "Destination filename?"
            channel.send(linea.rstrip('\r') + "\n")
            output += leer_hasta_silencio(channel, silencio=0.6 if bloque else SILENCIO_S)
        output += leer_hasta_silencio(channel)

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
    # La contraseña se recibe por la variable de entorno NOC_SSH_PASSWORD para que no
    # aparezca en la línea de comandos del proceso.
    # Un bloque de varias líneas llega en NOC_SSH_COMANDO (los saltos de línea no viajan bien como argumento en Windows)
    password = os.environ.get('NOC_SSH_PASSWORD')
    command = os.environ.get('NOC_SSH_COMANDO') or " ".join(sys.argv[4:])
    if len(sys.argv) < 4 or not password or not command:
        print("Uso: NOC_SSH_PASSWORD=... [NOC_SSH_COMANDO=...] python ssh_executor.py <host> <port> <username> [command]", file=sys.stderr)
        sys.exit(1)

    host = sys.argv[1]
    port = sys.argv[2]
    username = sys.argv[3]

    execute_ssh_command(host, port, username, password, command)
